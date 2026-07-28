<?php

namespace Tests\Feature\Controllers;

use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenreControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function 新しくジャンルを登録して一覧画面にリダイレクトする()
    {
        // 1. Arrange
        $user = User::factory()->create();
        $validData = [
            'name' => '新しいジャンル',
        ];

        // 2. Act
        $response = $this->actingAs($user)->post(route('genres.store'), $validData);

        // 3. Assert
        $response->assertRedirect(route('genres.index'));
        $response->assertSessionHas('success', 'ジャンルを登録しました。');

        $this->assertDatabaseHas('genres', [
            'name' => '新しいジャンル',
        ]);
    }

    /** @test */
    public function ジャンル名が空欄の場合は登録できずバリデーションエラーになる()
    {
        // 1. Arrange
        $user = User::factory()->create();

        $invalidData = [
            'name' => '', // 必須エラー
        ];

        // 2. Act
        $response = $this->actingAs($user)->post(route('genres.store'), $invalidData);

        // 3. Assert
        // 前の画面へリダイレクトされるか
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['name']);
        // データベースに空のジャンルが登録されていないか
        $this->assertDatabaseCount('genres', 0);
    }

    /** @test */
    public function 自分自身のジャンル名であれば重複エラーにならず更新できる()
    {
        // 1. Arrange
        $user = User::factory()->create();
        $genre = Genre::factory()->create(['name' => '小説']);
        $updateData = [
            'name' => '小説', // 自分の既存の名前
        ];

        // 2. Act
        $response = $this->actingAs($user)->put(route('genres.update', $genre), $updateData);

        // 3. Assert
        $response->assertRedirect(route('genres.index'));
        $response->assertSessionHas('success', 'ジャンル名を更新しました。');

        $this->assertDatabaseHas('genres', [
            'id' => $genre->id,
            'name' => '小説',
        ]);
    }

    /** @test */
    public function 他人が既に登録しているジャンル名への変更はバリデーションエラーになる()
    {
        // 1. Arrange
        $user = User::factory()->create();

        // 2つの異なるジャンルを作成
        $myGenre = Genre::factory()->create(['name' => '技術書']);
        Genre::factory()->create(['name' => '旅行']); // 他のジャンル

        // 「技術書」を「ビジネス書（重複）」に書き換えようとするデータ
        $invalidUpdateData = [
            'name' => '旅行', // 重複エラー対象
        ];

        // 2. Act
        $response = $this->actingAs($user)->put(route('genres.update', $myGenre), $invalidUpdateData);

        // 3. Assert
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['name']);

        // データベースが書き換わっていないことを確認
        $this->assertDatabaseHas('genres', [
            'id' => $myGenre->id,
            'name' => '技術書',
        ]);
    }

    /** @test */
    public function ジャンル名を更新して一覧画面にリダイレクトする()
    {
        // 1. Arrange
        $user = User::factory()->create();
        $genre = Genre::factory()->create(['name' => '古いジャンル名']);
        $updateData = [
            'name' => '更新後のジャンル名',
        ];

        // 2. Act
        $response = $this->actingAs($user)->put(route('genres.update', $genre), $updateData);

        // 3. Assert
        $response->assertRedirect(route('genres.index'));
        $response->assertSessionHas('success', 'ジャンル名を更新しました。');

        $this->assertDatabaseHas('genres', [
            'id' => $genre->id,
            'name' => '更新後のジャンル名',
        ]);
    }

    /** @test */
    public function 書籍が1冊も紐づいていないジャンルは削除できる()
    {
        // 1. Arrange
        $user = User::factory()->create();
        $genre = Genre::factory()->create();

        // 2. Act
        $response = $this->actingAs($user)->delete(route('genres.destroy', $genre));

        // 3. Assert
        $response->assertRedirect(route('genres.index'));
        $response->assertSessionHas('success', 'ジャンルを削除しました。');

        $this->assertDatabaseMissing('genres', ['id' => $genre->id]);
    }

    /** @test */
    public function 書籍が1冊でも紐づいているジャンルは削除できずエラーメッセージを返す()
    {
        // 1. Arrange
        $user = User::factory()->create();
        $genre = Genre::factory()->create();
        $book = Book::factory()->create(['user_id' => $user->id]);

        // ジャンルに本を紐づける（中間テーブルにレコード作成）
        $genre->books()->attach($book->id);

        // 2. Act
        $response = $this->actingAs($user)->delete(route('genres.destroy', $genre));

        // 3. Assert
        $response->assertRedirect();
        $response->assertSessionHas('error', '登録されている書籍があるため、削除できません。');

        // データベースから消えていないことを確認
        $this->assertDatabaseHas('genres', ['id' => $genre->id]);
    }
}
