<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookRequest;
use App\Http\Requests\UpdateBookRequest;
use App\Models\Book;
use App\Models\Genre;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BookController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        // 1. クエリビルダの初期化（reviewsテーブルと結合して平均評価を計算できるよう準備）
        $query = Book::query()
            ->select('books.*')
            ->leftJoin('reviews', 'books.id', '=', 'reviews.book_id')
            ->selectRaw('AVG(reviews.rating) as avg_rating')
            ->groupBy('books.id');

        // 2. キーワード検索機能（titleまたはauthorに部分一致）
        if ($request->filled('keyword')) {
            $keyword = $request->input('keyword');
            $query->where(function ($q) use ($keyword) {
                $q->where('books.title', 'like', "%{$keyword}%")
                    ->orWhere('books.author', 'like', "%{$keyword}%");
            });
        }

        // 3. ジャンル抽出機能
        if ($request->filled('genre')) {
            $genreId = $request->input('genre');
            $query->whereHas('genres', function ($q) use ($genreId) {
                $q->where('genres.id', $genreId);
            });
        }

        // 4. ソート機能（登録日新しい順/登録日古い順/タイトル順/平均評価順）
        $sort = $request->input('sort', 'newest'); // デフォルト:newest
        switch ($sort) {
            case 'oldest':
                $query->orderBy('books.created_at', 'asc');
                break;
            case 'title':
                $query->orderBy('books.title', 'asc');
                break;
            case 'rating':
                // 平均評価（avg_rating）がない場合（NULL）を最後に表示し、あるものは高い順にソート
                $query->orderByRaw('avg_rating IS NULL ASC')
                    ->orderBy('avg_rating', 'desc');
                break;
            case 'newest':
            default:
                $query->orderBy('books.created_at', 'desc');
                break;
        }

        // 5. ページネーション機能（検索条件を保持したまま1ページ10件で遷移）
        $books = $query->paginate(10)->withQueryString();

        // 全ジャンルを取得
        $genres = Genre::all();

        return view('books.index', compact('books', 'genres'));
    }

    public function show(Book $book)
    {
        $book->load(
            'genres',
            'reviews.likedByUsers'
        );
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
            'isbn' => $validated['isbn'],
            'published_date' => $validated['published_date'],
            'description' => $validated['description'] ?? null,
            'image_url' => $validated['image_url'] ?? null,
        ]);

        // 中間テーブルへのジャンル紐付け
        $book->genres()->sync($validated['genres']);

        return redirect()->route('books.index')->with('success', '書籍を登録しました。');
    }

    public function edit(Book $book)
    {
        // 認可チェック
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
            'isbn' => $validated['isbn'],
            'published_date' => $validated['published_date'],
            'description' => $validated['description'] ?? null,
            'image_url' => $validated['image_url'] ?? null,
        ]);

        // ジャンル紐付けの更新
        $book->genres()->sync($validated['genres']);

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

    /**
     * ISBNコードからGoogle Books APIを利用して書籍情報を取得する
     */
    public function fetchByIsbn(string $isbn): JsonResponse
    {
        // 13桁の数字チェック
        if (!preg_match('/^\d{13}$/', $isbn)) {
            return response()->json(['error' => 'ISBNは13桁の数字で入力してください。'], 400);
        }

        try {
            $url = 'https://www.googleapis.com/books/v1/volumes';

            $queryParams = [
                'q' => 'isbn:' . $isbn,
            ];

            // APIキーを指定
            if (config('services.google_books.key')) {
                $queryParams['key'] = config('services.google_books.key');
            }

            // HTTPリクエスト送信
            $response = Http::get($url, $queryParams);

            if ($response->failed()) {
                return response()->json(['error' => 'Google Books APIとの通信に失敗しました。Status: ' . $response->status()], 502);
            }

            $data = $response->json();

            // 該当書籍がない場合
            if (($data['totalItems'] ?? 0) === 0 || empty($data['items'])) {
                return response()->json(['error' => '該当する書籍情報が見つかりませんでした。'], 404);
            }

            // 最初に見つかった書籍の情報を取得
            $volumeInfo = $data['items'][0]['volumeInfo'];
            // Googleから返ってきた書籍の「本物のISBN」を取り出す
            // ※最後1桁をGoogleが自動で修正してデータ取得する場合があるため
            $industryIdentifiers = $volumeInfo['industryIdentifiers'] ?? [];
            $returnedIsbn = '';

            foreach ($industryIdentifiers as $identifier) {
                if ($identifier['type'] === 'ISBN_13') {
                    $returnedIsbn = $identifier['identifier'];
                    break;
                }
            }

            // ユーザーが入力したISBNと、Googleが持っていたISBNが完全一致するかチェック
            if ($returnedIsbn !== $isbn) {
                return response()->json(['error' => '該当する書籍情報が見つかりませんでした。'], 404);
            }

            // フロントエンドに合わせてデータを整形
            return response()->json([
                'title' => $volumeInfo['title'] ?? '',
                'author' => isset($volumeInfo['authors']) ? implode(', ', $volumeInfo['authors']) : '',
                'description' => $volumeInfo['description'] ?? '',
                'image_url' => $volumeInfo['imageLinks']['thumbnail'] ?? '',
                'published_date' => $volumeInfo['publishedDate'] ?? '',
            ]);

        } catch (\Exception $e) {
            Log::error('ISBN検索エラー: ' . $e->getMessage());
            return response()->json(['error' => 'サーバー内部でエラーが発生しました。'], 500);
        }
    }
}
