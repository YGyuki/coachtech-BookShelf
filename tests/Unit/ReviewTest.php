<?php

namespace Tests\Unit;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function レビューは特定のユーザーに所属する(): void
    {
        // 1. Arrange
        $user = User::factory()->create();
        $book = Book::factory()->create();
        $review = Review::factory()->create([
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);

        // 2. Act
        $relatedUser = $review->user;

        // 3. Assert
        $this->assertInstanceOf(User::class, $relatedUser);
        $this->assertEquals($user->id, $relatedUser->id);
    }

    /** @test */
    public function レビューは特定の書籍に所属する(): void
    {
        // 1. Arrange
        $user = User::factory()->create();
        $book = Book::factory()->create();
        $review = Review::factory()->create([
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);

        // 2. Act
        $relatedBook = $review->book;

        // 3. Assert
        $this->assertInstanceOf(Book::class, $relatedBook);
        $this->assertEquals($book->id, $relatedBook->id);
    }

    /** @test */
    public function レビューは複数のユーザーからいいねされる(): void
    {
        // 1. Arrange
        $reviewUser = User::factory()->create();
        $book = Book::factory()->create();
        $review = Review::factory()->create([
            'user_id' => $reviewUser->id,
            'book_id' => $book->id,
        ]);

        // いいねを押す側のユーザーを3人作成
        $likers = User::factory()->count(3)->create();

        // 2. Act
        // 中間テーブル（review_likes）経由で3人のいいねユーザーを紐づける
        $review->likedByUsers()->attach($likers->pluck('id'));

        // 3. Assert
        // レビューに紐づくいいねユーザーの数が「3人」であるか
        $this->assertCount(3, $review->likedByUsers);

        // 最初にいいねしたユーザーが、いいね一覧に正しく含まれているか
        $this->assertTrue($review->likedByUsers->contains($likers->first()));

        // データベースの中間テーブル（review_likes）にレコードが存在するか
        $this->assertDatabaseHas('review_likes', [
            'review_id' => $review->id,
            'user_id' => $likers->first()->id,
        ]);
    }
}
