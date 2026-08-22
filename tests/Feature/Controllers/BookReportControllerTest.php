<?php

namespace Tests\Feature\Controllers;

use App\Enums\ReadingPlanStatus;
use App\Models\Book;
use App\Models\Genre;
use App\Models\ReadingPlan;
use App\Models\Review;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookReportControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // ブレード内のViteアセット読み込みによる500クラッシュを確実に防止
        $this->withoutVite();
    }

    /** @test */
    public function 基本統計に正しい数値が表示される(): void
    {
        // 1. Arrange
        // 基本統計(総レビュー数/読了冊数/平均評価を表示するためのデータを準備)
        $user = User::factory()->create();

        $book1 = Book::factory()->create();
        $book2 = Book::factory()->create();
        $book3 = Book::factory()->create();

        // 総レビュー数・平均評価点用のテストデータ（2件のレビュー、平均4.5）
        Review::factory()->create(['user_id' => $user->id, 'book_id' => $book1->id, 'rating' => 5]);
        Review::factory()->create(['user_id' => $user->id, 'book_id' => $book2->id, 'rating' => 4]);

        // 読了冊数用のテストデータ（statusがcompleted、またはcompleted_atがNULL以外）
        ReadingPlan::create([
            'user_id' => $user->id,
            'book_id' => $book1->id,
            'target_date' => Carbon::today('Asia/Tokyo')->addDays(3)->format('Y-m-d'),
            'status' => ReadingPlanStatus::Completed->value,
            'completed_at' => null,
        ]);
        ReadingPlan::create([
            'user_id' => $user->id,
            'book_id' => $book2->id,
            'target_date' => Carbon::today('Asia/Tokyo')->subDays(3)->format('Y-m-d'),
            'status' => ReadingPlanStatus::Expired->value,
            'completed_at' => now(),
        ]);
        // 進行中(読了冊数には含まれない)
        ReadingPlan::create([
            'user_id' => $user->id,
            'book_id' => $book3->id,
            'target_date' => Carbon::today('Asia/Tokyo')->addDays(3)->format('Y-m-d'),
            'status' => ReadingPlanStatus::InProgress->value,
            'completed_at' => null,
        ]);

        // 2. Act (実行)
        $response = $this->actingAs($user)->get(route('reports.index'));

        // 3. Assert (検証)
        $response->assertStatus(200);
        $viewStats = $response->viewData('stats');

        $this->assertEquals(2, $viewStats['summary']['total_reviews']);    // 総レビュー数
        $this->assertEquals(2, $viewStats['summary']['books_read']);       // 読了冊数
        $this->assertEquals(4.5, $viewStats['summary']['average_rating']); // 平均評価点
    }

    /** @test */
    public function 評価分布が星5から星1までbladeの仕様に合わせたインデックスで正しく集計される(): void
    {
        // 1. Arrange
        $user = User::factory()->create();
        $book1 = Book::factory()->create();
        $book2 = Book::factory()->create();
        $book3 = Book::factory()->create();

        // 星5を2件、星3を1件作成（星4, 2, 1は0件にする）
        Review::factory()->create(['user_id' => $user->id, 'book_id' => $book1->id, 'rating' => 5]);
        Review::factory()->create(['user_id' => $user->id, 'book_id' => $book2->id, 'rating' => 5]);
        Review::factory()->create(['user_id' => $user->id, 'book_id' => $book3->id, 'rating' => 3]);

        // 2. Act
        $response = $this->actingAs($user)->get(route('reports.index'));

        // 3. Assert
        $response->assertStatus(200);
        $viewStats = $response->viewData('stats');
        $distribution = $viewStats['rating_distribution'];

        // Blade側の「$index + 1」仕様（キー4が星5、キー2が星3）と件数が一致するか検証
        $this->assertEquals(2, $distribution->get(4)); // 星5つ (Index 4) => 2件
        $this->assertEquals(0, $distribution->get(3)); // 星4つ (Index 3) => 0件
        $this->assertEquals(1, $distribution->get(2)); // 星3つ (Index 2) => 1件
        $this->assertEquals(0, $distribution->get(1)); // 星2つ (Index 1) => 0件
        $this->assertEquals(0, $distribution->get(0)); // 星1つ (Index 0) => 0件
    }

    /** @test */
    public function 高評価書籍top5は4星未満の書籍が件数に関わらず完全に除外される(): void
    {
        // 1. Arrange
        $user = User::factory()->create();

        // 星5の書籍を1冊、星3の書籍を1冊作成（合計2冊のみ）
        $star5Book = Book::factory()->create(['title' => '表示されるべき星5書籍']);
        Review::factory()->create([
            'user_id' => $user->id,
            'book_id' => $star5Book->id,
            'rating' => 5,
        ]);

        $star3Book = Book::factory()->create(['title' => '除外されるべき星3書籍']);
        Review::factory()->create([
            'user_id' => $user->id,
            'book_id' => $star3Book->id,
            'rating' => 3,
        ]);

        // 2. Act
        $response = $this->actingAs($user)->get(route('reports.index'));

        // 3. Assert
        $response->assertStatus(200);
        $viewStats = $response->viewData('stats');
        $topBooks = $viewStats['top_rated_books'];

        // 総件数は1冊（星5のみ）しか表示されないこと
        $this->assertCount(1, $topBooks);

        // 表示されている1冊が間違いなく星5の書籍であること
        $this->assertEquals('表示されるべき星5書籍', $topBooks[0]['title']);
        $this->assertEquals(5, $topBooks[0]['rating']);
        $this->assertNotNull($topBooks[0]['id']);
    }

    /** @test */
    public function 高評価書籍top5は4星以上の書籍が5件を超えた場合に評価の高い順かつ最新レビュー順で最大5件に制限される(): void
    {
        // 1. Arrange
        $user = User::factory()->create();

        // 全て4星以上（対象内）の書籍を「6冊」用意して上限をテスト
        // 評価：星5が3冊、星4が3冊。同じ星の中ではID（作成順）が大きい方が順位が上
        for ($i = 1; $i <= 6; $i++) {
            $book = Book::factory()->create(['title' => "高評価書籍_{$i}"]);
            $rating = $i <= 3 ? 5 : 4;

            Review::factory()->create([
                'user_id' => $user->id,
                'book_id' => $book->id,
                'rating' => $rating,
                'created_at' => now()->addMinutes($i), // 新しい順（セカンダリソート）の検証用
            ]);
        }

        // 2. Act
        $response = $this->actingAs($user)->get(route('reports.index'));

        // 3. Assert
        $viewStats = $response->viewData('stats');
        $topBooks = $viewStats['top_rated_books'];

        // 6冊ある対象データから、最大5件に制限されていること
        $this->assertCount(5, $topBooks);

        // プライマリソート（評価の高い順：星5 → 星4）の検証
        $this->assertEquals(5, $topBooks[0]['rating']);
        $this->assertEquals(5, $topBooks[1]['rating']);
        $this->assertEquals(5, $topBooks[2]['rating']);
        $this->assertEquals(4, $topBooks[3]['rating']);
        $this->assertEquals(4, $topBooks[4]['rating']);
    }

    /** @test */
    public function ジャンル別評価傾向top5にはジャンル別に平均点が高い順に最大5件抽出される(): void
    {
        // 1. Arrange
        $user = User::factory()->create();

        // 6つのジャンルを作成し、それぞれに紐づく6つの異なる書籍を生成する
        for ($i = 1; $i <= 6; $i++) {
            $genre = Genre::factory()->create(['name' => "ジャンル{$i}"]);
            $book = Book::factory()->create();
            $book->genres()->attach($genre->id);

            // 評価点に傾斜をつける（ジャンル1＝星5、ジャンル2＝星4.5、...ジャンル6＝星1）
            $rating = 6 - $i; // 5, 4, 3, 2, 1, 0 になるが評価は最低1とする
            $finalRating = $rating < 1 ? 1 : $rating;

            Review::factory()->create([
                'user_id' => $user->id,
                'book_id' => $book->id,
                'rating' => $finalRating,
            ]);
        }

        // 2. Act
        $response = $this->actingAs($user)->get(route('reports.index'));

        // 3. Assert
        $viewStats = $response->viewData('stats');
        $genreRatings = $viewStats['genre_ratings'];

        // 6つのジャンルのうち、最大5件に制限されていること
        $this->assertCount(5, $genreRatings);

        // 平均評価が高い順（ジャンル1がトップ）にソートされていること
        $this->assertEquals('ジャンル1', $genreRatings[0]['name']);
        $this->assertEquals(5.0, $genreRatings[0]['average_rating']);

        // ジャンル詳細リンク用のIDが含まれていること
        $this->assertNotNull($genreRatings[0]['id']);
    }
}
