<?php

use App\Http\Controllers\BookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

// Route::get('/books/create', [BookController::class, 'create'])->name('books.create');
// Route::get('/books/create', [BookController::class, 'create'])->name('books.create');
// Route::post('/books', [BookController::class, 'store'])->name('books.store');
// Route::get('/books/{book}/edit', [BookController::class, 'edit'])->name('books.edit');
// Route::put('/books/{book}', [BookController::class, 'update'])->name('books.update');
// Route::delete('/books/{book}', [BookController::class, 'destroy'])->name('books.destroy');


// 認証必須のルート（書籍登録・編集・削除）
Route::middleware(['auth'])->group(function () {
    Route::get('/books/create', [BookController::class, 'create'])->name('books.create');
    Route::resource('books', BookController::class)->except(['index', 'show']);
});

// ゲストもアクセス可能なルート（書籍一覧・詳細）
Route::resource('books', BookController::class)->only(['index', 'show']);

