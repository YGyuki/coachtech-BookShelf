<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateReadingPlanRequest extends FormRequest
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
        $readingPlan = $this->route()->parameter('reading_plan');
        $readingPlanId = is_object($readingPlan) ? $readingPlan->id : $readingPlan;

        return [
            'book_id' => [
                'sometimes', // 編集時に書籍を修正できないため
                'integer',
                'exists:books,id',
                Rule::unique('reading_plans', 'book_id')
                    ->where('user_id', auth()->id())
                    ->ignore($readingPlanId),
            ],
            'target_date' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:today',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'book_id.sometimes' => '書籍を選択してください。',
            'book_id.exists' => '選択された書籍は存在しません。',
            'book_id.integer' => '書籍ＩＤは整数で入力してください。',
            'book_id.unique' => 'この書籍に対する読書計画は既に作成されています。',

            'target_date.required' => '期日を入力してください。',
            'target_date.date_format' => '正しい形式（YYYY/MM/DD）で日付を入力してください。',
            'target_date.after_or_equal' => '今日以降の日付を入力してください。',
        ];
    }
}
