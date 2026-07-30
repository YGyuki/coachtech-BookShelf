<?php

namespace Tests\Feature\Controllers;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewControllerTest extends TestCase
{

    use RefreshDatabase;

    /** @test */
    public function レビューを投稿して書籍詳細画面にリダイレクトする(): void
    {
        // 1. Arrange
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $validData = [
            'rating' => 5,
            'comment' => '素晴らしい本でした。',
        ];

        // 2. Act
        $response = $this->actingAs($user)->post(route('reviews.store', $book), $validData);

        // 3. Assert
        $response->assertRedirect(route('books.show', $book));
        $response->assertSessionHas('success', 'レビューを投稿しました。');

        $this->assertDatabaseHas('reviews', [
            'user_id' => $user->id,
            'book_id' => $book->id,
            'rating' => 5,
            'comment' => '素晴らしい本でした。',
        ]);
    }

    /** @test */
    public function バリデーション違反がある場合は投稿できずエラーを返す(): void
    {
        // 1. Arrange
        $user = User::factory()->create();
        $book = Book::factory()->create();

        // 評価が範囲外(6)、コメントが空欄
        $invalidData = [
            'rating' => 6,
            'comment' => '',
        ];

        // 2. Act
        $response = $this->actingAs($user)->post(route('reviews.store', $book), $invalidData);

        // 3. Assert
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['rating', 'comment']);
        $this->assertDatabaseCount('reviews', 0);
    }

    /** @test */
    public function コメントが255文字の場合は正常に投稿できる(): void
    {
        // 1. Arrange
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $validData = [
            'rating' => 4,
            'comment' => str_repeat('あ', 255), // 255文字ぴったり（上限境界値）
        ];

        // 2. Act
        $response = $this->actingAs($user)->post(route('reviews.store', $book), $validData);

        // 3. Assert
        $response->assertRedirect(route('books.show', $book));
        $response->assertSessionHas('success', 'レビューを投稿しました。');

        $this->assertDatabaseHas('reviews', [
            'user_id' => $user->id,
            'book_id' => $book->id,
            'rating' => 4,
            'comment' => str_repeat('あ', 255),
        ]);
    }

    /** @test */
    public function コメントが255文字を超える場合は投稿できない(): void
    {
        // 1. Arrange
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $invalidData = [
            'rating' => 3,
            'comment' => str_repeat('あ', 256), // 256文字で上限オーバー
        ];

        // 2. Act
        $response = $this->actingAs($user)->post(route('reviews.store', $book), $invalidData);

        // 3. Assert
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['comment']);
    }

    /** @test */
    public function 作成者であればレビューを更新して書籍詳細画面にリダイレクトする(): void
    {
        // 1. Arrange
        $user = User::factory()->create();
        $book = Book::factory()->create();
        $review = Review::factory()->create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'rating' => 3,
            'comment' => '元のコメント',
        ]);

        $updateData = [
            'rating' => 4,
            'comment' => '更新されたコメント',
        ];

        // 2. Act
        $response = $this->actingAs($user)->put(route('reviews.update', $review), $updateData);

        // 3. Assert
        $response->assertRedirect(route('books.show', $book->id));
        $response->assertSessionHas('success', 'レビューを更新しました。');

        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'rating' => 4,
            'comment' => '更新されたコメント',
        ]);
    }

    /** @test */
    public function 作成者以外がレビューを更新しようとすると403エラーを返す(): void
    {
        // 1. Arrange
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $book = Book::factory()->create();
        $review = Review::factory()->create([
            'user_id' => $owner->id,
            'book_id' => $book->id,
        ]);

        $updateData = [
            'rating' => 1,
            'comment' => '悪意ある書き換え',
        ];

        // 2. Act
        $response = $this->actingAs($otherUser)->put(route('reviews.update', $review), $updateData);

        // 3. Assert
        $response->assertStatus(403);
    }

    /** @test */
    public function 作成者であればレビューを削除して書籍詳細画面にリダイレクトする(): void
    {
        // 1. Arrange
        $user = User::factory()->create();
        $book = Book::factory()->create();
        $review = Review::factory()->create([
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);

        // 2. Act
        $response = $this->actingAs($user)->delete(route('reviews.destroy', $review));

        // 3. Assert
        $response->assertRedirect(route('books.show', $book->id));
        $response->assertSessionHas('success', 'レビューを削除しました。');

        $this->assertDatabaseMissing('reviews', ['id' => $review->id]);
    }

    /** @test */
    public function 作成者以外がレビューを削除しようとすると403エラーを返す(): void
    {
        // 1. Arrange
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $book = Book::factory()->create();
        $review = Review::factory()->create([
            'user_id' => $owner->id,
            'book_id' => $book->id,
        ]);

        // 2. Act
        $response = $this->actingAs($otherUser)->delete(route('reviews.destroy', $review));

        // 3. Assert
        $response->assertStatus(403);
        $this->assertDatabaseHas('reviews', ['id' => $review->id]);
    }
}
