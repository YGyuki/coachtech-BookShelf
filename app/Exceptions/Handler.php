<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        // 未認証（401）時のレスポンスをJSONにカスタマイズ
        $this->renderable(function (AuthenticationException $e, $request) {
            if ($request->is('api/v1/*')) {
                return response()->json([
                    'message' => '認証されていません。有効なトークンを提示してください。',
                ], 401);
            }
        });

        // 認可エラー（403）時のレスポンスをJSONにカスタマイズ
        $this->renderable(function (AuthorizationException $e, $request) {
            if ($request->is('api/v1/*')) {
                return response()->json([
                    'message' => 'この操作を行う権限がありません（書籍の所有者ではありません）。',
                ], 403);
            }
        });

        // Laravelが認可エラーを自動で AccessDeniedHttpException に変換するため、
        // ここでキャッチして api/v1 向けにカスタムJSONメッセージを返却する
        $this->renderable(function (AccessDeniedHttpException $e, $request) {
            if ($request->is('api/v1/*')) {
                return response()->json([
                    'message' => 'この操作を行う権限がありません（書籍の所有者ではありません）。',
                ], 403);
            }
        });
    }
}
