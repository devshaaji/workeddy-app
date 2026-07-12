<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Subscription\Authorization;

use WorkEddy\Platform\Authorization\IPermissionDefinitionProvider;
use WorkEddy\Platform\Authorization\PermissionDefinition;

final class SubscriptionPermissionDefinitionProvider implements IPermissionDefinitionProvider
{
    public function definitions(): array
    {
        return [
            new PermissionDefinition('subscription', SubscriptionPermissions::VIEW, 'View Subscriptions', 'Can view subscriptions and plans.', 'read'),
            new PermissionDefinition('subscription', SubscriptionPermissions::MANAGE, 'Manage Subscriptions', 'Can create and edit subscriptions.', 'write'),
            new PermissionDefinition('subscription', SubscriptionPermissions::ACTIVATE, 'Activate Subscriptions', 'Can activate an organization subscription.', 'write'),
            new PermissionDefinition('subscription', SubscriptionPermissions::SUSPEND, 'Suspend Subscriptions', 'Can suspend an active subscription.', 'write'),
            new PermissionDefinition('subscription', SubscriptionPermissions::REACTIVATE, 'Reactivate Subscriptions', 'Can reactivate a suspended subscription.', 'write'),
            new PermissionDefinition('subscription', SubscriptionPermissions::EXPIRE, 'Expire Subscriptions', 'Can force-expire a subscription.', 'write'),
            new PermissionDefinition('subscription', SubscriptionPermissions::CHANGE_PLAN, 'Change Subscription Plan', 'Can upgrade or downgrade an organization subscription.', 'write'),
            new PermissionDefinition('subscription', SubscriptionPermissions::CANCEL, 'Cancel Subscriptions', 'Can cancel an organization subscription.', 'write'),
            new PermissionDefinition('subscription', SubscriptionPermissions::VIEW_USAGE, 'View Subscription Usage', 'Can view current-period usage against plan limits.', 'read'),
            new PermissionDefinition('subscription', SubscriptionPermissions::MANAGE_PLANS, 'Manage Subscription Plans', 'Can create and edit SaaS tier plan definitions.', 'admin'),
        ];
    }
}
