<?php

namespace Tests\Unit;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function ユーザーは複数の書籍を登録できる(): void
    {
        // 1. Arrange
        $user = User::factory()->create();

        // 2. Act
        // ユーザーに紐づく本を3冊作成する
        Book::factory()->count(3)->create(['user_id' => $user->id]);

        // 3. Assert
        // ユーザーが登録した本の数が「3冊」になっているか
        $this->assertCount(3, $user->books);
        $this->assertInstanceOf(Book::class, $user->books->first());
    }

    /** @test */
    public function ユーザーは複数のレビューを書くことができる(): void
    {
        // 1. Arrange
        $user = User::factory()->create();
        $book = Book::factory()->create();

        // 2. Act
        // ユーザーが書いたレビューを2件作成する
        Review::factory()->count(2)->create([
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);

        // 3. Assert
        // ユーザーが書いたレビューの数が「2件」になっているか
        $this->assertCount(2, $user->reviews);
        $this->assertInstanceOf(Review::class, $user->reviews->first());
    }

    /** @test */
    public function ユーザーは複数の書籍をお気に入りに登録できる(): void
    {
        // 1. Arrange
        $user = User::factory()->create();
        $books = Book::factory()->count(2)->create();

        // 2. Act
        // 中間テーブル（favorites）を介して2冊の本をお気に入り登録する
        $user->favoriteBooks()->attach($books->pluck('id'));

        // 3. Assert
        // お気に入り登録した本の数が「2冊」になっているか
        $this->assertCount(2, $user->favoriteBooks);
        // 最初に登録した本がお気に入り一覧に含まれているか
        $this->assertTrue($user->favoriteBooks->contains($books->first()));
        // データベースの中間テーブルにレコードが存在するか
        $this->assertDatabaseHas('favorites', [
            'user_id' => $user->id,
            'book_id' => $books->first()->id,
        ]);
    }

    /** @test */
    public function ユーザーは複数のレビューに対していいねができる(): void
    {
        // 1. Arrange
        $user = User::factory()->create();
        $author = User::factory()->create();
        $book = Book::factory()->create();

        // いいね対象となるレビューを2件作成
        $reviews = Review::factory()->count(2)->create([
            'user_id' => $author->id,
            'book_id' => $book->id,
        ]);

        // 2. Act
        // 中間テーブル（review_likes）を介して2件のレビューにいいねする
        $user->likedReviews()->attach($reviews->pluck('id'));

        // 3. Assert
        // いいねしたレビューの数が「2件」になっているか
        $this->assertCount(2, $user->likedReviews);
        // 最初にいいねしたレビューが一覧に含まれているか
        $this->assertTrue($user->likedReviews->contains($reviews->first()));
        // データベースの中間テーブルにレコードが存在するか
        $this->assertDatabaseHas('review_likes', [
            'user_id' => $user->id,
            'review_id' => $reviews->first()->id,
        ]);
    }
}
