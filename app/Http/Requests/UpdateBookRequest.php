<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBookRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $bookId = $this->route('book')?->id ?? $this->route('book');

        return [
            'title' => 'required|string',
            'author' => 'required|string',
            'isbn_13' => 'required| digits:13',
            Rule::unique('books', 'isbn_13')->ignore($bookId),
            'publication_date' => 'required|date_format:Y/m/d',
            'image_url' => 'nullable|url:https',
            'genre_ids' => 'required|array',
            'genre_ids.*' => 'exists:genres,id',
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'タイトルを入力してください。',
            'author.required' => '著者を入力してください。',
            'isbn_13.required' => 'ISBNコードを入力してください。',
            'isbn_13.unique' => 'このISBNコードは既に登録されています。',
            'isbn.digits' => 'ISBNコードは13桁で入力してください。',
            'published_at.required' => '出版日を入力してください。',
            'published_at.date_format' => '正しい形式（YYYY/MM/DD）で日付を入力してください。',
            'image_url.url' => '書籍の画像URLはhttps:// から始まる正しいURLを入力してください。',
            'genre_ids.required' => 'ジャンルを選択してください。',
        ];
    }
}
