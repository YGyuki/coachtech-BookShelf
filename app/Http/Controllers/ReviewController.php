<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReviewRequest;
use App\Models\Book;
use App\Models\Review;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;

class ReviewController extends Controller
{
    use AuthorizesRequests;

    public function store(ReviewRequest $request, Book $book)
    {
        $validated = $request->validated();

        Auth::user()->reviews()->create([
            'book_id' => $book->id,
            'rating' => $validated['rating'],
            'comment' => $validated['comment'],
        ]);

        return redirect()->route('books.show', $book)
            ->with('success', 'レビューを投稿しました。');
    }

    /**
     * いいねトグル（追加/解除）処理
     */
    public function like(Review $review)
    {
        // 1. レビューの投稿者IDと、現在ログインしているユーザーのIDを比較
        if ($review->user_id === Auth::id()) {
            // 自分のレビューだった場合は、処理をせずメッセージ付きで直前の画面に戻す
            return back()
                ->with('error', '自分のレビューにはいいねできません。');
        }

        $user = Auth::user();

        $user->likedReviews()->toggle($review->id);

        return back()
            ->with('success', 'いいねを更新しました。');
    }

    /**
     * レビュー編集画面
     */
    public function edit(Review $review)
    {
        $this->authorize('update', $review);

        $review->load('book');
        $title = $review->book->title;

        return view('reviews.edit', compact('review', 'title'));
    }

    /**
     * レビュー更新処理
     */
    public function update(ReviewRequest $request, Review $review)
    {
        $this->authorize('update', $review);

        $validated = $request->validated();

        $review->update([
            'rating' => $validated['rating'],
            'comment' => $validated['comment'],
        ]);

        return redirect()->route('books.show', $review->book_id)
            ->with('success', 'レビューを更新しました。');
    }

    /**
     * レビュー削除処理
     */
    public function destroy(Review $review)
    {
        $this->authorize('delete', $review);

        $bookId = $review->book_id;

        $review->delete();

        return redirect()->route('books.show', $bookId)
            ->with('success', 'レビューを削除しました。');
    }
}
