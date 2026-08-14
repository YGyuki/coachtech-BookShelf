<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Book;
use App\Models\ReadingPlan;
use App\Enums\ReadingPlanStatus;
use App\Notifications\GeneralNotification;
use App\Notifications\ReadingPlanReminder;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ReadingPlanNotificationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function 各タイミングで正しい通知メッセージとタイミング情報が送信される(): void
    {
        // 1. Arrange
        Notification::fake();

        $user = User::factory()->create();
        $book1 = Book::factory()->create(['title' => '書籍1']);
        $book2 = Book::factory()->create(['title' => '書籍2']);
        $book3 = Book::factory()->create(['title' => '書籍3']);

        // ①【3日前通知】期日が「今日から3日後」
        $planBefore3Days = ReadingPlan::create([
            'user_id' => $user->id,
            'book_id' => $book1->id,
            'target_date' => Carbon::today()->addDays(3)->format('Y-m-d'),
            'status' => ReadingPlanStatus::InProgress->value,
        ]);

        // ②【当日通知】期日が「今日当日」
        $planToday = ReadingPlan::create([
            'user_id' => $user->id,
            'book_id' => $book2->id,
            'target_date' => Carbon::today()->format('Y-m-d'),
            'status' => ReadingPlanStatus::InProgress->value,
        ]);

        // ③【3日後通知】期日が「今日から3日前」
        $planAfter3Days = ReadingPlan::create([
            'user_id' => $user->id,
            'book_id' => $book3->id,
            'target_date' => Carbon::today()->subDays(3)->format('Y-m-d'),
            'status' => ReadingPlanStatus::Expired->value,
        ]);

        // 2. Act
        $this->artisan('app:check-reading-plans');

        // 3. Assert
        Notification::assertCount(3, $user);

        // ① 3日前通知の検証
        Notification::assertSentTo($user, ReadingPlanReminder::class, function ($notification) use ($user) {
            $data = $notification->toDatabase($user);
            return $data['timing'] === 'three_days_before'
                && str_contains($data['title'], '期日まであと3日です');
        });

        // ② 当日通知の検証（'today' から 'on_due_date' に修正、文章も合わせる）
        Notification::assertSentTo($user, ReadingPlanReminder::class, function ($notification) use ($user) {
            $data = $notification->toDatabase($user);
            return $data['timing'] === 'on_due_date'
                && str_contains($data['title'], '読書目標が期日');
        });

        // ③ 3日後通知の検証（文章を現在のクラスの仕様に合わせる）
        Notification::assertSentTo($user, ReadingPlanReminder::class, function ($notification) use ($user) {
            $data = $notification->toDatabase($user);
            return $data['timing'] === 'three_days_after'
                && str_contains($data['title'], '3日が過ぎました');
        });
    }

    /** @test */
    public function 期日を超過した計画のステータスが自動的にexpiredに変更される(): void
    {
        // 1. Arrange
        $user = User::factory()->create();
        $bookForExpired = Book::factory()->create();
        $bookForToday = Book::factory()->create();

        // 過去の日付（昨日＝1日前）の未完了データを準備
        $expiredPlan = ReadingPlan::create([
            'user_id' => $user->id,
            'book_id' => $bookForExpired->id,
            'target_date' => Carbon::today()->subDays(1)->format('Y-m-d'),
            'status' => ReadingPlanStatus::InProgress->value,
        ]);

        // 今日が期日の未完了データ（期限切れにならない想定）
        $todayPlan = ReadingPlan::create([
            'user_id' => $user->id,
            'book_id' => $bookForToday->id,
            'target_date' => Carbon::today()->format('Y-m-d'),
            'status' => ReadingPlanStatus::InProgress->value,
        ]);

        // 2. Act
        $this->artisan('app:check-reading-plans');

        // 3. Assert
        // 過去の計画は expired になっていることを検証
        $this->assertEquals(
            ReadingPlanStatus::Expired->value,
            $expiredPlan->refresh()->status->value
        );

        // 当日の計画はステータスが変わっていない（in_progressのまま）ことを検証
        $this->assertEquals(
            ReadingPlanStatus::InProgress->value,
            $todayPlan->refresh()->status->value
        );
    }

    /** @test */
    public function ユーザーは自身の未読通知を既読にすることができる(): void
    {
        // 1. Arrange
        $user = User::factory()->create();

        // テスト用の通知データをユーザーに送信して未読状態を作る
        $book = Book::factory()->create();
        $plan = ReadingPlan::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'target_date' => Carbon::today('Asia/Tokyo')->format('Y-m-d'),
            'status' => ReadingPlanStatus::InProgress->value,
        ]);

        $user->notify(new ReadingPlanReminder($plan, 'today'));

        // 生成された未読通知をDBから1件取得
        $notification = $user->unreadNotifications->first();
        $this->assertNotNull($notification); // 最初は確実に未読であることを確認

        // 2. Act
        // 既読化
        $response = $this->actingAs($user)->post(route('notifications.read', $notification->id));

        // 3. Assert
        // リダイレクト（302）されることを検証
        $response->assertStatus(302);

        // ユーザーの未読通知カウントが 0 になっていることを検証
        $this->assertEquals(0, $user->fresh()->unreadNotifications->count());

        // 通知レコードの read_at に既読日時が入ったことを検証
        $this->assertNotNull($notification->fresh()->read_at);
    }

    /** @test */
    public function 他人の通知を既読にしようとすると404エラーを返す(): void
    {
        // 1. Arrange
        $user = User::factory()->create();
        $otherUser = User::factory()->create(); // 他人

        $book = Book::factory()->create();
        $plan = ReadingPlan::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'target_date' => Carbon::today('Asia/Tokyo')->format('Y-m-d'),
            'status' => ReadingPlanStatus::InProgress->value,
        ]);

        // ユーザーに対して通知を送る
        $user->notify(new ReadingPlanReminder($plan, 'today'));
        $notificationOfUser = $user->unreadNotifications->first();

        // 2. Act
        // 他人としてログインし、ユーザーの通知IDを指定して既読化を試みる
        $response = $this->actingAs($otherUser)
            ->post(route('notifications.read', $notificationOfUser->id));

        // 3. Assert
        // 404エラーで弾かれることを検証
        $response->assertStatus(404);

        // 通知は、不正に既読化されず「未読（null）」のままであることを検証
        $this->assertNull($notificationOfUser->fresh()->read_at);
    }
}
