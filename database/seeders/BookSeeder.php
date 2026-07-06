<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Database\Seeder;

class BookSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::first();
        $books = [
            [
                'title' => '吾輩は猫である',
                'author' => '夏目漱石',
                'isbn_13' => '9784101010014',
                'publication_date' => '1905-01-01',
                'genres' => ['小説']
            ],

            [
                'title' => '人を動かす',
                'author' => 'D・カーネギー',
                'isbn_13' => '9784422100524',
                'publication_date' => '1936-10-01',
                'genres' => ['ビジネス', '自己啓発']
            ],

            [
                'title' => 'リーダブルコード',
                'author' => 'Dustin Boswell',
                'isbn_13' => '9784873115658',
                'publication_date' => '2012-06-23',
                'genres' => ['技術書']
            ],

            [
                'title' => '7つの習慣',
                'author' => 'スティーブン・R・コヴィー',
                'isbn_13' => '9784863940246',
                'publication_date' => '2013-08-30',
                'genres' => ['ビジネス', '自己啓発']
            ],

            [
                'title' => '坊っちゃん',
                'author' => '夏目漱石',
                'isbn_13' => '9784101010021',
                'publication_date' => '1906-04-01',
                'genres' => ['小説']
            ],

            [
                'title' => 'サピエンス全史',
                'author' => 'ユヴァル・ノア・ハラリ',
                'isbn_13' => '9784309226712',
                'publication_date' => '2016-09-08',
                'genres' => ['歴史', '科学']
            ],

            [
                'title' => 'Clean Code',
                'author' => 'Robert C. Martin',
                'isbn_13' => '9784048930598',
                'publication_date' => '2017-12-18',
                'genres' => ['技術書']
            ],

            [
                'title' => '嫌われる勇気',
                'author' => '岸見一郎・古賀史健',
                'isbn_13' => '9784478025819',
                'publication_date' => '2013-12-13',
                'genres' => ['自己啓発']
            ],

            [
                'title' => '火花',
                'author' => '又吉直樹',
                'isbn_13' => '9784163902302',
                'publication_date' => '2015-03-11',
                'genres' => ['小説']
            ],

            [
                'title' => 'FACTFULNESS',
                'author' => 'ハンス・ロスリング',
                'isbn_13' => '9784822289607',
                'publication_date' => '2019-01-11',
                'genres' => ['ビジネス', '科学']
            ],

            [
                'title' => 'コンテナ物語',
                'author' => 'マルク・レビンソン',
                'isbn_13' => '9784822251468',
                'publication_date' => '2007-01-18',
                'genres' => ['ビジネス', '歴史']
            ],
        ];

        //booksへの書籍登録
        foreach ($books as $index => $bookData) {
            // カウンタ番号（1〜11）を生成
            $number = $index + 1;

            // isbn_13の重複をチェックし、存在しなければ作成
            $book = Book::firstOrCreate(
                ['isbn_13' => $bookData['isbn_13']], // 検索条件
                [
                    'user_id' => $user->id,
                    'title' => $bookData['title'],
                    'author' => $bookData['author'],
                    'publication_date' => $bookData['publication_date'],
                    //適当な文字列？
                    'description' => fake()->realText(),
                    'image_url' => "https://placehold.co/200x300/e2e8f0/475569?text={$number}",
                ]
            );

            //book_genreへのジャンル登録
            // 指定されたジャンル名から、対応するIDの配列を取得
            $genreIds = Genre::whereIn('name', $bookData['genres'])->pluck('id');

            // 中間テーブル book_genre に同期する
            $book->genres()->sync($genreIds);
        }
    }
}
