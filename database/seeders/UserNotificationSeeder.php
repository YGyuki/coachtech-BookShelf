<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\User;
use App\Notifications\GeneralNotification;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class UserNotificationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::find(1);

        if (! $user) {
            return;
        }

        // 既存の通知データを一度クリア（テストをクリーンにするため）
        $user->notifications()->delete();

        // ダミーの書籍タイトルを取得（存在しない場合はデフォルト文言）
        $book1 = Book::find(1)?->title ?? 'テスト書籍A';
        $book2 = Book::find(2)?->title ?? 'テスト書籍B';
        $book3 = Book::find(3)?->title ?? 'テスト書籍C';
        $book4 = Book::find(4)?->title ?? 'テスト書籍D';

        // 当日通知用の日付フォーマット（〇月〇日）
        $todayFormatted = Carbon::today()->isoFormat('M月D日');

        // 通知メッセージ
        $scenarios = [
            [
                'timing' => 'three_days_before',
                'message' => "『{$book1}』の読書目標期日が近づいてきました。期日まであと3日です。",
                'read' => false,
            ],
            [
                'timing' => 'on_due_date',
                'message' => "『{$book2}』の読書目標が期日（{$todayFormatted}）を迎えました。",
                'read' => false,
            ],
            [
                'timing' => 'three_days_after',
                'message' => "『{$book3}』の読書目標から3日が過ぎました。",
                'read' => false,
            ],
            [
                'timing' => 'on_due_date',
                'message' => "『{$book4}』の読書目標が期日（{$todayFormatted}）を迎えました。",
                'read' => true, // 既読状態
            ],
            [
                'timing' => null,
                'message' => '読書計画に関するお知らせです。',
                'read' => false,
            ],
        ];

        // データを投入
        foreach ($scenarios as $scenario) {
            // 1. 通知を発行（この時点で未読状態でテーブルに保存される）
            $user->notify(new GeneralNotification($scenario));

            // 2. 既読フラグが true の場合は、最新の通知を既読（read_atを現在時刻）にする
            if ($scenario['read']) {
                $user->unreadNotifications()->first()->markAsRead();
            }
        }
    }
}
