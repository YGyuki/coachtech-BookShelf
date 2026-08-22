<?php

namespace Tests\Feature;

use App\Enums\ReadingPlanStatus;
use App\Models\Book;
use App\Models\ReadingPlan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScreenAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // ブレード内のViteアセット読み込みによる500クラッシュを確実に防止
        $this->withoutVite();
    }

    /** @test */
    public function ゲストでも書籍一覧画面を表示できる(): void
    {
        // 1. Arrange
        $user = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $user->id]);

        // 2. Act
        // ゲストとして書籍一覧画面にアクセス
        $response = $this->get(route('books.index', $book));

        // 3. Assert
        $response->assertStatus(200);
        $response->assertViewIs('books.index');
    }

    /** @test */
    public function ゲストでも書籍詳細画面を表示できる(): void
    {
        // 1. Arrange
        $user = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $user->id]);

        // 2. Act
        // ゲストとして書籍詳細画面にアクセス
        $response = $this->get(route('books.show', $book));

        // 3. Assert
        $response->assertStatus(200);
        $response->assertViewIs('books.show');
    }

    /** @test */
    public function ゲストでもランキング画面を表示できる(): void
    {
        // 1. Arrange

        // 2. Act
        $response = $this->get(route('ranking.index'));

        // 3. Assert
        $response->assertStatus(200);
        $response->assertViewIs('ranking.index');
    }

    /** @test */
    public function ログイン済みのユーザーは書籍登録画面を表示できる(): void
    {
        // 1. Arrange
        $user = User::factory()->create();

        // 2. Act
        $response = $this->actingAs($user)->get(route('books.create'));

        // 3. Assert
        $response->assertStatus(200);
        $response->assertViewIs('books.create');
    }

    /** @test */
    public function ゲストとして書籍登録画面にアクセスするとログイン画面にリダイレクトされる(): void
    {
        // 1. Arrange (未ログイン状態)

        // 2. Act
        $response = $this->get(route('books.create'));

        // 3. Assert
        $response->assertStatus(302);
        $response->assertRedirect(route('login'));
    }

    /** @test */
    public function 本の作成者であれば書籍編集画面を表示できる(): void
    {
        // 1. Arrange
        $user = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $user->id]); // 自分が作成した本

        // 2. Act
        $response = $this->actingAs($user)->get(route('books.edit', $book));

        // 3. Assert
        $response->assertStatus(200);
        $response->assertViewIs('books.edit');
    }

    /** @test */
    public function ログイン済みのユーザーはジャンル一覧画面を表示できる(): void
    {
        // 1. Arrange
        $user = User::factory()->create();

        // 2. Act
        $response = $this->actingAs($user)->get(route('genres.index'));

        // 3. Assert
        $response->assertStatus(200);
        $response->assertViewIs('genres.index');
    }

    /** @test */
    public function ログイン済みのユーザーはお気に入り一覧画面を表示できる(): void
    {
        // 1. Arrange
        $user = User::factory()->create();

        // 2. Act
        $response = $this->actingAs($user)->get(route('favorites.index'));

        // 3. Assert
        $response->assertStatus(200);
        $response->assertViewIs('favorites.index');
    }

    /** @test */
    public function ゲストとしてお気に入り一覧画面にアクセスするとログイン画面にリダイレクトされる(): void
    {
        // 1. Arrange (未ログイン状態)

        // 2. Act
        $response = $this->get(route('favorites.index'));

        // 3. Assert
        $response->assertStatus(302);
        $response->assertRedirect(route('login'));
    }

    /** @test */
    public function ログインユーザーは自身の読書計画一覧を表示できステータスで絞り込める(): void
    {
        // 1. Arrange
        $user = User::factory()->create();
        $book1 = Book::factory()->create();
        $book2 = Book::factory()->create();

        // 進行中の計画と、完了済みの計画を1件ずつ作成
        $inProgressPlan = ReadingPlan::create([
            'user_id' => $user->id,
            'book_id' => $book1->id,
            'target_date' => Carbon::today('Asia/Tokyo')->addDays(7)->format('Y-m-d'),
            'status' => ReadingPlanStatus::InProgress->value,
        ]);

        $completedPlan = ReadingPlan::create([
            'user_id' => $user->id,
            'book_id' => $book2->id,
            'target_date' => Carbon::today('Asia/Tokyo')->format('Y-m-d'),
            'status' => ReadingPlanStatus::Completed->value,
        ]);

        // 2. Act
        // ステータスを「進行中(in_progress)」に絞り込んでGETリクエストを送信
        $response = $this->actingAs($user)->get(route('reading-plans.index', ['status' => 'in_progress']));

        // 3. Assert
        $response->assertStatus(200);
        // 進行中の書籍タイトルは画面に表示されていることを検証
        $response->assertSee($book1->title);
        // 完了済みの書籍タイトルは画面に表示されていないことを検証
        $response->assertDontSee($book2->title);
    }

    /** @test */
    public function 読書計画の編集画面を表示すると既存の読書計画データがビューに渡される(): void
    {
        // 1. Arrange
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $plan = ReadingPlan::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'target_date' => Carbon::today('Asia/Tokyo')->addDays(10)->format('Y-m-d'),
            'status' => ReadingPlanStatus::InProgress->value,
        ]);

        // 2. Act
        $response = $this->actingAs($user)->get(route('reading-plans.edit', $plan->id));

        // 3. Assert
        $response->assertStatus(200);

        // 渡されたオブジェクトのIDが一致するかチェック
        $viewPlan = $response->viewData('readingPlan');
        $this->assertEquals($plan->id, $viewPlan->id);
    }

    /** @test */
    public function ログイン済みのユーザーはマイ読書レポート画面を表示できる(): void
    {
        // 1. Arrange
        $user = User::factory()->create();

        // 2. Act
        $response = $this->actingAs($user)->get(route('reports.index'));

        // 3. Assert
        $response->assertStatus(200);
        $response->assertViewIs('reports.index');
    }

    /** @test */
    public function 未認証のユーザーはマイ読書レポート画面にアクセスできずログイン画面にリダイレクトされる(): void
    {
        // 1. Arrange
        // 未認証状態（ログインしない）

        // 2. Act
        $response = $this->get(route('reports.index'));

        // 3. Assert
        $response->assertRedirect('/login');
    }
}
