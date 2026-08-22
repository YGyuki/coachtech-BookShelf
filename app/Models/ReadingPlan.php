<?php

namespace App\Models;

use App\Enums\ReadingPlanStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReadingPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'book_id',
        'target_date',
        'completed_at',
        'status',
    ];

    /** カラムの型キャスト定義 */
    protected $casts = [
        'status' => ReadingPlanStatus::class,
        'target_date' => 'date',
        'completed_at' => 'datetime',
    ];

    /** モデルのデフォルト値 */
    protected $attributes = [
        'status' => 'in_progress',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function scopeOfStatus(Builder $query, ?string $status): Builder
    {
        if (empty($status)) {
            return $query; // 選択されていなければ、絞り込みをせずにそのまま返す
        }

        return $query->where('status', $status); // 選択されていれば、whereで絞り込む
    }
}
