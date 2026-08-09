<?php

use App\Http\Controllers\BookController;
use App\Http\Controllers\BookReportController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\GenreController;
use App\Http\Controllers\RankingController;
use App\Http\Controllers\ReadingPlanController;
use App\Http\Controllers\ReviewController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('books.index');
});

Route::middleware(['auth'])->group(function () {
    // 書籍の認証必須のルート（登録・編集・更新・削除）
    Route::resource('books', BookController::class)->except(['index', 'show']);

    // ★ISBN検索API（登録・編集のJavaScriptがここにアクセスする）
    Route::get('/books/isbn/{isbn}', [BookController::class, 'fetchByIsbn'])->name('books.isbn.fetch');

    // ジャンルの登録・編集・更新・削除
    Route::resource('genres', GenreController::class);

    // レビュー投稿
    Route::post('/books/{book}/reviews', [ReviewController::class, 'store'])->name('reviews.store');

    // いいね登録・解除トグル処理
    Route::post('/reviews/{review}/like', [ReviewController::class, 'like'])->name('reviews.like');

    // レビューの編集・更新・削除
    Route::resource('reviews', ReviewController::class)->only(['edit', 'update', 'destroy']);

    // お気に入り一覧画面
    Route::get('favorites', [FavoriteController::class, 'index'])->name('favorites.index');

    // お気に入り登録・解除トグル処理
    Route::post('/books/{book}/favorite', [FavoriteController::class, 'toggle'])->name('favorites.toggle');

    // 認証済みユーザー向けの読書レポートルーティング
    Route::get('/reports', [BookReportController::class, 'index'])->name('reports.index');

    // 読書計画(読了)
    Route::post('reading-plans/{reading_plan}/complete', [ReadingPlanController::class, 'complete'])
        ->name('reading-plans.complete');

    // 読書計画(一覧・登録・編集・更新・削除)
    Route::resource('reading-plans', ReadingPlanController::class)->except(['show'])->names([
        'index' => 'reading-plans.index',
        'create' => 'reading-plans.create',
        'store' => 'reading-plans.store',
        'edit' => 'reading-plans.edit',
        'update' => 'reading-plans.update',
        'destroy' => 'reading-plans.destroy',
    ]);
});

// ランキング画面(ゲスト閲覧可)
Route::get('ranking', [RankingController::class, 'index'])->name('ranking.index');

// 書籍一覧・詳細(ゲスト閲覧可)
Route::resource('books', BookController::class)->only(['index', 'show']);