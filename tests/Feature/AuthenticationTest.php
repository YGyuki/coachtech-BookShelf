<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function 正しい認証情報でログイン後、書籍一覧にリダイレクトできる(): void
    {
        // 1. Arrange
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $loginData = [
            'email' => 'test@example.com',
            'password' => 'password',
        ];

        // 2. Act
        $response = $this->post('/login', $loginData);

        // 3. Assert
        $response->assertRedirect(route('books.index'));
        $this->assertAuthenticatedAs($user);
    }

    /** @test */
    public function 入力必須項目が空だとバリデーションエラーになる(): void
    {
        // Act
        $response = $this->post(route('login'), [
            'email' => '', // 必須エラー
            'password' => '', // 必須エラー
        ]);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['email', 'password']);
        $this->assertGuest();
    }

    /** @test */
    public function 誤ったパスワードを送信すると認証されずバリデーションエラーを返す(): void
    {
        // 1. Arrange
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $invalidData = [
            'email' => 'test@example.com',
            'password' => 'wrong-password', // 間違ったパスワード
        ];

        // 2. Act
        $response = $this->post('/login', $invalidData);

        // 3. Assert
        // $response->assertStatus(302);
        $response->assertSessionHasErrors('password');
        $this->assertGuest();
    }

    /** @test */
    public function ログアウトされ、書籍一覧にリダイレクトする(): void
    {
        // 1. Arrange
        $user = User::factory()->create();

        // 2. Act
        $response = $this->actingAs($user)->post('/logout');

        // 3. Assert
        $response->assertStatus(302);
        $this->assertGuest();
    }
}
