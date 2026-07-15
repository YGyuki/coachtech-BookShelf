<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGenreRequest extends FormRequest
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
        $genreId = $this->route('genre')?->id ?? $this->route('genre');

        return [
            'name' => [
                'required',
                'string',
                Rule::unique('genres', 'name')->ignore($genreId),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => "ジャンル名を入力してください。",
            'name.unique' => "このジャンル名は既に登録されています。",
        ];
    }
}
