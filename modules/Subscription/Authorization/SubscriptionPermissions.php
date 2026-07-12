<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Subscription\Authorization;

final class SubscriptionPermissions
{
    public const VIEW = 'subscription.view';
    public const MANAGE = 'subscription.manage';
    public const ACTIVATE = 'subscription.activate';
    public const SUSPEND = 'subscription.suspend';
    public const REACTIVATE = 'subscription.reactivate';
    public const EXPIRE = 'subscription.expire';
    public const CHANGE_PLAN = 'subscription.change_plan';
    public const CANCEL = 'subscription.cancel';
    public const VIEW_USAGE = 'subscription.view_usage';
    public const MANAGE_PLANS = 'subscription.manage_plans';
}
