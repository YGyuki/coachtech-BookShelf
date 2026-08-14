<?php

namespace Tests\Feature\Api\v1;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookApiControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function API経由で書籍一覧を取得するとページネーションされたJSONデータとステータス200を返す(): void
    {
        // 1. Arrange
        $user = User::factory()->create(['id' => 1, 'name' => 'テストユーザー']);
        $genre = Genre::factory()->create(['name' => '技術書']);

        // テスト用に書籍を1冊作成し、ジャンルとレビューを紐付ける
        $book = Book::factory()->create([
            'user_id' => $user->id,
            'title' => 'API一覧テスト書籍',
            'author' => 'API著者',
            'isbn' => '1234567890123',
            'published_date' => Carbon::parse('2026-01-01'),
        ]);

        $book->genres()->attach($genre->id);

        // 平均評価の計算ロジック（4点と5点 = 平均4.5点）を検証するため、レビューを2件作成
        Review::factory()->create(['book_id' => $book->id, 'user_id' => $user->id, 'rating' => 4]);
        Review::factory()->create(['book_id' => $book->id, 'user_id' => $user->id, 'rating' => 5]);

        \Log::info(Book::first()->toArray()); // ★DBに実際に保存された生データをログに出力
        // 2. Act
        $response = $this->getJson('/api/v1/books');

        // 3. Assert
        $response->assertStatus(200);

        // BookCollectionのカスタム整形データ構造を厳密に検証
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'title',
                    'author',
                    'isbn',
                    'published_date',
                    'genres_id',
                    'genres_name',
                    'average_rating',
                    'reviews_count',
                ]
            ]
        ]);

        // 実際に返ってきた整形成果（1件目のデータの中身）を詳細にチェック
        $response->assertJsonFragment([
            'id' => $book->id,
            'title' => 'API一覧テスト書籍',
            'author' => 'API著者',
            'isbn' => '1234567890123',
            'published_date' => '2026-01-01',
            'genres_id' => [$genre->id],
            'genres_name' => ['技術書'],
            'average_rating' => 4.5,
            'reviews_count' => 2,
        ]);
    }

    /** @test */
    public function API経由で存在する書籍IDを指定するとその書籍の整形されたJSONデータとステータス200を返す(): void
    {
        // 1. Arrange
        $user = User::factory()->create(['id' => 1, 'name' => 'レビュー投稿者']);
        $genre = Genre::factory()->create(['name' => '小説']);

        $book = Book::factory()->create([
            'user_id' => $user->id,
            'title' => 'API詳細テスト書籍',
            'author' => '小説著者',
            'isbn' => '1111222233334',
            'published_date' => Carbon::parse('2026-05-01'),
            'description' => '詳細説明文',
            'image_url' => 'https://example.com',
        ]);

        $book->genres()->attach($genre->id);

        $review = Review::factory()->create([
            'book_id' => $book->id,
            'user_id' => $user->id,
            'rating' => 5,
            'comment' => '最高の一冊。',
            'created_at' => now(),
        ]);

        // 2. Act
        $response = $this->getJson("/api/v1/books/{$book->id}");

        // 3. Assert
        $response->assertStatus(200);

        // BookResourceのカスタム整形データ構造を厳密に検証
        $response->assertJson([
            'data' => [
                'id' => $book->id,
                'title' => 'API詳細テスト書籍',
                'author' => '小説著者',
                'isbn' => '1111222233334',
                'published_date' => '2026-05-01',
                'description' => '詳細説明文',
                'image_url' => 'https://example.com',
                'genres_id' => [$genre->id],
                'genres_name' => ['小説'],
                'reviews' => [
                    [
                        'user_name' => 'レビュー投稿者',
                        'rating' => 5,
                        'comment' => '最高の一冊。',
                        'created_at' => $review->created_at->format('Y-m-d H:i:s'),
                    ]
                ]
            ]
        ]);
    }

    /** @test */
    public function API経由で存在しない書籍IDを指定するとステータス404とエラーメッセージを返す(): void
    {
        // 1. Arrange
        $nonExistentId = 99999; // 存在しないID

        // 2. Act
        $response = $this->getJson("/api/v1/books/{$nonExistentId}");

        // 3. Assert
        $response->assertStatus(404);
        $response->assertJson([
            'error' => '指定された書籍が見つかりません。'
        ]);
    }

    /** @test */
    public function API経由で認証済みユーザーが書籍を新規登録するとJSON形式データとステータス201を返す(): void
    {
        // 1. Arrange
        $owner = User::factory()->create();
        Sanctum::actingAs($owner);
        $genres = Genre::factory()->count(2)->create();

        // 全てのバリデーション（StoreBookRequest）を確実に通過する正しいデータ
        $validData = [
            'title' => 'APIテスト書籍',
            'author' => 'API著者',
            'isbn' => '1234567890123',
            'published_date' => '2026-01-01',
            'description' => 'APIからの登録テストです。',
            'image_url' => 'https://example.com',
            'genres' => $genres->pluck('id')->toArray(),
        ];

        // 2. Act
        $response = $this->postJson('/api/v1/books', $validData);

        // 3. Assert
        $response->assertStatus(201)
            ->assertJson(['message' => '書籍を登録しました。',]);

        $this->assertDatabaseHas('books', [
            'user_id' => $owner->id,
            'title' => 'APIテスト書籍',
        ]);

        $latestBook = Book::latest('id')->first();
        $this->assertCount(2, $latestBook->genres);
    }

    /** @test */
    public function API経由で未認証ユーザーが書籍を新規登録するとステータス401を返す(): void
    {
        // 1. Arrange
        $book = [
            'title' => '未認証テスト書籍',
            'author' => '未認証著者',
            'genre_id' => 1,
            'published_date' => '2026-01-01',
        ];

        // 2. Act
        $response = $this->postJson('/api/v1/books', $book);

        // 3. Assert
        $response->assertStatus(401)
            ->assertJsonFragment([
                'message' => '認証されていません。有効なトークンを提示してください。'
            ]);
    }

    /** @test */
    public function API経由の登録時にバリデーションエラーが発生するとステータス422とエラーメッセージを返す(): void
    {
        // 1. Arrange
        $owner = User::factory()->create();
        Sanctum::actingAs($owner);

        // 必須項目が空、不正なISBN、不正な日付などの違反データ
        $invalidData = [
            'title' => '', // 必須エラー
            'author' => '', // 必須エラー
            'isbn' => '12345', // 13桁未満
            'published_date' => '2026/01/01', // Y-m-d形式ではない
            'image_url' => 'http://example.com', // httpsではない
            'genres' => [], // 必須エラー
        ];

        // 2. Act
        $response = $this->postJson('/api/v1/books', $invalidData);

        // 3. Assert
        // APIバリデーションエラーを検証
        $response->assertStatus(422);

        // JSONレスポンス内に各項目のエラーキーが含まれているか確認
        $response->assertJsonValidationErrors([
            'title',
            'author',
            'isbn',
            'published_date',
            'image_url',
            'genres'
        ]);

        $this->assertDatabaseCount('books', 0);
    }

    /** @test */
    public function API経由の登録時に既に登録されているISBNコードは登録できない(): void
    {
        // 1. Arrange
        $owner = User::factory()->create();
        Sanctum::actingAs($owner);
        $genre = Genre::factory()->create();

        // 既に存在するISBNの本を登録しておく
        Book::factory()->create(['isbn' => '1234567890123']);

        $duplicateData = [
            'title' => '重複書籍',
            'author' => '著者',
            'isbn' => '1234567890123', // 重複エラー対象
            'published_date' => '2026-01-01',
            'genres' => [$genre->id],
        ];

        // 2. Act
        $response = $this->postJson('/api/v1/books', $duplicateData);

        // 3. Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['isbn']);
        $this->assertDatabaseCount('books', 1);
    }

    /** @test */
    public function API経由の登録時に存在しないジャンルIDが送信された場合は登録できない(): void
    {
        // 1. Arrange
        $owner = User::factory()->create();
        Sanctum::actingAs($owner);

        $invalidGenreData = [
            'title' => 'テスト書籍',
            'author' => 'テスト著者',
            'isbn' => '9876543210987',
            'published_date' => '2026-01-01',
            'genres' => '999', // DBに存在しないジャンルID
        ];

        // 2. Act
        $response = $this->postJson('/api/v1/books', $invalidGenreData);

        // 3. Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['genres']);
        $this->assertDatabaseCount('books', 0);
    }


    /** @test */
    public function API経由で存在する書籍を所有者が更新すると書籍情報を書き換えてJSONデータを返す(): void
    {
        // 1. Arrange
        $owner = User::factory()->create();
        Sanctum::actingAs($owner);
        $book = Book::factory()->create(['user_id' => $owner->id, 'isbn' => '1111222233334']);
        $genres = Genre::factory()->count(2)->create();

        $updateData = [
            'title' => 'API更新後タイトル',
            'author' => 'API更新後著者',
            'isbn' => '1111222233334', // 自身のISBN
            'published_date' => '2026-05-01',
            'genres' => $genres->pluck('id')->toArray(),
        ];

        // 2. Act
        $response = $this->putJson("/api/v1/books/{$book->id}", $updateData);

        // 3. Assert
        $response->assertStatus(200);
        $response->assertJsonFragment(['message' => '書籍情報を更新しました。']);

        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'title' => 'API更新後タイトル',
        ]);
    }

    /** @test */
    public function API経由で他人の書籍を更新しようとすると403エラーを返す(): void
    {
        // 1. Arrange
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        // 所有者ではないユーザーとしてログイン
        Sanctum::actingAs($otherUser);

        $book = Book::factory()->create(['user_id' => $owner->id]);
        $genre = Genre::factory()->create();
        $updateData = [
            'title' => '不正な書き換え',
            'author' => 'テスト著者',
            'genres' => $genre->pluck('id'),
            'published_date' => '2026-05-01',
        ];

        // 2. Act
        $response = $this->putJson("/api/v1/books/{$book->id}", $updateData);

        // 3. Assert
        $response->assertStatus(403)
            ->assertJson([
                'message' => 'この操作を行う権限がありません（書籍の所有者ではありません）。',
            ]);
    }

    /** @test */
    public function API経由の更新時に他人が既に登録しているISBNコードへの変更はエラーになる(): void
    {
        // 1. Arrange
        $user = User::factory()->create(['id' => 1]);
        Sanctum::actingAs($user);
        $genre = Genre::factory()->create();

        $myBook = Book::factory()->create(['user_id' => $user->id, 'isbn' => '1111111111111']);
        Book::factory()->create(['isbn' => '2222222222222']); // 他人の本

        $invalidUpdateData = [
            'title' => '更新タイトル',
            'author' => $myBook->author,
            'isbn' => '2222222222222', // 他人と重複
            'published_date' => $myBook->published_date,
            'genres' => [$genre->id],
        ];

        // 2. Act
        $response = $this->putJson("/api/v1/books/{$myBook->id}", $invalidUpdateData);

        // 3. Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['isbn']);
    }

    /** @test */
    public function API経由で存在しない書籍IDを指定して更新しようとすると404エラーを返す(): void
    {
        // 1. Arrange
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $genre = Genre::factory()->create();
        $nonExistentId = 99999;

        $updateData = [
            'title' => '存在しない本への更新',
            'author' => '著者',
            'isbn' => '9876543210987',
            'published_date' => '2026-01-01',
            'genres' => [$genre->id],
        ];

        // 2. Act
        $response = $this->putJson("/api/v1/books/{$nonExistentId}", $updateData);

        // 3. Assert
        $response->assertStatus(404);
        $response->assertJson(['error' => '指定された書籍が見つかりません。']);
    }

    /** @test */
    public function API経由で存在する書籍を所有者が削除するとステータス204を返す(): void
    {
        // 1. Arrange
        $owner = User::factory()->create();
        Sanctum::actingAs($owner);
        $book = Book::factory()->create(['user_id' => $owner->id]);

        // 2. Act
        $response = $this->deleteJson("/api/v1/books/{$book->id}");

        // 3. Assert
        $response->assertStatus(204);
        $this->assertDatabaseMissing('books', ['id' => $book->id]);
    }

    /** @test */
    public function API経由で他人の書籍を削除しようとすると403エラーを返す(): void
    {
        // 1. Arrange
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        // 所有者ではないユーザーとしてログイン
        Sanctum::actingAs($otherUser);

        $book = Book::factory()->create(['user_id' => $owner->id]);

        // 2. Act
        $response = $this->deleteJson("/api/v1/books/{$book->id}");

        // 3. Assert
        $response->assertStatus(403)
            ->assertJson([
                'message' => 'この操作を行う権限がありません（書籍の所有者ではありません）。',
            ]);

        // データベース内のデータが削除されずに存在することを検証
        $this->assertDatabaseHas('books', [
            'id' => $book->id,
        ]);
    }

    /** @test */
    public function API経由で存在しない書籍IDを指定して削除しようとすると404エラーを返す(): void
    {
        // 1. Arrange
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $nonExistentId = 99999;

        // 2. Act
        $response = $this->deleteJson("/api/v1/books/{$nonExistentId}");

        // 3. Assert
        $response->assertStatus(404);
        $response->assertJson(['error' => '指定された書籍が見つかりません。']);
    }
}
