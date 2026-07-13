<?php

namespace App\Policies;

use App\Models\Book;
use App\Models\User;

class BookPolicy
{
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * 書籍の編集画面表示・更新処理の認可 (作成者本人のみ)
     */
    public function update(User $user, Book $book): bool
    {
        return $user->id === $book->user_id;
    }

    /**
     * 書籍の削除処理の認可 (作成者本人のみ)
     */
    public function delete(User $user, Book $book): bool
    {
        return $user->id === $book->user_id;
    }
}
