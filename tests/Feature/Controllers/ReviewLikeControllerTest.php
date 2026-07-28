<?php

namespace Tests\Feature\Controllers;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewLikeControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function 他人のレビューに対していいねができる(): void
    {
        // 1. Arrange
        $loginUser = User::factory()->create();
        $reviewOwner = User::factory()->create();
        $book = Book::factory()->create();

        // 他人が書いたレビューを用意
        $review = Review::factory()->create([
            'user_id' => $reviewOwner->id,
            'book_id' => $book->id,
        ]);

        // 2. Act
        // いいねを登録
        $response = $this->actingAs($loginUser)->post(route('reviews.like', $review));

        // 3. Assert
        $response->assertRedirect();
        $response->assertSessionHas('success', 'いいねを更新しました。');

        // ログインユーザーのいいね一覧に対象レビューが含まれているか検証
        $this->assertTrue($loginUser->likedReviews->contains($review->id));

        // 中間テーブル（review_likes）にレコードが作成されているか検証
        $this->assertDatabaseHas('review_likes', [
            'user_id' => $loginUser->id,
            'review_id' => $review->id,
        ]);
    }

    /** @test */
    public function 他人のレビューに対して登録済みのいいねが解除される(): void
    {
        // 1. Arrange
        $loginUser = User::factory()->create();
        $reviewOwner = User::factory()->create();
        $book = Book::factory()->create();

        $review = Review::factory()->create([
            'user_id' => $reviewOwner->id,
            'book_id' => $book->id,
        ]);

        // あらかじめいいねに登録しておく
        $loginUser->likedReviews()->attach($review->id);

        // 2. Act
        // いいねを解除（トグル）
        $response = $this->actingAs($loginUser)->post(route('reviews.like', $review));

        // 3. Assert
        $response->assertRedirect();
        $response->assertSessionHas('success', 'いいねを更新しました。');

        // ログインユーザーのいいね一覧から対象レビューが消えているか検証
        $this->assertFalse($loginUser->refresh()->likedReviews->contains($review->id));

        // 中間テーブル（review_likes）からレコードが消えているか検証
        $this->assertDatabaseMissing('review_likes', [
            'user_id' => $loginUser->id,
            'review_id' => $review->id,
        ]);
    }

    /** @test */
    public function 自分のレビューにいいねしようとするとエラーメッセージを返しトグルされない(): void
    {
        // 1. Arrange
        $user = User::factory()->create();
        $book = Book::factory()->create();

        // 自分が書いたレビューを用意
        $myReview = Review::factory()->create([
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);

        // 2. Act
        $response = $this->actingAs($user)->post(route('reviews.like', $myReview));

        // 3. Assert
        $response->assertRedirect();
        $response->assertSessionHas('error', '自分のレビューにはいいねできません。');
        $this->assertFalse($user->likedReviews->contains($myReview->id));
    }
}
