<?php

namespace App\Providers;

use App\Models\MemoryAttachment;
use App\Services\Security\ClamAvFileScanner;
use App\Services\Security\FileScanner;
use App\Services\Security\NullFileScanner;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(FileScanner::class, function (): FileScanner {
            return config('realitylog.uploads.scanner') === 'clamav'
                ? new ClamAvFileScanner((string) config('realitylog.uploads.clamav_binary'))
                : new NullFileScanner;
        });
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();

        View::composer('*', function ($view): void {
            if (! Auth::check()) {
                $view->with('interfaceLocale', app()->getLocale());

                return;
            }

            $user = Auth::user();
            $settings = $user->settings;

            $view->with([
                'interfaceLocale' => app()->getLocale(),
                'currentUser' => $user,
                'currentSettings' => $settings,
                'navReminderCount' => $user->reminders()
                    ->where('status', 'upcoming')
                    ->whereBetween('due_at', [now(), now()->addDays(14)])
                    ->count(),
                'navMemoryCount' => $user->memories()->where('is_archived', false)->count(),
                'navStorageBytes' => (int) MemoryAttachment::query()
                    ->whereHas('memory', fn ($query) => $query->where('user_id', $user->id))
                    ->sum('size'),
            ]);
        });
    }
}
