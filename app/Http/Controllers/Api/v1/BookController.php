<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\v1\SearchBookRequest;
use App\Http\Requests\StoreBookRequest;
use App\Http\Requests\UpdateBookRequest;
use App\Http\Resources\BookCollection;
use App\Http\Resources\BookResource;
use App\Models\Book;
use Illuminate\Http\JsonResponse;

class BookController extends Controller
{
    public function index(SearchBookRequest $request): BookCollection
    {
        // 1. クエリビルダの初期化（Eager Loading と レビュー集計）
        // ※ withAvgを使うと「reviews_avg_rating」というカラム名が自動生成されます
        $query = Book::with('genres')
            ->withAvg('reviews', 'rating')
            ->withCount('reviews');

        // 2. キーワード検索機能（titleまたはauthorに部分一致）
        if ($request->filled('keyword')) {
            $keyword = $request->input('keyword');
            $query->where(function ($q) use ($keyword) {
                $q->where('books.title', 'like', "%{$keyword}%")
                    ->orWhere('books.author', 'like', "%{$keyword}%");
            });
        }

        // 3. ジャンル抽出機能
        // ジャンルIDでの絞り込み
        if ($request->filled('genre')) {
            $genreId = $request->input('genre');
            $query->whereHas('genres', function ($q) use ($genreId) {
                $q->where('genres.id', $genreId);
            });
        }

        // ジャンル名での絞り込み
        if ($request->filled('genre_name')) {
            $genreName = $request->input('genre_name');
            $query->whereHas('genres', function ($q) use ($genreName) {
                $q->where('genres.name', $genreName);
            });
        }

        // 4. ソート機能（登録日新しい順/古い順/タイトル順/平均評価順）
        $sort = $request->input('sort', 'newest'); // デフォルト: newest
        switch ($sort) {
            case 'oldest':
                $query->orderBy('books.created_at', 'asc');
                break;
            case 'title':
                $query->orderBy('books.title', 'asc');
                break;
            case 'rating':
                // withAvgによって生成された「reviews_avg_rating」を使用
                $query->orderByRaw('reviews_avg_rating IS NULL ASC')
                    ->orderBy('reviews_avg_rating', 'desc');
                break;
            case 'newest':
            default:
                $query->orderBy('books.created_at', 'desc');
                break;
        }

        // 5. ページネーション機能
        $perPage = $request->input('per_page', 10);
        $books = $query->paginate($perPage);

        return new BookCollection($books);
    }

    public function show(int $id): BookResource|JsonResponse
    {
        $book = Book::with(['genres', 'reviews.user'])->find($id);

        if (!$book) {
            return response()->json([
                'error' => '指定された書籍が見つかりません。',
            ], 404);
        }

        return new BookResource($book);
    }

    public function store(StoreBookRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $book = Book::create([
            'user_id' => $request->user()->id,
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

    public function update(UpdateBookRequest $request, $id): JsonResponse
    {
        $book = Book::find($id);
        if (!$book) {
            return response()->json([
                'error' => '指定された書籍が見つかりません。',
            ], 404);
        }

        // 認可チェック
        $this->authorize('update', $book);

        $validated = $request->validated();

        $book->update($validated);

        $book->genres()->sync($validated['genres']);

        $book->load(['genres', 'reviews.user']);

        return (new BookResource($book))
            ->additional(['message' => '書籍情報を更新しました。'])
            ->response()
            ->setStatusCode(200);
    }

    public function destroy($id): JsonResponse
    {
        $book = Book::find($id);
        if (!$book) {
            return response()->json([
                'error' => '指定された書籍が見つかりません。',
            ], 404);
        }

        // 認可チェック
        $this->authorize('update', $book);

        $book->delete();

        return response()->json(null, 204);
    }
}
