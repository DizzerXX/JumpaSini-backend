<?php

namespace App\Exceptions;

use RuntimeException;

class GoogleMapsException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $apiStatus = '',
    ) {
        parent::__construct($message);
    }

    public static function fromStatus(string $status): self
    {
        return new self("Google Maps API returned status: {$status}", $status);
    }
}
