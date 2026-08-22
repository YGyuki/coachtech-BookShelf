<?php

namespace App\Http\Requests\Api\v1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SearchBookRequest extends FormRequest
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
        return [
            // キーワード (文字列、最大100文字)
            'keyword' => 'nullable|string|max:100',

            // ジャンルID
            // ID検索用
            'genre' => 'nullable|integer|exists:genres,id',
            // 名前検索用
            'genre_name' => 'nullable|string|max:50',

            // ページ番号 (整数、1以上10000以下)
            'page' => 'nullable|integer|min:1|max:10000',

            // ページあたり件数 (10のみ指定可能)
            'per_page' => ['nullable', 'integer', Rule::in([10])],

            // ソート順 (指定の文字列のみ許可)
            'sort' => [
                'nullable',
                'string',
                Rule::in(['newest', 'oldest', 'rating', 'title']),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'keyword.max' => '検索キーワードは100文字以内で入力してください。',

            'genre.exists' => '指定されたジャンルIDは存在しません。',
            'genre.integer' => 'ジャンルIDは整数で指定してください。',
            'genre_name.max' => 'ジャンル名は50文字以内で入力してください。',

            'page.integer' => 'ページ番号は整数で指定してください。',
            'page.min' => 'ページ番号は整数で指定してください。',
            'page.max' => 'ページ番号は整数で指定してください。',

            'per_page.in' => '1ページあたりの件数は10件のみ指定可能です。',

            'sort.in' => '指定されたソート順は無効です。',
        ];
    }
}
