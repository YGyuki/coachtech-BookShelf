<?php

namespace App\Http\Requests;

use App\Models\Book;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class UpdateBookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation()
    {//APIでバリデーションの前に404エラーを確認
        // ルートからIDを取得
        $bookId = $this->route('book');

        // 存在しない場合は404を返して処理を中断
        if (!Book::where('id', $bookId)->exists()) {
            throw new HttpResponseException(
                response()->json(['error' => '指定された書籍が見つかりません。'], 404)
            );
        }
    }

    public function rules(): array
    {
        $bookId = $this->route('book')?->id ?? $this->route('book');

        return [
            'title' => 'required|string',
            'author' => 'required|string',
            'isbn' => [
                'required',
                'digits:13',
                Rule::unique('books', 'isbn')->ignore($bookId)
            ],
            'published_date' => 'required|date_format:Y-m-d',
            'description' => 'nullable|string',
            'image_url' => 'nullable|url:https',
            'genres' => 'required|array',
            'genres.*' => 'exists:genres,id',
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'タイトルを入力してください。',
            'author.required' => '著者を入力してください。',
            'isbn.required' => 'ISBNコードを入力してください。',
            'isbn.unique' => 'このISBNコードは既に登録されています。',
            'isbn.digits' => 'ISBNコードは13桁で入力してください。',
            'published_date.required' => '出版日を入力してください。',
            'published_date.date_format' => '正しい形式（YYYY/MM/DD）で日付を入力してください。',
            'image_url.url' => '書籍の画像URLはhttps:// から始まる正しいURLを入力してください。',
            'genres.required' => 'ジャンルを選択してください。',
        ];
    }
}