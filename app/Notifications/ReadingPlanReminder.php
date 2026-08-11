<?php

namespace App\Notifications;

use App\Models\ReadingPlan;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ReadingPlanReminder extends Notification
{
    use Queueable;

    protected $readingPlan;
    protected $type; //'before_3_days', 'today', 'after_3_days'

    /**
     * Create a new notification instance.
     */
    public function __construct(ReadingPlan $readingPlan, string $type)
    {
        $this->readingPlan = $readingPlan;
        $this->type = $type;
    }

    public function via($notifiable): array
    {
        // Laravel標準のnotificationsテーブルを使用
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        $bookTitle = $this->readingPlan->book->title;
        $targetDate = $this->readingPlan->target_date->format('Y/m/d');

        // Bladeの match ($timing) に完全一致する文字列を定義
        $timingValue = match ($this->type) {
            'before_3_days' => 'three_days_before',
            'today' => 'on_due_date',
            'after_3_days' => 'three_days_after',
            default => null,
        };

        $message = match ($this->type) {
            'before_3_days' => "『{$bookTitle}』の読書目標期日（{$targetDate}）が近づいてきました。期日まであと3日です。",
            'today' => "『{$bookTitle}』の読書目標が期日（{$targetDate}）を迎えました。",
            'after_3_days' => "『{$bookTitle}』の読書目標（{$targetDate}）から3日が過ぎました。",
            default => "『{$bookTitle}』の読書計画に関するお知らせです。",
        };

        return [
            'reading_plan_id' => $this->readingPlan->id,
            'title' => $message,
            'timing' => $timingValue,
        ];
    }
}
