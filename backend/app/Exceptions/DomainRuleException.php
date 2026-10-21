<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A business rule refused an operation (e.g. tournament full, registration closed).
 * Rendered as JSON: {"message": "...", "code": "registration_closed"} with the given HTTP status.
 */
class DomainRuleException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly int $status = 422,
    ) {
        parent::__construct($message);
    }

    /**
     * @param  string  $message  English text; also the translation key in lang/{locale}.json
     * @param  array<string, string|int>  $replace  placeholder values, e.g. [':limit' => 4] as ['limit' => 4]
     */
    public static function make(string $errorCode, string $message, int $status = 422, array $replace = []): self
    {
        // Translated into the request's language (admin choice, or the app's Accept-Language).
        return new self(__($message, $replace), $errorCode, $status);
    }
}
