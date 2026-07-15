<?php

use App\Http\Controllers\BookController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\ReviewController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('books.index');
});

Route::middleware(['auth'])->group(function () {
    // 書籍の認証必須のルート（登録・編集・更新・削除）
    Route::resource('books', BookController::class)->except(['index', 'show']);

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
});

// ゲストもアクセス可能なルート（書籍一覧・詳細のみ）
Route::resource('books', BookController::class)->only(['index', 'show']);

