<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Subscription\Console;

use WorkEddy\Platform\Console\ConsoleCommandDefinition;
use WorkEddy\Platform\Console\IConsoleCommandProvider;

final class SubscriptionConsoleCommandProvider implements IConsoleCommandProvider
{
    public function commands(): array
    {
        return [
            new ConsoleCommandDefinition(
                name: 'subscription:renewal:sweep',
                description: 'Renews active, auto-renewing subscriptions whose current billing period has ended.',
                handlerClass: SubscriptionRenewalSweepCommand::class,
            ),
            new ConsoleCommandDefinition(
                name: 'subscription:dunning:sweep',
                description: 'Suspends subscriptions whose linked invoice is unpaid past its due date.',
                handlerClass: SubscriptionDunningSweepCommand::class,
            ),
        ];
    }
}
