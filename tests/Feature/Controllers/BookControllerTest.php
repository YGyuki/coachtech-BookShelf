<?php

namespace Tests\Feature\Controllers;

use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function 書籍を新規登録し、ジャンルを同期してリダイレクトする(): void
    {
        // 1. Arrange
        $user = User::factory()->create();
        $genres = Genre::factory()->count(2)->create();

        $validData = [
            'title' => '新規登録の書籍',
            'author' => 'テスト著者',
            'isbn' => '1234567890123',
            'published_date' => '2026-01-01',
            'description' => '本の詳細説明が入ります。',
            'image_url' => 'https://example.com',
            'genres' => $genres->pluck('id')->toArray(),
        ];

        // 2. Act
        $response = $this->actingAs($user)->post(route('books.store'), $validData);

        // 3. Assert
        $response->assertRedirect(route('books.index'));
        $response->assertSessionHas('success', '書籍を登録しました。');

        $this->assertDatabaseHas('books', [
            'user_id' => $user->id,
            'title' => '新規登録の書籍',
        ]);

        $latestBook = Book::latest('id')->first();
        $this->assertCount(2, $latestBook->genres);
    }

    /** @test */
    public function バリデーションエラー時は登録できずセッションにエラーが残る(): void
    {
        // 1. Arrange
        $user = User::factory()->create();

        // タイトルや著者、ジャンルが空、不正なISBNなど、ルールに違反するデータ
        $invalidData = [
            'title' => '', // 必須エラー
            'author' => '', // 必須エラー
            'isbn' => '12345', // 13桁未満エラー
            'published_date' => '2026/01/01', // Y-m-d形式ではないエラー
            'image_url' => 'http://example.com', // httpsではないエラー
            'genres' => [], // ジャンル必須エラー
        ];

        // 2. Act
        $response = $this->actingAs($user)->post(route('books.store'), $invalidData);

        // 3. Assert
        // 前の画面へリダイレクトされるか
        $response->assertStatus(302);
        // 各項目にエラーが格納されているか
        $response->assertSessionHasErrors([
            'title',
            'author',
            'isbn',
            'published_date',
            'image_url',
            'genres'
        ]);
        // データベースに登録されていないか
        $this->assertDatabaseCount('books', 0);
    }

    /** @test */
    public function 既に登録されているISBNコードは登録できない(): void
    {
        // 1. Arrange
        $user = User::factory()->create();
        $genre = Genre::factory()->create();

        // 既に存在する本を登録（同じISBNを持たせる）
        $existingBook = Book::factory()->create(['isbn' => '1234567890123']);

        $duplicateData = [
            'title' => '重複書籍名',
            'author' => '重複著者',
            'isbn' => '1234567890123', // 重複エラー対象
            'published_date' => '2026-01-01',
            'genres' => [$genre->id],
        ];

        // 2. Act
        $response = $this->actingAs($user)->post(route('books.store'), $duplicateData);

        // 3. Assert
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['isbn']);

        // 最初に用意した1冊以外の本が増えていないか確認
        $this->assertDatabaseCount('books', 1);
    }

    /** @test */
    public function 存在しないジャンルIDが送信された場合は登録できない(): void
    {
        // 1. Arrange
        $user = User::factory()->create();

        $invalidGenreData = [
            'title' => 'テスト書籍',
            'author' => 'テスト著者',
            'isbn' => '9876543210987',
            'published_date' => '2026-01-01',
            'genres' => 99, // DBに存在しないジャンルID
        ];

        // 2. Act
        $response = $this->actingAs($user)->post(route('books.store'), $invalidGenreData);

        // 3. Assert
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['genres']); // 最初のジャンル要素にエラーが入る
        $this->assertDatabaseCount('books', 0);
    }

    /** @test */
    public function 作成者であれば書籍情報を更新しジャンルを同期する(): void
    {
        // 1. Arrange
        $user = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $user->id]);
        $genres = Genre::factory()->count(2)->create();

        $updateData = [
            'title' => '更新後の書籍タイトル',
            'author' => '更新後の著者',
            'isbn' => '9876543210987',
            'published_date' => '2026-05-01',
            'description' => '更新された説明文。',
            'image_url' => 'https://example.com',
            'genres' => $genres->pluck('id')->toArray(),
        ];

        // 2. Act
        $response = $this->actingAs($user)->put(route('books.update', $book), $updateData);

        // 3. Assert
        $response->assertRedirect(route('books.show', $book));
        $response->assertSessionHas('success', '書籍情報を更新しました。');

        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'title' => '更新後の書籍タイトル',
        ]);

        $this->assertCount(2, $book->refresh()->genres);
    }

    /** @test */
    public function 作成者以外が更新しようとすると403エラーを返す(): void
    {
        // 1. Arrange
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $owner->id]); // 所有者はowner
        $genre = Genre::factory()->create();

        $updateData = [
            'title' => '不正な更新試み',
            'author' => '悪意あるユーザー',
            'isbn' => '9876543210987',
            'published_date' => '2026-05-01',
            'genres' => [$genre->id],
        ];

        // 2. Act
        // otherUser（作成者以外）としてリクエストを送信
        $response = $this->actingAs($otherUser)->put(route('books.update', $book), $updateData);

        // 3. Assert
        $response->assertStatus(403); // 認可エラーになること
    }

    /** @test */
    public function 自分自身のISBNコードであれば重複エラーにならず更新できる(): void
    {
        // 1. Arrange
        $user = User::factory()->create();
        $genre = Genre::factory()->create();
        // 既存のISBNを持つ本を作成
        $book = Book::factory()->create([
            'user_id' => $user->id,
            'isbn' => '1234567890123',
            'title' => '元のタイトル',
        ]);

        // ISBNは変えずに、タイトルだけ変更するデータを用意
        $updateData = [
            'title' => 'タイトルだけ更新',
            'author' => $book->author,
            'isbn' => '1234567890123',
            'published_date' => $book->published_date,
            'genres' => [$genre->id],
        ];

        // 2. Act
        $response = $this->actingAs($user)->put(route('books.update', $book), $updateData);

        // 3. Assert
        $response->assertRedirect(route('books.show', $book));
        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'title' => 'タイトルだけ更新',
        ]);
    }

    /** @test */
    public function 登録しているISBNコードへの変更はバリデーションエラーになる(): void
    {
        // 1. Arrange
        $user = User::factory()->create();
        $genre = Genre::factory()->create();

        // 2冊の本を作成
        $myBook = Book::factory()->create(['user_id' => $user->id, 'isbn' => '1111111111111']);
        $otherBook = Book::factory()->create(['isbn' => '2222222222222']); // 他人の本

        // myBookのISBNを、otherBookのISBN（重複）に書き換えようとするデータ
        $invalidUpdateData = [
            'title' => '更新タイトル',
            'author' => $myBook->author,
            'isbn' => '2222222222222', // 重複エラー対象
            'published_date' => $myBook->published_date,
            'genres' => [$genre->id],
        ];

        // 2. Act
        $response = $this->actingAs($user)->put(route('books.update', $myBook), $invalidUpdateData);

        // 3. Assert
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['isbn']);

        // DBが書き換わっていないことを確認
        $this->assertDatabaseHas('books', [
            'id' => $myBook->id,
            'isbn' => '1111111111111',
        ]);
    }

    /** @test */
    public function 作成者であれば書籍を削除できる(): void
    {
        // 1. Arrange
        $user = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $user->id]);

        // 2. Act
        $response = $this->actingAs($user)->delete(route('books.destroy', $book));

        // 3. Assert
        $response->assertRedirect(route('books.index'));
        $response->assertSessionHas('success', '書籍を削除しました。');

        $this->assertDatabaseMissing('books', ['id' => $book->id]);
    }

    /** @test */
    public function 作成者以外が削除しようとすると403エラーを返す(): void
    {
        // 1. Arrange
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $owner->id]);

        // 2. Act
        $response = $this->actingAs($otherUser)->delete(route('books.destroy', $book));

        // 3. Assert
        $response->assertStatus(403);
        $this->assertDatabaseHas('books', ['id' => $book->id]); // DBから消えていないこと
    }
}
