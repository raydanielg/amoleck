<?php

use App\Models\Announcement;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome', [
        'announcements' => Announcement::latest()
            ->get(['id', 'title', 'body', 'created_at'])
            ->map(fn ($announcement) => [
                'id' => $announcement->id,
                'title' => $announcement->title,
                'body' => $announcement->body,
                'created_at_diff' => $announcement->created_at->diffForHumans(),
            ])
            ->all(),
    ]);
})->name('home');

Route::get('/announcements', function () {
    return Inertia::render('announcements', [
        'announcements' => Announcement::latest()
            ->get(['id', 'title', 'body', 'created_at'])
            ->map(fn ($announcement) => [
                'id' => $announcement->id,
                'title' => $announcement->title,
                'body' => $announcement->body,
                'created_at_diff' => $announcement->created_at->diffForHumans(),
            ])
            ->all(),
    ]);
})->name('announcements');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::redirect('dashboard', '/admin/dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
