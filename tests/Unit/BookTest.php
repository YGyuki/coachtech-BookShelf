<?php

namespace Tests\Unit;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function 書籍は特定のユーザーに所属する()
    {
        // 1. Arrange
        $user = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $user->id]);

        // 2. Act
        $relatedUser = $book->user;

        // 3. Assert
        $this->assertInstanceOf(User::class, $relatedUser);
        $this->assertEquals($user->id, $relatedUser->id);
    }

    /** @test */
    public function 書籍は複数のジャンルに紐づく()
    {
        // 1. Arrange
        $book = Book::factory()->create();
        $genres = Genre::factory()->count(2)->create();

        // 2. Act
        $book->genres()->attach($genres->pluck('id'));

        // 3. Assert
        $this->assertCount(2, $book->genres);
        $this->assertTrue($book->genres->contains($genres->first()));
    }

    /** @test */
    public function 書籍は複数のレビューを持つ()
    {
        // 1. Arrange
        $book = Book::factory()->create();
        $user = User::factory()->create();

        // 2. Act
        Review::factory()->count(3)->create([
            'book_id' => $book->id,
            'user_id' => $user->id,
        ]);

        // 3. Assert
        $this->assertCount(3, $book->reviews);
        $this->assertInstanceOf(Review::class, $book->reviews->first());
    }

    /** @test */
    public function 書籍は複数のユーザーからお気に入り登録される()
    {
        // 1. Arrange
        $book = Book::factory()->create();
        $users = User::factory()->count(2)->create();

        // 2. Act
        $book->favoritedUsers()->attach($users->pluck('id'));

        // 3. Assert
        $this->assertCount(2, $book->favoritedUsers);
        $this->assertTrue($book->favoritedUsers->contains($users->first()));
    }
}
