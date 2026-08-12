<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(LoginRequest $request)
    {
        $validated = $request->validated();

        $user = User::where('email', $validated['email'])->first();

        // ユーザーの存在確認とパスワードの一致確認
        if (!$user || !Hash::check($validated['password'], $user->password)) {
            return response()->json(['message' => 'ログイン情報が正しくありません。'], 422);
        }

        // Sanctumトークンを発行してJSONで返却
        $token = $user->createToken('postman-token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]);
    }
}
