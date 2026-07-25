<?php

namespace Tests\Unit;

use App\Models\Book;
use App\Models\Genre;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenreTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function ジャンルは複数の書籍紐づく()
    {
        // 1. Arrange
        $genre = Genre::factory()->create(['name' => '小説']);
        $books = Book::factory()->count(3)->create();

        // 2. Act
        $genre->books()->attach($books->pluck('id'));

        // 3. Assert
        // ジャンルに紐づく本の数が「3冊」になっているか
        $this->assertCount(3, $genre->books);

        // 紐づけた最初の本が、ジャンルの本一覧に正しく含まれているか
        $this->assertTrue($genre->books->contains($books->first()));

        // 中間テーブルにレコードが正しく書き込まれているか
        $this->assertDatabaseHas('book_genre', [
            'genre_id' => $genre->id,
            'book_id' => $books->first()->id,
        ]);
    }

    /** @test */
    public function ジャンルは書籍が0冊の状態でも登録できる()
    {
        // 1. Arrange
        // 本を1冊も作らず、ジャンルだけを独立して作成する
        $genre = Genre::factory()->create(['name' => '未分類の新規ジャンル']);

        // 2. Act
        $relatedBooks = $genre->books;

        // 3. Assert
        // 紐づく書籍が0冊であることを確認する
        $this->assertCount(0, $relatedBooks);
        $this->assertTrue($relatedBooks->isEmpty());
    }
}
