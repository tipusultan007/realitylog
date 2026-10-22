<?php

use App\Http\Controllers\Api\V1\AttachmentController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CaptureController;
use App\Http\Controllers\Api\V1\CollectionController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\ExportController;
use App\Http\Controllers\Api\V1\MemoryController;
use App\Http\Controllers\Api\V1\MetaController;
use App\Http\Controllers\Api\V1\ReminderController;
use App\Http\Controllers\Api\V1\SearchController;
use App\Http\Controllers\Api\V1\SessionController;
use App\Http\Controllers\Api\V1\SettingsController;
use App\Http\Controllers\Api\V1\TimelineController;
use App\Http\Controllers\Api\V1\TwoFactorController;
use App\Http\Middleware\AuthenticateApiToken;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::get('meta', MetaController::class)->name('meta');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:5,1')->name('login');
    Route::post('two-factor/challenge', [TwoFactorController::class, 'challenge'])
        ->middleware('throttle:5,1')
        ->name('two-factor.challenge');
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:3,1')->name('register');

    Route::middleware(AuthenticateApiToken::class)->group(function () {
        Route::get('me', [AuthController::class, 'me'])->name('me');
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');

        Route::get('sessions', [SessionController::class, 'index'])->name('sessions.index');
        Route::delete('sessions', [SessionController::class, 'destroyOthers'])->name('sessions.destroy-others');
        Route::delete('sessions/{token}', [SessionController::class, 'destroy'])->whereNumber('token')->name('sessions.destroy');

        Route::get('two-factor', [TwoFactorController::class, 'status'])->name('two-factor.status');
        Route::post('two-factor/setup', [TwoFactorController::class, 'setup'])->name('two-factor.setup');
        Route::post('two-factor/confirm', [TwoFactorController::class, 'confirm'])->name('two-factor.confirm');
        Route::post('two-factor/disable', [TwoFactorController::class, 'disable'])->name('two-factor.disable');

        Route::get('dashboard', DashboardController::class)->name('dashboard');

        Route::post('capture', [CaptureController::class, 'store'])->middleware('throttle:30,1')->name('capture.store');

        Route::get('memories', [MemoryController::class, 'index'])->name('memories.index');
        Route::get('memories/{memory}', [MemoryController::class, 'show'])->whereNumber('memory')->name('memories.show');
        Route::patch('memories/{memory}', [MemoryController::class, 'update'])->whereNumber('memory')->name('memories.update');
        Route::delete('memories/{memory}', [MemoryController::class, 'destroy'])->whereNumber('memory')->name('memories.destroy');
        Route::patch('memories/{memory}/favorite', [MemoryController::class, 'favorite'])->whereNumber('memory')->name('memories.favorite');
        Route::patch('memories/{memory}/archive', [MemoryController::class, 'archive'])->whereNumber('memory')->name('memories.archive');
        Route::patch('memories/{memory}/restore', [MemoryController::class, 'restore'])->whereNumber('memory')->name('memories.restore');
        Route::post('memories/{memory}/link', [MemoryController::class, 'link'])->whereNumber('memory')->name('memories.link');
        Route::delete('memories/{memory}/links/{relationship}', [MemoryController::class, 'unlink'])
            ->whereNumber(['memory', 'relationship'])
            ->name('memories.unlink');
        Route::post('memories/{memory}/reprocess', [MemoryController::class, 'reprocess'])->whereNumber('memory')->name('memories.reprocess');
        Route::post('memories/{memory}/attachments', [AttachmentController::class, 'store'])
            ->whereNumber('memory')
            ->name('memories.attachments.store');
        Route::delete('memories/{memory}/attachments/{attachment}', [AttachmentController::class, 'destroy'])
            ->whereNumber(['memory', 'attachment'])
            ->name('memories.attachments.destroy');

        Route::get('search', [SearchController::class, 'index'])->name('search');
        Route::get('timeline', [TimelineController::class, 'index'])->name('timeline');

        Route::get('reminders', [ReminderController::class, 'index'])->name('reminders.index');
        Route::post('reminders', [ReminderController::class, 'store'])->name('reminders.store');
        Route::patch('reminders/{reminder}/complete', [ReminderController::class, 'complete'])->whereNumber('reminder')->name('reminders.complete');
        Route::patch('reminders/{reminder}/snooze', [ReminderController::class, 'snooze'])->whereNumber('reminder')->name('reminders.snooze');
        Route::delete('reminders/{reminder}', [ReminderController::class, 'destroy'])->whereNumber('reminder')->name('reminders.destroy');

        Route::get('settings', [SettingsController::class, 'show'])->name('settings.show');
        Route::patch('settings/profile', [SettingsController::class, 'updateProfile'])->name('settings.profile');
        Route::patch('settings/language', [SettingsController::class, 'updateLanguage'])->name('settings.language');
        Route::patch('settings/notifications', [SettingsController::class, 'updateNotifications'])->name('settings.notifications');
        Route::patch('settings/privacy', [SettingsController::class, 'updatePrivacy'])->name('settings.privacy');
        Route::delete('account', [SettingsController::class, 'destroyAccount'])->name('account.destroy');

        Route::get('collections', [CollectionController::class, 'index'])->name('collections.index');
        Route::post('collections', [CollectionController::class, 'store'])->name('collections.store');
        Route::patch('collections/{collection}', [CollectionController::class, 'update'])->whereNumber('collection')->name('collections.update');
        Route::delete('collections/{collection}', [CollectionController::class, 'destroy'])->whereNumber('collection')->name('collections.destroy');

        Route::get('export', ExportController::class)->name('export');

        Route::get('attachments/{attachment}', [AttachmentController::class, 'show'])->whereNumber('attachment')->name('attachments.show');
        Route::get('attachments/{attachment}/download', [AttachmentController::class, 'download'])->whereNumber('attachment')->name('attachments.download');
    });
});
