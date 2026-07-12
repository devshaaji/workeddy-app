<?php

/**
 * User management controller — admin operations.
 */

declare(strict_types=1);

namespace WorkEddy\Modules\IAM\Presentation;

use WorkEddy\Modules\IAM\Application\CreateUserUseCase;
use WorkEddy\Modules\IAM\Application\UpdateUserUseCase;
use WorkEddy\Modules\IAM\Application\GetUserUseCase;
use WorkEddy\Modules\IAM\Application\SuspendUserUseCase;
use WorkEddy\Modules\IAM\Application\ActivateUserUseCase;
use WorkEddy\Modules\IAM\Application\ChangePasswordUseCase;
use WorkEddy\Modules\IAM\Application\AssignRoleUseCase;
use WorkEddy\Modules\IAM\Application\ForceLogoutUserUseCase;
use WorkEddy\Modules\IAM\Application\SoftDeleteUserUseCase;
use WorkEddy\Modules\IAM\Application\DTOs\CreateUserRequest;
use WorkEddy\Modules\IAM\Application\DTOs\UpdateUserRequest;
use WorkEddy\Modules\IAM\Application\DTOs\ChangePasswordRequest;
use WorkEddy\Modules\IAM\Application\DTOs\AssignRoleRequest;
use WorkEddy\Modules\IAM\Authorization\IAMPermissions;
use WorkEddy\Modules\IAM\Domain\Contracts\IOrganizationMembershipRepository;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Modules\IAM\Domain\Contracts\IUserRepository;
use WorkEddy\Platform\Http\Request;
use WorkEddy\Platform\Http\Response;
use WorkEddy\Platform\Session\ISessionService;
use WorkEddy\Shared\Support\UuidSupport;

final class UserController
{
    public function __construct(
        private readonly CreateUserUseCase     $createUser,
        private readonly UpdateUserUseCase     $updateUser,
        private readonly GetUserUseCase        $getUser,
        private readonly SuspendUserUseCase    $suspendUser,
        private readonly ActivateUserUseCase   $activateUser,
        private readonly ChangePasswordUseCase $changePassword,
        private readonly AssignRoleUseCase     $assignRole,
        private readonly SoftDeleteUserUseCase $softDeleteUser,
        private readonly ForceLogoutUserUseCase $forceLogoutUser,
        private readonly IUserRepository       $users,
        private readonly IOrganizationMembershipRepository $memberships,
        private readonly IPermissionService    $permissions,
        private readonly ISessionService       $session,
        private readonly IAMUserActionPolicy   $userActionPolicy,
        private readonly UserViewFactory       $userViews,
    ) {}

