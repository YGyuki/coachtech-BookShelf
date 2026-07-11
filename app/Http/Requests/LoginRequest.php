<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Http\Requests\LoginRequest as FortifyLoginRequest;

class LoginRequest extends FortifyLoginRequest
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
            'email' => 'required|string|email',
            'password' => [
                'required',
                'string',
                //メールアドレスはあるがパスワードが間違っている場合の検証
                function ($attribute, $value, $fail) {
                    //メールアドレス入力形式にエラーがある場合はスキップ
                    if ($this->getValidatorInstance()->errors()->has('email')) {
                        return;
                    }

                    //入力されたメールアドレスでユーザーを検索
                    $user = User::where('email', $this->email)->first();

                    //ユーザーが存在ない、またはハッシュ化したパスワードが一致しない場合にエラー
                    if (!$user || !Hash::check($value, $user->password)) {
                        $fail($this->messages()['password.mismatch']);
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'メールアドレスを入力してください。',
            'email.email' => 'メールアドレスはメール形式で入力してください。',
            'password.required' => 'パスワードを入力してください。',
            'password.mismatch' => 'ログイン情報が登録されていません。',
        ];
    }
}
