<?php

namespace Tests\Feature\Controllers;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BookControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // ブレード内のViteアセット読み込みによる500クラッシュを確実に防止
        $this->withoutVite();
    }

    /** @test */
    public function 書籍を新規登録し、ジャンルを同期してリダイレクトする(): void
    {
        // 1. Arrange
        $user = User::factory()->create();
        $genres = Genre::factory()->count(2)->create();

        $validData = [
            'title' => '新規登録の書籍',
            'author' => 'テスト著者',
            'isbn' => '1234567890123',
            'published_date' => '2026-01-01',
            'description' => '本の詳細説明が入ります。',
            'image_url' => 'https://example.com',
            'genres' => $genres->pluck('id')->toArray(),
        ];

        // 2. Act
        $response = $this->actingAs($user)->post(route('books.store'), $validData);

        // 3. Assert
        $response->assertRedirect(route('books.index'));
        $response->assertSessionHas('success', '書籍を登録しました。');

        $this->assertDatabaseHas('books', [
            'user_id' => $user->id,
            'title' => '新規登録の書籍',
        ]);

        $latestBook = Book::latest('id')->first();
        $this->assertCount(2, $latestBook->genres);
    }

    /** @test */
    public function バリデーションエラー時は登録できずセッションにエラーが残る(): void
    {
        // 1. Arrange
        $user = User::factory()->create();

        // タイトルや著者、ジャンルが空、不正なISBNなど、ルールに違反するデータ
        $invalidData = [
            'title' => '', // 必須エラー
            'author' => '', // 必須エラー
            'isbn' => '12345', // 13桁未満エラー
            'published_date' => '2026/01/01', // Y-m-d形式ではないエラー
            'image_url' => 'http://example.com', // httpsではないエラー
            'genres' => [], // ジャンル必須エラー
        ];

        // 2. Act
        $response = $this->actingAs($user)->post(route('books.store'), $invalidData);

        // 3. Assert
        // 前の画面へリダイレクトされるか
        $response->assertStatus(302);
        // 各項目にエラーが格納されているか
        $response->assertSessionHasErrors([
            'title',
            'author',
            'isbn',
            'published_date',
            'image_url',
            'genres'
        ]);
        // データベースに登録されていないか
        $this->assertDatabaseCount('books', 0);
    }

    /** @test */
    public function 既に登録されているISBNコードは登録できない(): void
    {
        // 1. Arrange
        $user = User::factory()->create();
        $genre = Genre::factory()->create();

        // 既に存在する本を登録（同じISBNを持たせる）
        $existingBook = Book::factory()->create(['isbn' => '1234567890123']);

        $duplicateData = [
            'title' => '重複書籍名',
            'author' => '重複著者',
            'isbn' => '1234567890123', // 重複エラー対象
            'published_date' => '2026-01-01',
            'genres' => [$genre->id],
        ];

        // 2. Act
        $response = $this->actingAs($user)->post(route('books.store'), $duplicateData);

        // 3. Assert
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['isbn']);

        // 最初に用意した1冊以外の本が増えていないか確認
        $this->assertDatabaseCount('books', 1);
    }

    /** @test */
    public function 存在しないジャンルIDが送信された場合は登録できない(): void
    {
        // 1. Arrange
        $user = User::factory()->create();

        $invalidGenreData = [
            'title' => 'テスト書籍',
            'author' => 'テスト著者',
            'isbn' => '9876543210987',
            'published_date' => '2026-01-01',
            'genres' => 99, // DBに存在しないジャンルID
        ];

        // 2. Act
        $response = $this->actingAs($user)->post(route('books.store'), $invalidGenreData);

        // 3. Assert
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['genres']); // 最初のジャンル要素にエラーが入る
        $this->assertDatabaseCount('books', 0);
    }

    /** @test */
    public function 作成者であれば書籍情報を更新しジャンルを同期する(): void
    {
        // 1. Arrange
        $user = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $user->id]);
        $genres = Genre::factory()->count(2)->create();

        $updateData = [
            'title' => '更新後の書籍タイトル',
            'author' => '更新後の著者',
            'isbn' => '9876543210987',
            'published_date' => '2026-05-01',
            'description' => '更新された説明文。',
            'image_url' => 'https://example.com',
            'genres' => $genres->pluck('id')->toArray(),
        ];

        // 2. Act
        $response = $this->actingAs($user)->put(route('books.update', $book), $updateData);

        // 3. Assert
        $response->assertRedirect(route('books.show', $book));
        $response->assertSessionHas('success', '書籍情報を更新しました。');

        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'title' => '更新後の書籍タイトル',
        ]);

        $this->assertCount(2, $book->refresh()->genres);
    }

    /** @test */
    public function 自分自身のISBNコードであれば重複エラーにならず更新できる(): void
    {
        // 1. Arrange
        $user = User::factory()->create();
        $genre = Genre::factory()->create();
        // 既存のISBNを持つ本を作成
        $book = Book::factory()->create([
            'user_id' => $user->id,
            'isbn' => '1234567890123',
            'title' => '元のタイトル',
        ]);

        // Factoryが作った ISO形式の文字列を、「Y-m-d」に強制変換する
        $safeDate = Carbon::parse($book->published_date)->format('Y-m-d');

        // ISBNは変えずに、タイトルだけ変更するデータを用意
        $updateData = [
            'title' => 'タイトルだけ更新',
            'author' => $book->author,
            'isbn' => '1234567890123',
            'published_date' => $safeDate,
            'genres' => [$genre->id],
        ];

        // 2. Act
        $response = $this->actingAs($user)->put(route('books.update', $book), $updateData);

        // 3. Assert
        $response->assertRedirect(route('books.show', $book));
        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'title' => 'タイトルだけ更新',
        ]);
    }

    /** @test */
    public function 登録しているISBNコードへの変更はバリデーションエラーになる(): void
    {
        // 1. Arrange
        $user = User::factory()->create();
        $genre = Genre::factory()->create();

        // 2冊の本を作成
        $myBook = Book::factory()->create(['user_id' => $user->id, 'isbn' => '1111111111111']);
        $otherBook = Book::factory()->create(['isbn' => '2222222222222']); // 他人の本

        // myBookのISBNを、otherBookのISBN（重複）に書き換えようとするデータ
        $invalidUpdateData = [
            'title' => '更新タイトル',
            'author' => $myBook->author,
            'isbn' => '2222222222222', // 重複エラー対象
            'published_date' => $myBook->published_date,
            'genres' => [$genre->id],
        ];

        // 2. Act
        $response = $this->actingAs($user)->put(route('books.update', $myBook), $invalidUpdateData);

        // 3. Assert
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['isbn']);

        // DBが書き換わっていないことを確認
        $this->assertDatabaseHas('books', [
            'id' => $myBook->id,
            'isbn' => '1111111111111',
        ]);
    }

    /** @test */
    public function 作成者以外が更新しようとすると403エラーを返す(): void
    {
        // 1. Arrange
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $owner->id]); // 所有者はowner
        $genre = Genre::factory()->create();

        $updateData = [
            'title' => '不正な更新試み',
            'author' => '悪意あるユーザー',
            'isbn' => '9876543210987',
            'published_date' => '2026-05-01',
            'genres' => [$genre->id],
        ];

        // 2. Act
        // otherUser（作成者以外）としてリクエストを送信
        $response = $this->actingAs($otherUser)->put(route('books.update', $book), $updateData);

        // 3. Assert
        $response->assertStatus(403); // 認可エラーになること
    }

    /** @test */
    public function 作成者であれば書籍を削除できる(): void
    {
        // 1. Arrange
        $user = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $user->id]);

        // 2. Act
        $response = $this->actingAs($user)->delete(route('books.destroy', $book));

        // 3. Assert
        $response->assertRedirect(route('books.index'));
        $response->assertSessionHas('success', '書籍を削除しました。');

        $this->assertDatabaseMissing('books', ['id' => $book->id]);
    }

    /** @test */
    public function 作成者以外が削除しようとすると403エラーを返す(): void
    {
        // 1. Arrange
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $owner->id]);

        // 2. Act
        $response = $this->actingAs($otherUser)->delete(route('books.destroy', $book));

        // 3. Assert
        $response->assertStatus(403);
        $this->assertDatabaseHas('books', ['id' => $book->id]); // DBから消えていないこと
    }

    /** @test */
    public function 書籍一覧にてタイトルまたは著者名の部分一致検索ができること(): void
    {
        // 1. Arrange
        Book::factory()->create(['title' => 'Laravel入門', 'author' => '山田太郎']);
        Book::factory()->create(['title' => 'Vue.js実践', 'author' => '鈴木一郎']);
        Book::factory()->create(['title' => 'PHP基礎', 'author' => '山田花子']);

        // 2. Act & 3. Assert
        // 「Laravel」でタイトル検索
        $response1 = $this->get('/books?keyword=Laravel');
        $response1->assertSee('Laravel入門')->assertDontSee('Vue.js実践');

        // 「山田」で著者名検索
        $response2 = $this->get('/books?keyword=山田');
        $response2->assertSee('Laravel入門')->assertSee('PHP基礎')->assertDontSee('Vue.js実践');
    }

    /** @test */
    public function 書籍一覧にて指定したジャンルで抽出ができること(): void
    {
        // 1. Arrange
        $genreA = Genre::factory()->create(['name' => '技術書']);
        $genreB = Genre::factory()->create(['name' => '小説']);

        // ジャンルが紐付いた書籍を生成
        Book::factory()
            ->hasAttached($genreA)
            ->create(['title' => '達人プログラマー']);

        Book::factory()
            ->hasAttached($genreB)
            ->create(['title' => '吾輩は猫である']);

        // 2. Act
        $response = $this->get("/books?genre={$genreA->id}");

        // 3. Assert
        $response->assertSee('達人プログラマー')->assertDontSee('吾輩は猫である');
    }

    /** @test */
    public function 書籍一覧にて指定した各種ソート順で正しく並び替えられること(): void
    {
        // 1. Arrange
        // テストデータの準備（作成日、タイトルの異なる本を用意）
        $book1 = Book::factory()->create([
            'title' => 'AAAの本',
            'created_at' => now()->subDays(1),
        ]);
        $book2 = Book::factory()->create([
            'title' => 'BBBの本',
            'created_at' => now()->subDays(2),
        ]);
        $book3 = Book::factory()->create([
            'title' => 'CCCの本',
            'created_at' => now(),
        ]);

        // 各書籍にレビューデータを紐付ける
        // book1 は 平均3.0点
        Review::factory()->create(['book_id' => $book1->id, 'rating' => 3.0]);
        // book2 は 平均4.5点
        Review::factory()->create(['book_id' => $book2->id, 'rating' => 4.5]);
        // book3 は 平均5.0点
        Review::factory()->create(['book_id' => $book3->id, 'rating' => 5.0]);

        // 2. Act & 3. Assert

        // ① 登録日が新しい順 (book3 -> book1 -> book2)
        $this->get('/books?sort=newest')
            ->assertSeeInOrder(['CCCの本', 'AAAの本', 'BBBの本']);

        // ② 登録日が古い順 (book2 -> book1 -> book3)
        $this->get('/books?sort=oldest')
            ->assertSeeInOrder(['BBBの本', 'AAAの本', 'CCCの本']);

        // ③ タイトル順 (book1 -> book2 -> book3)
        $this->get('/books?sort=title')
            ->assertSeeInOrder(['AAAの本', 'BBBの本', 'CCCの本']);

        // ④ 平均評価が高い順 (book3(5.0) -> book2(4.5) -> book1(3.0))
        $this->get('/books?sort=rating')
            ->assertSeeInOrder(['CCCの本', 'BBBの本', 'AAAの本']);
    }

    /** ISBN検索
     */
    /** @test */
    public function 正しい13桁のISBNコードで検索した場合にGoogleBooksAPIから整形された書籍データが返却されること(): void
    {
        // フェイク（モック）されていない外部通信が発生した場合に、本物の通信をさせずにエラー（例外）を投げる設定
        Http::preventStrayRequests();

        // 1. Arrange
        $user = User::factory()->create();
        $isbn = '9784873119076';
        $apiUrl = 'https://googleapis.com*';

        // Google Books APIのレスポンスをフェイク（モック）
        Http::fake([
            '*' => Http::response([
                'totalItems' => 1,
                'items' => [
                    [
                        'volumeInfo' => [
                            'title' => '達人プログラマー',
                            'authors' => ['Andrew Hunt', 'David Thomas'],
                            'description' => '熟達した職人技について解説。',
                            'imageLinks' => ['thumbnail' => 'http://example.com'], // ※エラーに合せて修正
                            'publishedDate' => '2020-11-20',
                            'industryIdentifiers' => [
                                ['type' => 'ISBN_13', 'identifier' => $isbn]
                            ]
                        ]
                    ]
                ]
            ], 200)
        ]);

        // 2. Act
        $response = $this->actingAs($user)->getJson("/books/isbn/{$isbn}");

        // 3. Assert
        $response->assertStatus(200)
            ->assertJson([
                'title' => '達人プログラマー',
                'author' => 'Andrew Hunt, David Thomas',
                'description' => '熟達した職人技について解説。',
                'image_url' => 'http://example.com',
                'published_date' => '2020-11-20',
            ]);
    }

    /** @test */
    public function 存在しないISBNまたは返却データのISBNが一致しない場合に404エラーが返却されること(): void
    {
        // フェイク（モック）されていない外部通信が発生した場合に、本物の通信をさせずにエラー（例外）を投げる設定
        Http::preventStrayRequests();

        // 1. Arrange
        $user = User::factory()->create();
        $inputIsbn = '9784101010012'; // 偽物のISBN
        $returnedIsbn = '9784101010014'; // Googleが自動補正し返してきた別本のISBN

        Http::fake([
            '*' => Http::response([
                'totalItems' => 1,
                'items' => [
                    [
                        'volumeInfo' => [
                            'title' => 'こころ',
                            'industryIdentifiers' => [
                                ['type' => 'ISBN_13', 'identifier' => $returnedIsbn]
                            ]
                        ]
                    ]
                ]
            ], 200)
        ]);

        // 2. Act
        $response = $this->actingAs($user)->getJson("/books/isbn/{$inputIsbn}");

        // 3. Assert
        $response->assertStatus(404)
            ->assertJson(['error' => '該当する書籍情報が見つかりませんでした。']);
    }
}
