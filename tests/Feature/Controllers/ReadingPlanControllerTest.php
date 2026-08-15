<?php

namespace Tests\Feature\Controllers;

use App\Enums\ReadingPlanStatus;
use App\Models\User;
use App\Models\Book;
use App\Models\ReadingPlan;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReadingPlanControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function ログインユーザーは書籍と期日を指定して新しく読書計画を作成できる(): void
    {
        // 1. Arrange
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $postData = [
            'book_id' => $book->id,
            'target_date' => Carbon::today('Asia/Tokyo')->addDays(5)->format('Y-m-d'),
        ];

        // 2. Act
        $response = $this->actingAs($user)->post(route('reading-plans.store'), $postData);

        // 3. Assert
        // ステータス（302リダイレクト）とデータベースの状態で確認
        $response->assertStatus(302);
        $response->assertRedirect(route('reading-plans.index'));

        $this->assertDatabaseHas('reading_plans', [
            'user_id' => $user->id,
            'book_id' => $book->id,
            'target_date' => Carbon::today('Asia/Tokyo')->addDays(5)->format('Y-m-d 00:00:00'),
            'status' => ReadingPlanStatus::InProgress->value,
        ]);
    }

    /** @test */
    public function バリデーションエラー時は登録できずセッションにエラーが残る(): void
    {
        // 1. Arrange
        $user = User::factory()->create();

        // 書籍が選択されていない、期日が今日以前などルールに違反するデータ
        $invalidData = [
            'book_id' => '', // 必須エラー
            'target_date' => Carbon::today('Asia/Tokyo')
                ->subDays(1)->format('Y-m-d'), // 今日以前の日付
        ];

        // 2. Act
        $response = $this->actingAs($user)->post(route('reading-plans.store'), $invalidData);

        // 3. Assert
        // 前の画面へリダイレクトされるか
        $response->assertStatus(302);
        // 各項目にエラーが格納されているか
        $response->assertSessionHasErrors([
            'book_id',
            'target_date',
        ]);
        // データベースに登録されていないか
        $this->assertDatabaseCount('reading_plans', 0);
    }

    /** @test */
    public function 既に同じユーザーで計画されている書籍は新たに読書計画を作成できない(): void
    {
        // 1. Arrange
        $user = User::factory()->create();
        $book = Book::factory()->create();

        // 読書計画を作成
        ReadingPlan::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'target_date' => Carbon::today('Asia/Tokyo')->addDays(3)->format('Y-m-d'),
            'status' => ReadingPlanStatus::InProgress->value,
        ]);

        // 同じユーザー、同じ書籍で再度読書計画作成を試みるデータ
        $duplicateData = [
            'book_id' => $book->id,
            'target_date' => Carbon::today('Asia/Tokyo')->addDays(7)->format('Y-m-d'),
        ];

        // 2. Act
        $response = $this->actingAs($user)->post(route('reading-plans.store'), $duplicateData);

        // 3. Assert
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['book_id']);

        // エラーメッセージが返ってきているかを検証
        $this->assertEquals(
            'この書籍に対する読書計画は既に作成されています。',
            session('errors')->getBag('default')->first('book_id')
        );

        // 最初に用意した読書計画以外の計画が増えていないか確認
        $this->assertDatabaseCount('reading_plans', 1);
    }

    /** @test */
    public function ログインユーザーは自身の読書計画の期日を変更できる(): void
    {
        // 1. Arrange
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $plan = ReadingPlan::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'target_date' => Carbon::today('Asia/Tokyo')->addDays(3)->format('Y-m-d'),
            'status' => ReadingPlanStatus::InProgress->value,
        ]);

        $updateData = [
            'book_id' => $book->id,
            'target_date' => Carbon::today('Asia/Tokyo')->addDays(14)->format('Y-m-d'),
        ];

        // 2. Act
        $response = $this->actingAs($user)->put(route('reading-plans.update', $plan->id), $updateData);

        // 3. Assert
        $response->assertStatus(302);
        $response->assertRedirect(route('reading-plans.index'));

        $this->assertEquals(
            Carbon::today('Asia/Tokyo')->addDays(14)->format('Y-m-d'),
            $plan->refresh()->target_date->format('Y-m-d')
        );
    }

    /** @test */
    public function 他人の読書計画を編集または削除しようとすると認可エラーでブロックされる(): void
    {
        // 1. Arrange
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $book = Book::factory()->create();

        $planOfUserA = ReadingPlan::create([
            'user_id' => $userA->id,
            'book_id' => $book->id,
            'target_date' => Carbon::today('Asia/Tokyo')->format('Y-m-d'),
            'status' => ReadingPlanStatus::InProgress->value,
        ]);

        // 2. Act
        $editResponse = $this->actingAs($userB)->get(route('reading-plans.edit', $planOfUserA->id));
        $deleteResponse = $this->actingAs($userB)->delete(route('reading-plans.destroy', $planOfUserA->id));

        // 3. Assert
        $editResponse->assertStatus(403);
        $deleteResponse->assertStatus(403);
    }

    /** @test */
    public function ログインユーザーは自身の読書計画を削除できる(): void
    {
        // 1. Arrange
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $plan = ReadingPlan::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'target_date' => Carbon::today('Asia/Tokyo')->format('Y-m-d'),
            'status' => ReadingPlanStatus::InProgress->value,
        ]);

        // 2. Act
        $response = $this->actingAs($user)->delete(route('reading-plans.destroy', $plan->id));

        // 3. Assert
        $response->assertStatus(302);
        $response->assertRedirect(route('reading-plans.index'));

        $this->assertDatabaseMissing('reading_plans', [
            'id' => $plan->id,
        ]);
    }
}
