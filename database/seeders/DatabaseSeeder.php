<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        Announcement::create([
            'title' => 'Welcome to the new staff portal',
            'body' => 'All hospital announcements will be posted here. Check back regularly for updates on schedules, trainings, and events.',
        ]);

        Announcement::create([
            'title' => 'Wellness session this Friday',
            'body' => 'A guided stretching and recovery session for all staff will be held this Friday at 4 PM in the physiotherapy hall.',
        ]);

        Announcement::create([
            'title' => 'Updated duty roster available',
            'body' => 'The revised duty roster for this month is now available. Please review your shifts and report any conflicts to administration.',
        ]);
    }
}
