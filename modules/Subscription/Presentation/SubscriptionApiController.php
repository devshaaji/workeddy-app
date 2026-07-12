<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Subscription\Presentation;

use WorkEddy\Modules\Subscription\Application\UseCases\ActivateSubscription;
use WorkEddy\Modules\Subscription\Application\UseCases\CancelSubscription;
use WorkEddy\Modules\Subscription\Application\UseCases\ChangeSubscriptionPlan;
use WorkEddy\Modules\Subscription\Application\UseCases\CreateSubscriptionPlan;
use WorkEddy\Modules\Subscription\Application\UseCases\ExpireSubscription;
use WorkEddy\Modules\Subscription\Application\UseCases\ReactivateSubscription;
use WorkEddy\Modules\Subscription\Application\UseCases\SuspendSubscription;
use WorkEddy\Modules\Subscription\Application\UseCases\UpdateSubscriptionPlan;
use WorkEddy\Modules\Subscription\Domain\Contracts\ISubscriptionPlanRepository;
use WorkEddy\Modules\Subscription\Domain\Contracts\ISubscriptionRepository;
use WorkEddy\Modules\Subscription\Domain\Contracts\ISubscriptionUsageRepository;
use WorkEddy\Platform\Clock\IClock;
use WorkEddy\Platform\Http\Request;
use WorkEddy\Platform\Http\Response;
use WorkEddy\Platform\Session\ISessionService;

final class SubscriptionApiController
{
    public function __construct(
        private readonly ISubscriptionRepository $repository,
        private readonly ISubscriptionPlanRepository $plans,
        private readonly ISubscriptionUsageRepository $usage,
        private readonly CreateSubscriptionPlan $createSubscriptionPlan,
        private readonly UpdateSubscriptionPlan $updateSubscriptionPlan,
        private readonly ActivateSubscription $activateSubscription,
        private readonly SuspendSubscription $suspendSubscription,
        private readonly ReactivateSubscription $reactivateSubscription,
        private readonly ExpireSubscription $expireSubscription,
        private readonly CancelSubscription $cancelSubscription,
        private readonly ChangeSubscriptionPlan $changeSubscriptionPlan,
        private readonly IClock $clock,
        private readonly ISessionService $session,
    ) {}

    public function listPlans(Request $request): Response
    {
        return Response::success([
            'plans' => array_map(static fn($plan): array => $plan->toArray(), $this->plans->listActive()),
        ]);
    }

    public function createPlan(Request $request): Response
    {
        $plan = $this->createSubscriptionPlan->execute(array_merge($request->body, $request->json));

        return Response::success(['plan' => $plan->toArray()], 'Subscription plan created.', 201);
    }

    public function updatePlan(Request $request): Response
    {
        $plan = $this->updateSubscriptionPlan->execute(
            (string) $request->routeParam('code'),
            array_merge($request->body, $request->json),
        );

        return Response::success(['plan' => $plan->toArray()], 'Subscription plan updated.');
    }

    public function listSubscriptions(Request $request): Response
    {
        $filters = array_merge($request->query, $request->body, $request->json);

        return Response::success([
            'subscriptions' => array_map(
                static fn($subscription): array => $subscription->toArray(),
                $this->repository->listSubscriptions([
                    'organization_id' => isset($filters['organization_id']) ? (int) $filters['organization_id'] : null,
                    'status' => $filters['status'] ?? null,
                ]),
            ),
        ]);
    }

    public function view(Request $request): Response
    {
        $subscription = $this->repository->findSubscriptionByUuid((string) $request->routeParam('uuid'));
        if ($subscription === null) {
            return Response::error('Subscription not found.', 404);
        }

        return Response::success(['subscription' => $subscription->toArray()]);
    }

    public function usage(Request $request): Response
    {
        $subscription = $this->repository->findSubscriptionByUuid((string) $request->routeParam('uuid'));
        if ($subscription === null) {
            return Response::error('Subscription not found.', 404);
        }

        $usage = $this->usage->getCurrentPeriodUsage($subscription->uuid, $this->clock->now());

        return Response::success(['usage' => $usage->toArray()]);
    }

    public function activate(Request $request): Response
    {
        $payload = array_merge($request->body, $request->json);
        $payload['actor_id'] = $this->userId();
        $subscription = $this->activateSubscription->execute($payload);

        return Response::success(['subscription' => $subscription->toArray()], 'Subscription activated.', 201);
    }

    public function suspend(Request $request): Response
    {
        $subscription = $this->suspendSubscription->execute(
            (string) $request->routeParam('uuid'),
            $request->input('reason') !== null ? (string) $request->input('reason') : null,
            $this->userId(),
        );

        return Response::success(['subscription' => $subscription->toArray()], 'Subscription suspended.');
    }

    public function reactivate(Request $request): Response
    {
        $subscription = $this->reactivateSubscription->execute((string) $request->routeParam('uuid'), $this->userId());

        return Response::success(['subscription' => $subscription->toArray()], 'Subscription reactivated.');
    }

    public function expire(Request $request): Response
    {
        $subscription = $this->expireSubscription->execute((string) $request->routeParam('uuid'), $this->userId());

        return Response::success(['subscription' => $subscription->toArray()], 'Subscription expired.');
    }

    public function cancel(Request $request): Response
    {
        $subscription = $this->cancelSubscription->execute(
            (string) $request->routeParam('uuid'),
            $request->input('reason') !== null ? (string) $request->input('reason') : null,
            $this->userId(),
        );

        return Response::success(['subscription' => $subscription->toArray()], 'Subscription cancelled.');
    }

    public function changePlan(Request $request): Response
    {
        $payload = array_merge($request->body, $request->json);
        $subscription = $this->changeSubscriptionPlan->execute(
            (string) $request->routeParam('uuid'),
            (string) ($payload['plan_code'] ?? ''),
            $this->userId(),
        );

        return Response::success(['subscription' => $subscription->toArray()], 'Subscription plan changed.');
    }

    private function userId(): ?string
    {
        return $this->session->getUserContext() !== null
            ? (string) $this->session->getUserContext()->userId
            : null;
    }
}
