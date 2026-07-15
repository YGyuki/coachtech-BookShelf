<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Support\Facades\Auth;

class FavoriteController extends Controller
{
    public function index()
    {
        $books = Auth::user()->favoriteBooks()
            ->paginate(10);

        return view('favorites.index', compact('books'));
    }

    /**
     * お気に入りトグル（追加/解除）処理
     */
    public function toggle(Book $book)
    {
        Auth::user()->favoriteBooks()->toggle($book->id);

        return back()->with('success', 'お気に入りを更新しました。');
    }
}
