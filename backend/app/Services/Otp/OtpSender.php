<?php

namespace App\Services\Otp;

/**
 * Envoie un code OTP à un numéro. Le fournisseur SMS/WhatsApp n'est pas encore choisi
 * (docs/DECISIONS.md, décision 5) : toute implémentation passe par cette interface.
 */
interface OtpSender
{
    public function send(string $phone, string $code): void;
}
