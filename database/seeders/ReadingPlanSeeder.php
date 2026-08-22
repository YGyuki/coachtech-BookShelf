<?php

namespace Database\Seeders;

use App\Models\ReadingPlan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class ReadingPlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plans = [
            // ユーザーは山田太郎(user_id = 1)
            // 1. 期日の3日前（通知発火対象）
            [
                'user_id' => 1,
                'book_id' => 1,
                'status' => 'in_progress',
                'target_date' => Carbon::today()->addDays(3),
            ],
            // 2. 当日（通知発火対象）
            [
                'user_id' => 1,
                'book_id' => 2,
                'status' => 'in_progress',
                'target_date' => Carbon::today(),
            ],
            // 3. 期日から3日経過（通知発火対象）
            [
                'user_id' => 1,
                'book_id' => 3,
                'status' => 'expired',
                'target_date' => Carbon::today()->subDays(3),
            ],
            // 4. 期日が過ぎたがステータス＝完了（通知は発火しない）
            [
                'user_id' => 1,
                'book_id' => 4,
                'status' => 'completed',
                'completed_at' => now(),
                'target_date' => Carbon::today()->subDays(3),
            ],
            // 5. 期日から3日以上前（通知は発火しない）
            [
                'user_id' => 1,
                'book_id' => 5,
                'status' => 'in_progress',
                'target_date' => Carbon::today()->addDays(10),
            ],
            // 6. 認可検証用：別ユーザー（通知は来ない：進行中/3日前）
            [
                'user_id' => 2,
                'book_id' => 1,
                'status' => 'in_progress',
                'target_date' => Carbon::today()->addDays(3),
            ],
            // 7. 認可検証用：別ユーザー（通知は来ない：期限切れ/3日後）
            [
                'user_id' => 3,
                'book_id' => 2,
                'status' => 'expired',
                'target_date' => Carbon::today()->subDays(3),
            ],
        ];

        // データの作成と投入（createを使用）
        foreach ($plans as $plan) {
            ReadingPlan::create($plan);
        }
    }
}
