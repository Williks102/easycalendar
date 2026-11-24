<?php

namespace App\Services\Otp;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Facades\Hash;

class OtpService
{
    public const TTL_SECONDS = 600;

    public const MAX_ATTEMPTS = 5;

    public function __construct(private OtpSender $sender, private Cache $cache) {}

    public function request(string $phone): void
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $this->cache->put($this->key($phone), ['hash' => Hash::make($code), 'attempts' => 0], self::TTL_SECONDS);

        $this->sender->send($phone, $code);
    }

    /** Vérifie le code ; il est à usage unique et se bloque après MAX_ATTEMPTS essais. */
    public function verify(string $phone, string $code): bool
    {
        $entry = $this->cache->get($this->key($phone));
        if (! $entry) {
            return false;
        }

        if ($entry['attempts'] >= self::MAX_ATTEMPTS) {
            $this->cache->forget($this->key($phone));

            return false;
        }

        if (! Hash::check($code, $entry['hash'])) {
            $entry['attempts']++;
            $this->cache->put($this->key($phone), $entry, self::TTL_SECONDS);

            return false;
        }

        $this->cache->forget($this->key($phone));

        return true;
    }

    private function key(string $phone): string
    {
        return 'otp:'.$phone;
    }
}
