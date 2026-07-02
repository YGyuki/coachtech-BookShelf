<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Book extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'author',
        'isbn_13',
        'publication_date',
        'description',
        'image_url',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function genres(): BelongsToMany
    {
        return $this->belongsToMany(Genre::class, 'book_genre')
            ->withTimestamps();
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function favoritedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'favorites')
            ->withTimestamps();
    }
}
