<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookRequest;
use App\Http\Requests\UpdateBookRequest;
use App\Models\Book;
use App\Models\Genre;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB; //あとで消す
use Illuminate\Foundation\Auth\Access\AuthorizesRequests; //必要？

class BookController extends Controller
{
    use AuthorizesRequests;

    public function index()
    {
        $books = Book::with('genres')->paginate(10);
        return view('books.index', compact('books'));
    }

    public function show(Book $book)
    {
        $book->load('genres');
        return view('books.show', compact('book'));
    }

    public function create()
    {
        $genres = Genre::all();
        return view('books.create', compact('genres'));
    }

    public function store(StoreBookRequest $request)
    {
        $validated = $request->validated();

        // 書籍を登録
        $book = Auth::user()->books()->create([
            'title' => $validated['title'],
            'author' => $validated['author'],
            'isbn_13' => $validated['isbn_13'],
            'published_at' => $validated['published_at'],
            'image_url' => $validated['image_url'] ?? null,
        ]);

        // 中間テーブルへのジャンル紐付け
        $book->genres()->sync($validated['genre_ids']);

        return redirect()->route('books.index')->with('success', '書籍を登録しました。');
    }

    public function edit(Book $book)
    {
        $this->authorize('update', $book);

        $genres = Genre::all();
        $book->load('genres');

        return view('books.edit', compact('book', 'genres'));
    }


    public function update(UpdateBookRequest $request, Book $book)
    {
        // 認可チェック
        $this->authorize('update', $book);

        $validated = $request->validated();

        // 書籍情報の更新
        $book->update([
            'title' => $validated['title'],
            'author' => $validated['author'],
            'isbn_13' => $validated['isbn_13'],
            'published_at' => $validated['published_at'],
            'image_url' => $validated['image_url'] ?? null,
        ]);

        // ジャンル紐付けの更新（既存の紐付けを削除して再登録してくれる）
        $book->genres()->sync($validated['genre_ids']);

        return redirect()->route('books.show', $book)->with('success', '書籍情報を更新しました。');
    }

    public function destroy(Book $book)
    {
        // 認可チェック
        $this->authorize('delete', $book);
        // 書籍、関連データ(レビュー、お気に入り)の削除
        $book->delete();

        return redirect()->route('books.index')->with('success', '書籍を削除しました。');
    }
}
