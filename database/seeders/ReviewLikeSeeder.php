<?php

namespace Database\Seeders;

use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReviewLikeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all();
        $reviews = Review::all();

        foreach ($reviews as $review) {
            // 自分のレビューを除く
            $likedUsers = $users->filter(function ($user) use ($review) {
                return $user->id !== $review->user_id;
            });

            // 各レビューに0〜3人のユーザーがいいね
            $likeCount = fake()->numberBetween(0, 3);

            if ($likeCount > 0) {
                // シャッフルして必要な件数分を take で切り出す
                $randomUsers = $likedUsers->shuffle()->take($likeCount);

                // いいねするユーザーのID配列を取得
                $userIds = $randomUsers->pluck('id')->toArray();

                // 中間テーブル review_likes に追加する
                $review->likedUsers()->syncWithoutDetaching($userIds);
            }
        }
    }
}