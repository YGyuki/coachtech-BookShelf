<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            GenreSeeder::class,
            BookSeeder::class,
            ReviewSeeder::class,
            ReviewLikeSeeder::class,
            FavoriteSeeder::class,
            ReadingPlanSeeder::class,
            UserNotificationSeeder::class,
        ]);
    }
}
