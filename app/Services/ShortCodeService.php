<?php

namespace App\Services;

use App\Models\Session;
use RuntimeException;

class ShortCodeService
{
    private const int    LENGTH       = 8;
    private const string CHARSET      = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    private const int    MAX_ATTEMPTS = 5;

    public function generate(): string
    {
        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            $code = $this->randomCode();

            if (!Session::where('short_code', $code)->exists()) {
                return $code;
            }
        }

        throw new RuntimeException(
            'Failed to generate a unique short code after ' . self::MAX_ATTEMPTS . ' attempts.'
        );
    }

    private function randomCode(): string
    {
        $charset = self::CHARSET;
        $length  = strlen($charset);
        $code    = '';

        for ($i = 0; $i < self::LENGTH; $i++) {
            $code .= $charset[random_int(0, $length - 1)];
        }

        return $code;
    }
}