    public function list(Request $request): Response
    {
        $ctx = $this->session->getUserContext();
        if ($ctx === null) {
            return Response::error('Unauthenticated', 401);
        }

        $this->permissions->requirePrivilege($ctx, IAMPermissions::USER_VIEW);

        $filters = [];
        foreach (['status', 'role_slug', 'search'] as $key) {
            $value = $request->query($key);
            if ($value !== null && trim((string) $value) !== '') {
                $filters[$key] = trim((string) $value);
            }
        }
        if ($ctx->organizationUuid !== null && $ctx->organizationUuid !== '') {
            $filters['organization_uuid'] = $ctx->organizationUuid;
        }

        $limit = max(1, min(100, (int) ($request->query('limit') ?? 50)));
        $offset = max(0, (int) ($request->query('offset') ?? 0));

        $users = array_map(fn($user): array => $this->userViews->listItem($user, $ctx), $this->users->findAll($filters, $limit, $offset));

        return Response::json([
            'status' => 'ok',
            'data' => $users,
            'meta' => [
                'total' => $this->users->count($filters),
                'limit' => $limit,
                'offset' => $offset,
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Response::error('IAM user creation must be requested by the owning module.', 403);
    }

    public function show(Request $request): Response
    {
        $ctx = $this->session->getUserContext();
        if ($ctx === null) {
            return Response::error('Unauthenticated', 401);
        }
        $user = $this->requireUser((string) ($request->routeParam('id', '')), $ctx);

        $dto = $this->getUser->execute((int) $user->getId(), $ctx);

        return Response::json(['status' => 'ok', 'data' => $this->userViews->detail($dto)]);
    }

    public function update(Request $request): Response
    {
        $body = $this->requestData($request);
        $ctx = $this->session->getUserContext();
        if ($ctx === null) {
            return Response::error('Unauthenticated', 401);
        }
        $user = $this->requireUser((string) ($request->routeParam('id', '')), $ctx);

        $dto = $this->updateUser->execute(new UpdateUserRequest(
            userId: (int) $user->getId(),
            fullName: $body['fullName'] ?? $body['full_name'] ?? '',
            email: $body['email'] ?? '',
            phone: $body['phone'] ?? null,
        ), $ctx);

        return Response::json(['status' => 'ok', 'data' => ['uuid' => $dto->id]]);
    }

    public function suspend(Request $request): Response
    {
        $ctx = $this->session->getUserContext();
        if ($ctx === null) {
            return Response::error('Unauthenticated', 401);
        }
        $user = $this->requireUser((string) ($request->routeParam('id', '')), $ctx);
        $this->suspendUser->execute((int) $user->getId(), $ctx);

        return Response::json(['status' => 'ok', 'data' => $this->workflowResponse((int) $user->getId(), $ctx)]);
    }

    public function activate(Request $request): Response
    {
        $ctx = $this->session->getUserContext();
        if ($ctx === null) {
            return Response::error('Unauthenticated', 401);
        }
        $user = $this->requireUser((string) ($request->routeParam('id', '')), $ctx);
        $this->activateUser->execute((int) $user->getId(), $ctx);

        return Response::json(['status' => 'ok', 'data' => $this->workflowResponse((int) $user->getId(), $ctx)]);
    }

    public function delete(Request $request): Response
    {
        $ctx = $this->session->getUserContext();
        if ($ctx === null) {
            return Response::error('Unauthenticated', 401);
        }
        $user = $this->requireUser((string) ($request->routeParam('id', '')), $ctx);

        $this->softDeleteUser->execute((int) $user->getId(), $ctx);

        return Response::json(['status' => 'ok', 'data' => $this->workflowResponse((int) $user->getId(), $ctx)]);
    }

    public function forceLogout(Request $request): Response
    {
        $ctx = $this->session->getUserContext();
        if ($ctx === null) {
            return Response::error('Unauthenticated', 401);
        }
        $user = $this->requireUser((string) ($request->routeParam('id', '')), $ctx);

        $revoked = $this->forceLogoutUser->execute((int) $user->getId(), $ctx);

        return Response::json(['status' => 'ok', 'data' => ['revokedSessionCount' => $revoked]]);
    }

    public function changePassword(Request $request): Response
    {
        $body = $this->requestData($request);
        $ctx = $this->session->getUserContext();
        if ($ctx === null) {
            return Response::error('Unauthenticated', 401);
        }
        $user = $this->requireUser((string) ($request->routeParam('id', '')), $ctx);

        $this->changePassword->execute(new ChangePasswordRequest(
            userId: (int) $user->getId(),
            currentPassword: $body['currentPassword'] ?? $body['current_password'] ?? '',
            newPassword: $body['newPassword'] ?? $body['new_password'] ?? $body['password'] ?? '',
        ), $ctx);

        return Response::json(['status' => 'ok']);
    }

    public function assignRole(Request $request): Response
    {
        $body = $this->requestData($request);
        $ctx = $this->session->getUserContext();
        if ($ctx === null) {
            return Response::error('Unauthenticated', 401);
        }
        $user = $this->requireUser((string) ($request->routeParam('id', '')), $ctx);

        $this->assignRole->execute(new AssignRoleRequest(
            userId: (int) $user->getId(),
            roleSlug: $body['roleSlug'] ?? $body['role_slug'] ?? '',
        ), $ctx);

        return Response::json(['status' => 'ok']);
    }

    /**
     * @return array{status: string|null, actions: array<int, array<string, string>>}
     */
    private function workflowResponse(int $userId, \WorkEddy\Platform\Session\UserContext $ctx): array
    {
        $user = $this->users->findById($userId);

        return [
            'status' => $user ? $user->getStatus()->value : null,
            'actions' => $user ? $this->userActionPolicy->workflowActions($ctx, $user) : [],
        ];
    }

    private function requireUser(string $uuid, ?\WorkEddy\Platform\Session\UserContext $ctx = null): \WorkEddy\Modules\IAM\Domain\User
    {
        $user = $this->users->findByUuid(UuidSupport::requireValid($uuid));
        if ($user === null) {
            throw new \WorkEddy\Shared\Exceptions\NotFoundException('User ' . $uuid . ' not found');
        }
        if ($ctx !== null && $ctx->organizationUuid !== null && $ctx->organizationUuid !== '') {
            $membership = $this->memberships->findByUserAndOrganizationUuid((int) $user->getId(), $ctx->organizationUuid);
            if ($membership === null) {
                throw new \WorkEddy\Shared\Exceptions\NotFoundException('User ' . $uuid . ' not found');
            }
        }

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function requestData(Request $request): array
    {
        return array_replace($request->body, $request->json);
    }
}
