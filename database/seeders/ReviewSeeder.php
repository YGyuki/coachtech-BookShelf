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
            '非常に読みやすく、一気に読了しました！万人におすすめできる名著です。',
            '内容が非常に深く、これからの人生や仕事のバイブルになりそうです。',
            '少し難しい部分もありましたが、解説が丁寧で非常に学びの多い一冊でした。',
            '期待通りの素晴らしさです。ページをめくる手が止まりませんでした。',
            '実用的な知識が詰まっており、読み終わってすぐにでも実践したくなりました！',
            '著者の視点が斬新で、今までの固定観念を覆されるような新しい気づきがありました。',
            '手元に置いて何度も読み返したくなる素晴らしい本です。買って大正解でした。',
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
                Review::factory()->create([
                    'user_id' => $user->id,
                    'book_id' => $book->id,
                    'rating' => fake()->numberBetween(3, 5), // 3〜5の範囲
                    'comment' => fake()->randomElement($comments),
                ]);
            }
        }
    }
}
