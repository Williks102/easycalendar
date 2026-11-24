<?php

namespace App\Providers;

use App\Services\Otp\LogOtpSender;
use App\Services\Otp\OtpSender;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Fournisseur d'OTP à choisir (docs/DECISIONS.md, décision 5) ; en attendant, les codes vont dans les journaux.
        $this->app->bind(OtpSender::class, LogOtpSender::class);
    }

    public function boot(): void
    {
        // Limite les coûts SMS et le brute-force : 3 demandes / 10 min par numéro, 10 par IP.
        RateLimiter::for('otp-request', fn (Request $request) => [
            Limit::perMinutes(10, 3)->by('otp-phone:'.$request->input('phone')),
            Limit::perMinutes(10, 10)->by('otp-ip:'.$request->ip()),
        ]);
        RateLimiter::for('otp-verify', fn (Request $request) => [
            Limit::perMinutes(10, 10)->by('otp-verify:'.$request->input('phone')),
            Limit::perMinutes(10, 30)->by('otp-verify-ip:'.$request->ip()),
        ]);
    }
}
