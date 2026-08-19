<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;

class BookReportController extends Controller
{
    public function index()
    {
        // ログイン中のユーザーを取得
        $user = Auth::user();

        // レビュー、書籍、ジャンルを一括取得
        $reviews = $user->reviews()->with('book.genres')->get();

        // 読了冊数（statusがcompleted、またはcompleted_atがNULL以外）
        $completedPlans = $user->readingPlans()
            ->where(function ($query) {
                $query->where('status', 'completed')
                    ->orWhereNotNull('completed_at');
            })
            ->get();

        // -------------------------------------------------------------------------
        // Bladeに渡す配列構造 `$stats` を作成
        // -------------------------------------------------------------------------

        $stats = [];

        // -------------------------------------------------------------------------

        // 1. 基本サマリー（総レビュー数、読了冊数、平均評価）（キー名: summary）
        $stats['summary'] = [
            'total_reviews' => $reviews->count(),
            'books_read' => $completedPlans->unique('book_id')->count(),
            'average_rating' => $reviews->avg('rating') ?: 0.0,
        ];

        // -------------------------------------------------------------------------

        // 2. 評価分布（キー名: rating_distribution）
        // DBから取得した各星の件数
        $groupedRatings = $reviews->groupBy('rating')->map->count();

        // 空のコレクションを作成
        $ratingDistribution = collect();

        // Bladeの「$index + 1」という仕様に合わせるため、
        // ☆5つ（i=5）のデータは キー「4」 として、上から順（5→1）にコレクションに格納
        for ($i = 5; $i >= 1; $i--) {
            $bladeKey = $i - 1; // 4, 3, 2, 1, 0
            $ratingDistribution->put($bladeKey, $groupedRatings->get($i, 0));
        }

        // コレクションをそのままセット
        $stats['rating_distribution'] = $ratingDistribution;

        // -------------------------------------------------------------------------

        // 3. 高評価書籍TOP5（キー名: top_rated_books）
        // 4星以上のレビューをフィルタリング・ソートし、配列形式に変換
        $stats['top_rated_books'] = $reviews->filter(function ($review) {
            return $review->rating >= 4;
        })
            ->sortByDesc('created_at') // 同じ評価なら新しい順
            ->sortByDesc('rating')     // 評価の高い順
            ->take(5)
            ->map(function ($review) {
                return [
                    'id' => $review->book->id ?? null,
                    'title' => $review->book->title ?? 'タイトル不明',
                    'author' => $review->book->author ?? '著者不明',
                    'rating' => $review->rating,
                ];
            })
            ->values() // インデックスを0からの連番にリセット
            ->toArray();

        // -------------------------------------------------------------------------

        // 4. ジャンル別評価傾向TOP5（キー名: genre_ratings）
        // 空のコレクションを作成
        $genreStats = collect();

        foreach ($reviews as $review) {
            if (! $review->book) {
                continue;
            }
            // 書籍に紐づく全ジャンルをループ ジャンルごとに箱分けし、評価点を追加していく
            foreach ($review->book->genres as $genre) {
                if (! $genreStats->has($genre->id)) {
                    $genreStats->put($genre->id, [
                        'genre' => $genre,
                        'ratings' => collect(),
                    ]);
                }
                $genreStats->get($genre->id)['ratings']->push($review->rating);
            }
        }

        // ジャンル別レビュー件数・平均点の算出
        $stats['genre_ratings'] = $genreStats->map(function ($item) {
            $ratings = $item['ratings'];

            return [
                'id' => $item['genre']->id,
                'name' => $item['genre']->name,
                'count' => $ratings->count(),
                'average_rating' => round($ratings->avg(), 1) ?: 0.0,
            ];
        })
            ->sortByDesc('count')          // 同じ平均点なら件数が多い順
            ->sortByDesc('average_rating') // 平均点が高い順
            ->take(5)
            ->values()
            ->toArray();

        // -------------------------------------------------------------------------

        // `$stats` 配列をビューに渡す
        return view('reports.index', compact('stats'));
    }
}
