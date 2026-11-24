<?php

namespace App\Services\Otp;

use Illuminate\Support\Facades\Log;

/** Implémentation de développement : écrit le code dans les journaux, n'envoie rien. */
class LogOtpSender implements OtpSender
{
    public function send(string $phone, string $code): void
    {
        Log::info('OTP (développement)', ['phone' => $phone, 'code' => $code]);
    }
}
