<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReviewSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all();
        $books = Book::all();

        // 具体的なコメント内容のバリエーション配列
        $comments = [
            1 => '期待外れでした。あまりおすすめできません。',
            2 => '少し物足りない内容でした。好みが分かれそうです。',
            3 => '普通のクオリティです。参考になりました。',
            4 => '読み応えがあり、とても満足できる内容でした。',
            5 => '非常に読みやすく、一気に読了しました！万人におすすめできる名著です。',
        ];

        //レビュー件数が各2～4件になるように設定(4件×2冊、3件×6冊、2件×3冊 = 合計32件)
        $reviewCounts = [4, 4, 3, 3, 3, 3, 3, 3, 2, 2, 2];

        foreach ($books as $index => $book) {
            // この本に投稿するレビュー数を取得
            $count = $reviewCounts[$index];

            // 1人のユーザーが同じ本に2回レビューを書かないよう、
            // 5人のユーザーをランダムにシャッフルし、必要な件数分だけ選出
            $selectedUsers = $users->shuffle()->take($count);


            foreach ($selectedUsers as $user) {
                $rating = rand(1, 5); // 1〜5の範囲

                Review::create([
                    'user_id' => $user->id,
                    'book_id' => $book->id,
                    'rating' => $rating,
                    'comment' => $comments[$rating], // 評価(rating)にあったコメント
                ]);
            }
        }
    }
}
