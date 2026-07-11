<?php

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

Route::get('/', function () {
    return view('books.index');
});

//仮ルート設定
Route::middleware('auth')->group(function () {
    Route::get('/books', fn() => '書籍一覧(準備中)')->name('books.index');
});
