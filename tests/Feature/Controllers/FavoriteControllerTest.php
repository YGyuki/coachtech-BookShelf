<?php

namespace Tests\Feature\Controllers;

use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FavoriteControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function 書籍はお気に入りに追加され直前の画面にリダイレクトする(): void
    {
        // 1. Arrange
        $user = User::factory()->create();
        $book = Book::factory()->create();

        // 2. Act
        // お気に入りに追加
        $response = $this->actingAs($user)->post(route('favorites.toggle', $book));

        // 3. Assert
        $response->assertRedirect();
        $response->assertSessionHas('success', 'お気に入りを更新しました。');

        // 中間テーブル（favorites）にレコードが作成されているか検証
        $this->assertTrue($user->favoriteBooks->contains($book->id));
        $this->assertDatabaseHas('favorites', [
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);
    }

    /** @test */
    public function 登録済みの書籍はお気に入りから解除され直前の画面にリダイレクトする(): void
    {
        // 1. Arrange
        $user = User::factory()->create();
        $book = Book::factory()->create();

        // あらかじめお気に入りに登録しておく
        $user->favoriteBooks()->attach($book->id);

        // 2. Act
        // お気に入りから解除（トグル）
        $response = $this->actingAs($user)->post(route('favorites.toggle', $book));

        // 3. Assert
        $response->assertRedirect();
        $response->assertSessionHas('success', 'お気に入りを更新しました。');

        // 中間テーブルからレコードが削除されているか検証
        $this->assertFalse($user->refresh()->favoriteBooks->contains($book->id));
        $this->assertDatabaseMissing('favorites', [
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);
    }
}
