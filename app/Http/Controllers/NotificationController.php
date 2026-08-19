<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function index()
    {
        // ログインユーザーの通知を最新順に取得
        $notifications = Auth::user()->notifications()->get();

        return view('notifications.index', compact('notifications'));
    }

    /**
     * 通知を既読にする
     */
    public function read(string $id)
    {
        // ログインユーザーの通知の中から該当のIDを探す(+認可)
        $notification = Auth::user()->unreadNotifications()->findOrFail($id);

        // 既読化
        $notification->markAsRead();

        return redirect()->back()->with('success', '通知を既読にしました。');
    }
}
