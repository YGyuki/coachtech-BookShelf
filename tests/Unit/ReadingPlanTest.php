<?php

namespace Tests\Unit;

use App\Models\Book;
use App\Models\ReadingPlan;
use App\Models\User;
use Carbon\Carbon;
use App\Enums\ReadingPlanStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReadingPlanTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function 読書計画は特定のユーザーに所属する(): void
    {
        // 1. Arrange
        $user = User::factory()->create();
        $book = Book::factory()->create();
        $readingPlan = ReadingPlan::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'target_date' => Carbon::today('Asia/Tokyo')->addDays(5)->format('Y-m-d'),
            'status' => 'in_progress'
        ]);

        // 2. Act
        $relatedUser = $readingPlan->user;

        // 3. Assert
        $this->assertInstanceOf(User::class, $relatedUser);
        $this->assertEquals($user->id, $relatedUser->id);
    }

    /** @test */
    public function 読書計画は特定の書籍に所属する(): void
    {
        // 1. Arrange
        $user = User::factory()->create();
        $book = Book::factory()->create();
        $readingPlan = ReadingPlan::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'target_date' => Carbon::today('Asia/Tokyo')->addDays(5)->format('Y-m-d'),
            'status' => 'in_progress'
        ]);

        // 2. Act
        $relatedBook = $readingPlan->book;

        // 3. Assert
        $this->assertInstanceOf(Book::class, $relatedBook);
        $this->assertEquals($book->id, $relatedBook->id);
    }

    /** @test */
    public function 新規作成時にstatusカラムにデフォルト値として進行中がセットされる(): void
    {
        // 1. Arrange & 2. Act
        $user = User::factory()->create();
        $book = Book::factory()->create();

        // status を明示的に指定せずにインスタンスを作成
        $plan = ReadingPlan::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'target_date' => Carbon::today('Asia/Tokyo')->addDays(5)->format('Y-m-d'),
        ]);

        // 3. Assert
        // ① モデルの属性デフォルト値（attributes）として InProgress になっているか検証
        $this->assertInstanceOf(ReadingPlanStatus::class, $plan->status);
        // ② キャスト定義によって文字列ではなく Enum オブジェクトとして取得できているか検証
        $this->assertEquals(ReadingPlanStatus::InProgress, $plan->status);
    }

    /** @test */
    public function scopeOfStatusは指定されたステータスでデータを正しく絞り込む(): void
    {
        // 1. Arrange
        $user = User::factory()->create();
        $book1 = Book::factory()->create();
        $book2 = Book::factory()->create();

        // 進行中(in_progress)の計画
        $inProgressPlan = ReadingPlan::create([
            'user_id' => $user->id,
            'book_id' => $book1->id,
            'target_date' => Carbon::today('Asia/Tokyo')->addDays(5)->format('Y-m-d'),
            'status' => ReadingPlanStatus::InProgress->value,
        ]);

        // 完了(completed)の計画
        $completedPlan = ReadingPlan::create([
            'user_id' => $user->id,
            'book_id' => $book2->id,
            'target_date' => Carbon::today('Asia/Tokyo')->addDays(5)->format('Y-m-d'),
            'status' => ReadingPlanStatus::Completed->value,
        ]);

        // 2. Act & 3. Assert 【パターンA：引数に文字列を渡して絞り込む場合】
        $filteredPlans = ReadingPlan::ofStatus('completed')->get();
        $this->assertCount(1, $filteredPlans);
        $this->assertEquals($completedPlan->id, $filteredPlans->first()->id);

        // 2. Act & 3. Assert 【パターンB：引数が空（nullまたは空文字）の時、絞り込みをスキップする場合】
        $allPlans = ReadingPlan::ofStatus(null)->get();
        $this->assertCount(2, $allPlans); // 絞り込まれず2件とも取得できる
    }
}
