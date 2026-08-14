<?php

namespace Tests\Feature\Controllers;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RankingControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // テスト実行時はViteのアセットチェックを強制的にパスさせる
        $this->withoutVite();
    }

    /** @test */
    public function 書籍が11冊以上ある場合でも上位10件のみが取得される(): void
    {
        // 1. Arrange
        $user = User::factory()->create();

        // レビューを持つ本を11冊作成する
        $books = Book::factory()->count(11)->create(['user_id' => $user->id]);
        foreach ($books as $book) {
            Review::factory()->create(['book_id' => $book->id, 'rating' => 4, 'user_id' => $user->id]);
        }

        // 2. Act
        $response = $this->get(route('ranking.index'));

        // 3. Assert
        $response->assertStatus(200);
        $rankedBooks = $response->viewData('rankedBooks');

        // 11冊あっても、limit(10)により最大10件しか取得されないことを検証
        $this->assertCount(10, $rankedBooks);
    }

    /** @test */
    public function レビューの平均評価が高い順に並んでいる(): void
    {
        // 1. Arrange
        $user = User::factory()->create();

        // 本A (平均5点)、本B (平均3点)、本C (平均4点) を作成
        $bookA = Book::factory()->create(['user_id' => $user->id]);
        Review::factory()->create(['book_id' => $bookA->id, 'rating' => 5, 'user_id' => $user->id]);

        $bookB = Book::factory()->create(['user_id' => $user->id]);
        Review::factory()->create(['book_id' => $bookB->id, 'rating' => 3, 'user_id' => $user->id]);

        $bookC = Book::factory()->create(['user_id' => $user->id]);
        Review::factory()->create(['book_id' => $bookC->id, 'rating' => 4, 'user_id' => $user->id]);

        // 2. Act
        $response = $this->get(route('ranking.index'));

        // 3. Assert
        $rankedBooks = $response->viewData('rankedBooks');

        $this->assertEquals($bookA->id, $rankedBooks[0]->id, '1位は本Aである必要があります');
        $this->assertEquals($bookC->id, $rankedBooks[1]->id, '2位は本Cである必要があります');
        $this->assertEquals($bookB->id, $rankedBooks[2]->id, '3位は本Bである必要があります');
    }

    /** @test */
    public function 平均評価が同じ場合はレビュー件数が多い方が上になっている(): void
    {
        // 1. Arrange
        $user = User::factory()->create();

        // 本A: レビュー1件、平均4.0点
        $bookA = Book::factory()->create(['user_id' => $user->id]);
        Review::factory()->create(['book_id' => $bookA->id, 'rating' => 4, 'user_id' => $user->id]);

        // 本B: レビュー2件、平均4.0点
        $bookB = Book::factory()->create(['user_id' => $user->id]);
        Review::factory()->create(['book_id' => $bookB->id, 'rating' => 4, 'user_id' => $user->id]);
        Review::factory()->create(['book_id' => $bookB->id, 'rating' => 4, 'user_id' => $user->id]);

        // 2. Act
        $response = $this->get(route('ranking.index'));

        // 3. Assert
        $rankedBooks = $response->viewData('rankedBooks');

        $this->assertEquals($bookB->id, $rankedBooks[0]->id, '件数の多い本Bが上になる必要があります');
        $this->assertEquals($bookA->id, $rankedBooks[1]->id, '件数の少ない本Aが下になる必要があります');
    }
}
