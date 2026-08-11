<?php

namespace App\Console\Commands;

use App\Enums\ReadingPlanStatus;
use App\Models\ReadingPlan;
use App\Notifications\ReadingPlanReminder;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CheckReadingPlans extends Command
{
    protected $signature = 'app:check-reading-plans';
    protected $description = '読書計画の期日チェック、通知送信、ステータス自動更新を日次で行う';

    public function handle()
    {
        $today = Carbon::today();

        // 完了（Completed）および すでに期限切れ（Expired）以外の計画をすべて取得
        $activePlans = ReadingPlan::whereNotIn('status', [
            ReadingPlanStatus::Completed->value,
            ReadingPlanStatus::Expired->value,
        ])
            ->with(['user', 'book'])
            ->get();

        foreach ($activePlans as $plan) {
            $targetDate = Carbon::parse($plan->target_date)->startOfDay();
            //期日からみて何日経過したか
            $passedDays = $targetDate->diffInDays($today, false);

            //デバック
            // $this->info("Plan ID: {$plan->id}, Title: {$plan->book->title}, Status: {$plan->status}, Passed Days: {$passedDays}");


            // 【通知タイミングの判定】
            if ($passedDays === -3) {
                // 3日前
                $plan->user->notify(new ReadingPlanReminder($plan, 'before_3_days'));
            } elseif ($passedDays === 0) {
                // 期日当日
                $plan->user->notify(new ReadingPlanReminder($plan, 'today'));
            } elseif ($passedDays === 3) {
                // 3日後
                $plan->user->notify(new ReadingPlanReminder($plan, 'after_3_days'));
            }

            // 【ステータス自動変更の判定】
            // 期日を1日でも超過した場合、expired に変更
            if ($passedDays >= 1) {
                $plan->update([
                    'status' => ReadingPlanStatus::Expired->value,
                ]);
                $this->info("--> [STATUS UPDATED] Plan ID {$plan->id} is now EXPIRED.");
            }
        }

        $this->info('読書計画の判定バッチ処理が正常に完了しました。');
    }
}
