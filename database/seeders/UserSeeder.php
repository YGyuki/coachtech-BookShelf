<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $hashedPassword = Hash::make('password');

        $users = [
            ['name' => '山田太郎', 'email' => 'yamada@example.com', 'password' => $hashedPassword],
            ['name' => '鈴木花子', 'email' => 'suzuki@example.com', 'password' => $hashedPassword],
            ['name' => '田中一郎', 'email' => 'tanaka@example.com', 'password' => $hashedPassword],
            ['name' => '佐藤美咲', 'email' => 'sato@example.com', 'password' => $hashedPassword],
            ['name' => '高橋健太', 'email' => 'takahashi@example.com', 'password' => $hashedPassword],
        ];

        //email重複をチェックし、存在しなければ作成する
        foreach ($users as $user) {
            User::firstOrCreate(
                ['email' => $user['email']], // 検索条件
                ['name' => $user['name'], 'password' => $user['password']]
            );
        }
    }
}
