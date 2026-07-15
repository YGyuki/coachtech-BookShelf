<?php

use App\Http\Controllers\BookController;
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

    // いいねトグル
    Route::post('/reviews/{review}/like', [ReviewController::class, 'like'])->name('reviews.like');

    // レビューの編集・更新・削除（リソースを部分利用して短く記述）
    Route::resource('reviews', ReviewController::class)->only(['edit', 'update', 'destroy']);
});

// ゲストもアクセス可能なルート（書籍一覧・詳細のみ）
Route::resource('books', BookController::class)->only(['index', 'show']);

