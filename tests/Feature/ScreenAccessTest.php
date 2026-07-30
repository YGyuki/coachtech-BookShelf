<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\User;
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
}
