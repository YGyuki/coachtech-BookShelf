<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class BookCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            // $this->collection でページネーションされた Book のリストをループ処理します
            'data' => $this->collection->map(function ($book) {
                return [
                    'id' => $book->id,
                    'title' => $book->title,
                    'author' => $book->author,
                    'isbn' => $book->isbn,
                    'genres' => $book->genres->pluck('name'),
                    'average_rating' => round($book->reviews_avg_rating ?? 0, 1),
                    'reviews_count' => $book->reviews_count ?? 0,
                ];
            }),
        ];
    }
}
