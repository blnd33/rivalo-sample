<?php

namespace App\Filament\Concerns;

use App\Exceptions\DomainRuleException;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;

/**
 * Runs a service call and turns business-rule refusals into a visible admin notification
 * instead of a server error. The action/page is halted so nothing is half-saved.
 */
trait HandlesDomainRules
{
    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    protected static function attempt(callable $callback): mixed
    {
        try {
            return $callback();
        } catch (DomainRuleException $e) {
            Notification::make()
                ->danger()
                ->title(__('Not allowed'))
                ->body($e->getMessage())
                ->persistent()
                ->send();

            throw new Halt;
        }
    }
}
