<?php

namespace App\Http\Controllers;

use App\Enums\ReadingPlanStatus;
use App\Http\Requests\StoreReadingPlanRequest;
use App\Http\Requests\UpdateReadingPlanRequest;
use App\Models\Book;
use App\Models\ReadingPlan;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReadingPlanController extends Controller
{
    public function index(Request $request): View
    {
        // ログインユーザーの計画を取得
        $query = ReadingPlan::where('user_id', Auth::id())->with('book');

        // 現在選択されているステータスを取得
        $currentStatus = $request->input('status');

        // ステータス（状態）による絞り込み
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $readingPlans = $query->latest()->get();

        return view('reading-plans.index', compact('readingPlans', 'currentStatus'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        $books = Book::all();

        return view('reading-plans.create', compact('books'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreReadingPlanRequest $request): RedirectResponse
    {
        ReadingPlan::create([
            'user_id' => Auth::id(),
            'book_id' => $request->book_id,
            'target_date' => $request->target_date,
            'status' => 'in_progress',
        ]);

        return redirect()->route('reading-plans.index')
            ->with('success', '読書計画を作成しました。');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ReadingPlan $readingPlan): View
    {
        $this->authorize('update', $readingPlan);

        return view('reading-plans.edit', compact('readingPlan'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateReadingPlanRequest $request, ReadingPlan $readingPlan): RedirectResponse
    {
        // 認可チェック
        $this->authorize('update', $readingPlan);

        $readingPlan->update([
            'target_date' => $request->target_date,
        ]);

        return redirect()->route('reading-plans.index')
            ->with('success', '読書計画を更新しました。');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ReadingPlan $readingPlan): RedirectResponse
    {
        // 認可チェック
        $this->authorize('delete', $readingPlan);

        $readingPlan->delete();

        return redirect()->route('reading-plans.index')
            ->with('success', '読書計画を削除しました。');
    }

    /**
     * 読書計画を完了（読了）にする
     */
    public function complete(ReadingPlan $readingPlan): RedirectResponse
    {
        // 認可チェック
        $this->authorize('update', $readingPlan);

        // ステータスを完了にし、完了日時に現在時刻を記録
        $readingPlan->update([
            'status' => ReadingPlanStatus::Completed->value, // Enumの値
            'completed_at' => Carbon::now(), // 現在日時
        ]);

        // 紐づく書籍のタイトルを取得
        $bookTitle = $readingPlan->book->title;

        return redirect()->route('reading-plans.index')
            ->with('success', "『{$bookTitle}』を読了しました。");
    }
}
