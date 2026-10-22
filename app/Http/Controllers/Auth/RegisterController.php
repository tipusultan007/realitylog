<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function create(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'locale' => ['required', 'in:en,bn'],
        ]);

        $user = DB::transaction(function () use ($validated): User {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
            ]);

            UserSetting::create([
                'user_id' => $user->id,
                'interface_locale' => $validated['locale'],
                'ai_locale' => $validated['locale'],
                'timezone' => 'Asia/Dhaka',
                'country' => 'BD',
                'currency' => 'BDT',
            ]);

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'account.created',
                'subject_type' => User::class,
                'subject_id' => $user->id,
                'ip_address' => request()->ip(),
            ]);

            return $user;
        });

        event(new Registered($user));

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('locale', $validated['locale']);

        return redirect()->route('verification.notice');
    }
}
