<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\User;
use Illuminate\Database\Seeder;

class FavoriteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all();
        $books = Book::all();

        foreach ($users as $user) {
            // 各ユーザーごとに、ランダムで3〜5冊の本を選出
            $randomBooks = $books->random(fake()->numberBetween(3, 5));

            // 選出した書籍のID配列を取得
            $bookIds = $randomBooks->pluck('id')->toArray();

            // 中間テーブル favorites に同期する
            $user->favoriteBooks()->syncWithoutDetaching($bookIds);
        }
    }
}
