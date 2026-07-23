<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookCollection;
use App\Http\Resources\BookResource;
use App\Http\Requests\StoreBookRequest;
use App\Http\Requests\UpdateBookRequest;
use App\Models\Book;

class BookController extends Controller
{
    public function index()
    {
        $books = Book::with('genres')
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->paginate(10);

        return new BookCollection($books);
    }

    public function show($id)
    {
        $book = Book::with(['genres', 'reviews.user'])->find($id);

        if (!$book) {
            return response()->json([
                'error' => '指定された書籍が見つかりません。'
            ], 404);
        }

        return new BookResource($book);
    }

    public function store(StoreBookRequest $request)
    {
        //Sanctum導入前の為、仮設定
        $userId = auth()->id() ?? 1;

        $validated = $request->validated();

        $book = Book::create([
            'user_id' => $userId,
            'title' => $validated['title'],
            'author' => $validated['author'],
            'isbn' => $validated['isbn'],
            'published_date' => $validated['published_date'],
            'description' => $validated['description'] ?? null,
            'image_url' => $validated['image_url'] ?? null,
        ]);

        $book->genres()->sync($validated['genres']);

        return (new BookResource($book->load('genres')))
            ->additional(['message' => '書籍を登録しました。'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateBookRequest $request, $id)
    {
        $book = Book::find($id);
        if (!$book) {
            return response()->json([
                'error' => '指定された書籍が見つかりません。'
            ], 404);
        }

        $validated = $request->validated();

        $book->update($validated);

        $book->genres()->sync($validated['genres']);

        $book->load(['genres', 'reviews.user']);

        return (new BookResource($book))
            ->additional(['message' => '書籍情報を更新しました。']);
    }

    public function destroy($id)
    {
        $book = Book::find($id);
        if (!$book) {
            return response()->json([
                'error' => '指定された書籍が見つかりません。'
            ], 404);
        }

        $book->delete();

        return response()->json(null, 204);
    }
}
