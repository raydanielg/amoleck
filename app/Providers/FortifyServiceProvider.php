<?php

namespace App\Providers;

use App\Actions\Fortify\ResetUserPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
        $this->configureLoginHistory();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        Fortify::authenticateUsing(function (Request $request) {
            $login = Str::lower($request->input('email'));
            $user = \App\Models\User::where('email', $login)->orWhere('username', $login)->first();

            if ($user && $user->status !== 'active') {
                throw ValidationException::withMessages([
                    'email' => 'This account is '.$user->status.'. Contact an administrator.',
                ]);
            }

            if ($user && $user->locked_until?->isFuture()) {
                throw ValidationException::withMessages([
                    'email' => 'Account temporarily locked. Try again later.',
                ]);
            }

            if ($user && ! \Illuminate\Support\Facades\Hash::check($request->input('password'), $user->password)) {
                $user->increment('failed_login_attempts');
                if ($user->failed_login_attempts >= 5) {
                    $user->update(['locked_until' => now()->addMinutes(15), 'failed_login_attempts' => 0]);
                }

                return null;
            }

            if ($user) {
                $user->update(['failed_login_attempts' => 0, 'locked_until' => null]);
            }

            return $user;
        });
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/login', [
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'status' => $request->session()->get('status'),
        ]));

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/reset-password', [
            'email' => $request->email,
            'token' => $request->route('token'),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('auth/forgot-password', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::verifyEmailView(fn (Request $request) => Inertia::render('auth/verify-email', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/two-factor-challenge'));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/confirm-password'));
    }

    /**
     * Configure rate limiting.
     */
    private function configureLoginHistory(): void
    {
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\Login::class, function ($event) {
            $event->user->update(['last_login_at' => now()]);
            \Modules\Foundation\Models\LoginHistory::create([
                'user_id' => $event->user->id,
                'email' => $event->user->email,
                'event' => 'login',
                'ip_address' => request()->ip(),
                'user_agent' => substr((string) request()->userAgent(), 0, 500),
                'created_at' => now(),
            ]);
            \Modules\Foundation\Support\Audit::record('auth.login');
        });

        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\Logout::class, function ($event) {
            if ($event->user) {
                \Modules\Foundation\Models\LoginHistory::create([
                    'user_id' => $event->user->id,
                    'email' => $event->user->email,
                    'event' => 'logout',
                    'ip_address' => request()->ip(),
                    'created_at' => now(),
                ]);
                \Modules\Foundation\Support\Audit::record('auth.logout');
            }
        });

        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\Failed::class, function ($event) {
            \Modules\Foundation\Models\LoginHistory::create([
                'user_id' => $event->user?->id,
                'email' => $event->credentials['email'] ?? null,
                'event' => 'failed',
                'ip_address' => request()->ip(),
                'created_at' => now(),
            ]);
        });
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });
    }
}
