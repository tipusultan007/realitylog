<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Auth\TwoFactorController as WebTwoFactorController;
use App\Http\Controllers\CaptureController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MemoryController;
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TimelineController;
use App\Http\Controllers\TwoFactorSettingsController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return Auth::check()
        ? redirect()->route('dashboard')
        : view('welcome');
})->name('home');

Route::view('/privacy', 'legal.privacy')->name('privacy');
Route::post('/locale/{locale}', LocaleController::class)->whereIn('locale', ['en', 'bn'])->name('locale.update');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:5,1')->name('login.store');
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:3,1')->name('register.store');

    Route::get('/forgot-password', [ForgotPasswordController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'store'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [ResetPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [ResetPasswordController::class, 'store'])->middleware('throttle:5,1')->name('password.store');

    Route::get('/two-factor/challenge', [WebTwoFactorController::class, 'show'])->name('two-factor.challenge');
    Route::post('/two-factor/challenge', [WebTwoFactorController::class, 'store'])->middleware('throttle:5,1')->name('two-factor.verify');
    Route::post('/two-factor/cancel', [WebTwoFactorController::class, 'cancel'])->name('two-factor.cancel');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/email/verify', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'send'])
        ->middleware('throttle:6,1')
        ->name('verification.send');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/capture', [CaptureController::class, 'create'])->name('capture.create');
    Route::post('/capture', [CaptureController::class, 'store'])->middleware('throttle:30,1')->name('capture.store');

    Route::get('/search', [SearchController::class, 'index'])->name('search.index');
    Route::get('/timeline', [TimelineController::class, 'index'])->name('timeline.index');

    Route::get('/memories', [MemoryController::class, 'index'])->name('memories.index');
    Route::get('/favorites', [MemoryController::class, 'favorites'])->name('memories.favorites');
    Route::get('/archive', [MemoryController::class, 'archived'])->name('memories.archived');
    Route::get('/memories/{memory}', [MemoryController::class, 'show'])->name('memories.show');
    Route::get('/memories/{memory}/edit', [MemoryController::class, 'edit'])->name('memories.edit');
    Route::patch('/memories/{memory}', [MemoryController::class, 'update'])->name('memories.update');
    Route::delete('/memories/{memory}', [MemoryController::class, 'destroy'])->name('memories.destroy');
    Route::patch('/memories/{memory}/favorite', [MemoryController::class, 'favorite'])->name('memories.favorite');
    Route::patch('/memories/{memory}/archive', [MemoryController::class, 'archive'])->name('memories.archive');
    Route::patch('/memories/{memory}/restore', [MemoryController::class, 'restore'])->name('memories.restore');
    Route::post('/memories/{memory}/link', [MemoryController::class, 'link'])->name('memories.link');
    Route::post('/memories/{memory}/reprocess', [MemoryController::class, 'reprocess'])->name('memories.reprocess');

    Route::get('/reminders', [ReminderController::class, 'index'])->name('reminders.index');
    Route::post('/reminders', [ReminderController::class, 'store'])->name('reminders.store');
    Route::patch('/reminders/{reminder}/complete', [ReminderController::class, 'complete'])->name('reminders.complete');
    Route::patch('/reminders/{reminder}/snooze', [ReminderController::class, 'snooze'])->name('reminders.snooze');
    Route::delete('/reminders/{reminder}', [ReminderController::class, 'destroy'])->name('reminders.destroy');

    Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::patch('/settings/profile', [SettingsController::class, 'updateProfile'])->name('settings.profile');
    Route::patch('/settings/language', [SettingsController::class, 'updateLanguage'])->name('settings.language');
    Route::patch('/settings/notifications', [SettingsController::class, 'updateNotifications'])->name('settings.notifications');
    Route::patch('/settings/privacy', [SettingsController::class, 'updatePrivacy'])->name('settings.privacy');
    Route::patch('/settings/password', [SettingsController::class, 'updatePassword'])->name('settings.password');
    Route::get('/settings/two-factor', [TwoFactorSettingsController::class, 'show'])->name('settings.two-factor.show');
    Route::post('/settings/two-factor', [TwoFactorSettingsController::class, 'confirm'])->name('settings.two-factor.confirm');
    Route::delete('/settings/two-factor', [TwoFactorSettingsController::class, 'destroy'])->name('settings.two-factor.destroy');
    Route::delete('/settings/account', [SettingsController::class, 'destroyAccount'])->name('settings.account.destroy');

    Route::get('/export', ExportController::class)->name('export');
    Route::get('/attachments/{attachment}', [AttachmentController::class, 'show'])->name('attachments.show');
    Route::get('/attachments/{attachment}/download', [AttachmentController::class, 'download'])->name('attachments.download');
    Route::view('/help', 'help')->name('help');
});
