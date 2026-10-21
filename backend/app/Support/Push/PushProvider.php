<?php

namespace App\Support\Push;

/** Delivers a message to phones. Implementations must never throw for a single bad token. */
interface PushProvider
{
    /**
     * @param  list<string>  $tokens
     * @param  array<string, string>  $data  deep-link data for the app (strings only)
     * @return list<string> tokens the provider reported as no longer valid (to be deleted)
     */
    public function send(array $tokens, string $title, ?string $body, array $data = []): array;
}
