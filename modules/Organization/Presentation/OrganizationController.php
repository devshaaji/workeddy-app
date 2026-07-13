<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Organization\Presentation;

use WorkEddy\Modules\Organization\Application\CreateOrganizationUseCase;
use WorkEddy\Modules\Organization\Application\UpdateOrganizationStatusUseCase;
use WorkEddy\Modules\Organization\Application\CreateDepartmentUseCase;
use WorkEddy\Modules\Organization\Application\CreateJobRoleUseCase;
use WorkEddy\Modules\Organization\Application\CreateWorksiteUseCase;
use WorkEddy\Modules\Organization\Application\EnrollPilotSiteUseCase;
use WorkEddy\Modules\Organization\Application\InviteOrganizationMemberUseCase;
use WorkEddy\Modules\Organization\Application\ListDepartmentsUseCase;
use WorkEddy\Modules\Organization\Application\ListJobRolesUseCase;
use WorkEddy\Modules\Organization\Application\ListPilotSitesUseCase;
use WorkEddy\Modules\Organization\Application\ListWorksitesUseCase;
use WorkEddy\Modules\Organization\Application\UpdateDepartmentUseCase;
use WorkEddy\Modules\Organization\Application\UpdateJobRoleUseCase;
use WorkEddy\Modules\Organization\Application\UpdatePilotSiteUseCase;
use WorkEddy\Modules\Organization\Application\UpdateWorksiteUseCase;
use WorkEddy\Modules\IAM\Domain\Contracts\IOrganizationMembershipRepository;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Modules\IAM\Domain\Contracts\IRoleRepository;
use WorkEddy\Modules\IAM\Domain\Contracts\IUserRepository;
use WorkEddy\Modules\Organization\Authorization\OrganizationPermissions;
use WorkEddy\Modules\Organization\Domain\Contracts\IDepartmentRepository;
use WorkEddy\Modules\Organization\Domain\Contracts\IJobRoleRepository;
use WorkEddy\Modules\Organization\Domain\Contracts\IOrganizationRepository;
use WorkEddy\Modules\Organization\Domain\Contracts\IWorksiteRepository;
use WorkEddy\Modules\Organization\Domain\Organization;
use WorkEddy\Platform\Http\Request;
use WorkEddy\Platform\Http\Response;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Session\ISessionService;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Shared\Exceptions\NotFoundException;
use WorkEddy\Shared\Exceptions\WrongScopeException;

final class OrganizationController
{
    public function __construct(
        private readonly CreateOrganizationUseCase $createOrganization,
        private readonly UpdateOrganizationStatusUseCase $updateOrganizationStatus,
        private readonly InviteOrganizationMemberUseCase $inviteOrganizationMember,
        private readonly CreateWorksiteUseCase $createWorksite,
        private readonly ListWorksitesUseCase $listWorksites,
        private readonly UpdateWorksiteUseCase $updateWorksite,
        private readonly EnrollPilotSiteUseCase $enrollPilotSite,
        private readonly ListPilotSitesUseCase $listPilotSites,
        private readonly UpdatePilotSiteUseCase $updatePilotSite,
        private readonly CreateDepartmentUseCase $createDepartment,
        private readonly ListDepartmentsUseCase $listDepartments,
        private readonly UpdateDepartmentUseCase $updateDepartment,
        private readonly CreateJobRoleUseCase $createJobRole,
        private readonly ListJobRolesUseCase $listJobRoles,
        private readonly UpdateJobRoleUseCase $updateJobRole,
        private readonly IOrganizationRepository $organizations,
        private readonly IPermissionService $permissions,
        private readonly IAuditService $audit,
        private readonly ISessionService $session,
        private readonly IWorksiteRepository $worksites,
        private readonly IDepartmentRepository $departments,
        private readonly IJobRoleRepository $jobRoles,
        private readonly IOrganizationMembershipRepository $memberships,
        private readonly IUserRepository $users,
        private readonly IRoleRepository $roles,
    ) {}

    public function list(Request $request): Response
    {
        $ctx = $this->session->getUserContext();
        if ($ctx === null) {
            return Response::error('Unauthenticated', 401);
        }
        $this->permissions->requirePrivilege($ctx, OrganizationPermissions::VIEW);

        if ($ctx->organizationId !== null) {
            $organization = $this->organizations->findById((int) $ctx->organizationId);

            return Response::json(['status' => 'ok', 'data' => $organization === null ? [] : [$this->serializeOrganization($organization)]]);
        }

        $limit = max(1, min(100, (int) ($request->query('limit') ?? 50)));
        $offset = max(0, (int) ($request->query('offset') ?? 0));
        $rows = array_map(fn($organization): array => $this->serializeOrganization($organization), $this->organizations->findAll($limit, $offset));

        return Response::json(['status' => 'ok', 'data' => $rows]);
    }

    public function show(Request $request): Response
    {
        $ctx = $this->requireContext();
        $this->permissions->requirePrivilege($ctx, OrganizationPermissions::VIEW);

        return Response::json(['status' => 'ok', 'data' => $this->serializeOrganization($this->requireOrganization((string) ($request->routeParam('id') ?? ''), $ctx))]);
    }

    public function update(Request $request): Response
    {
        $ctx = $this->requireContext();
        $this->permissions->requirePrivilege($ctx, OrganizationPermissions::MANAGE);
        $existing = $this->requireOrganization((string) ($request->routeParam('id') ?? ''), $ctx);
        $body = $this->requestData($request);
        $before = $this->serializeOrganization($existing);

        $updated = new Organization(
            id: $existing->getId(),
            uuid: $existing->getUuid(),
            name: trim((string) ($body['name'] ?? $existing->getName())),
            slug: trim((string) ($body['slug'] ?? $existing->getSlug())),
            status: trim((string) ($body['status'] ?? $existing->getStatus())),
            contactEmail: isset($body['contactEmail']) ? (string) $body['contactEmail'] : (isset($body['contact_email']) ? (string) $body['contact_email'] : $existing->getContactEmail()),
            phone: isset($body['phone']) ? (string) $body['phone'] : $existing->getPhone(),
            createdAt: $existing->getCreatedAt(),
        );
        $this->organizations->update($updated);
        $after = $this->serializeOrganization($updated);
        $this->audit->record('organization.updated', 'organization', $updated->getUuid(), beforeState: $before, afterState: $after, actorId: (string) $ctx->userId, actorType: 'user');

        return Response::json(['status' => 'ok', 'data' => $after]);
    }

    public function updateStatus(Request $request): Response
    {
        $ctx = $this->requireContext();
        $body = $this->requestData($request);

        $result = $this->updateOrganizationStatus->execute(
            organizationUuid: (string) ($request->routeParam('id') ?? ''),
            newStatus: (string) ($body['status'] ?? ''),
            actor: $ctx,
            reason: isset($body['reason']) ? (string) $body['reason'] : null,
        );

        return Response::json(['status' => 'ok', 'data' => $result]);
    }

    public function create(Request $request): Response
    {
        $ctx = $this->session->getUserContext();
        if ($ctx === null) {
            return Response::error('Unauthenticated', 401);
        }

        $body = $this->requestData($request);
        $result = $this->createOrganization->execute(
            name: (string) ($body['name'] ?? ''),
            contactEmail: isset($body['contactEmail']) ? (string) $body['contactEmail'] : (isset($body['contact_email']) ? (string) $body['contact_email'] : null),
            actor: $ctx,
            phone: isset($body['phone']) ? (string) $body['phone'] : null,
        );

        return Response::json(['status' => 'ok', 'data' => $result], 201);
    }

    public function inviteMember(Request $request): Response
    {
        $ctx = $this->session->getUserContext();
        if ($ctx === null) {
            return Response::error('Unauthenticated', 401);
        }

        $body = $this->requestData($request);
        $result = $this->inviteOrganizationMember->execute(
            organizationUuid: (string) ($request->routeParam('id', '') ?? ''),
            email: (string) ($body['email'] ?? ''),
            fullName: (string) ($body['fullName'] ?? $body['full_name'] ?? ''),
            phone: isset($body['phone']) ? (string) $body['phone'] : null,
            roleSlug: (string) ($body['roleSlug'] ?? $body['role_slug'] ?? ''),
            actor: $ctx,
        );

        return Response::json(['status' => 'ok', 'data' => $result], 201);
    }

    public function listAssignableRoles(Request $request): Response
    {
        $ctx = $this->requireContext();
        $this->permissions->requirePrivilege($ctx, OrganizationPermissions::MEMBERS_MANAGE);
        $this->requireOrganization((string) ($request->routeParam('id') ?? ''), $ctx);

        $roles = array_values(array_filter(
            $this->requireRoles()->findAll(),
            static fn(\WorkEddy\Modules\IAM\Domain\Role $role): bool => strtolower($role->getScope()) === 'customer'
        ));

        usort($roles, static function (\WorkEddy\Modules\IAM\Domain\Role $left, \WorkEddy\Modules\IAM\Domain\Role $right): int {
            return strcasecmp($left->getName(), $right->getName());
        });

        return Response::json([
            'status' => 'ok',
            'data' => array_map(
                fn(\WorkEddy\Modules\IAM\Domain\Role $role): array => $this->serializeAssignableRole($role),
                $roles,
            ),
        ]);
    }

    public function listMembers(Request $request): Response
    {
        $ctx = $this->requireContext();
        $this->permissions->requirePrivilege($ctx, OrganizationPermissions::VIEW);
        $organization = $this->requireOrganization((string) ($request->routeParam('id') ?? ''), $ctx);
        $memberships = $this->requireMemberships();
        $users = $this->requireUsers();

        $membershipRows = $memberships->findAllByOrganizationId((int) $organization->getId(), max(1, min(100, (int) ($request->query('limit') ?? 50))), max(0, (int) ($request->query('offset') ?? 0)));
        $userRows = $users->findAll(['organization_uuid' => $organization->getUuid()], 500, 0);
        $usersById = [];
        foreach ($userRows as $user) {
            $usersById[(int) $user->getId()] = $user;
        }

        return Response::json(['status' => 'ok', 'data' => array_map(function ($membership) use ($usersById): array {
            $row = [
                'id' => $membership->getUuid(),
                'userId' => $membership->getUserId(),
                'organizationId' => $membership->getOrganizationId(),
                'organizationUuid' => $membership->getOrganizationUuid(),
                'roleSlug' => $membership->getRoleSlug(),
                'status' => $membership->getStatus(),
                'worksiteId' => $membership->getWorksiteId(),
                'departmentId' => $membership->getDepartmentId(),
                'jobRoleId' => $membership->getJobRoleId(),
                'isPrimary' => $membership->isPrimary(),
            ];

            $user = $usersById[$membership->getUserId()] ?? null;
            $row['user'] = $user === null ? null : [
                'id' => $user->getId(),
                'uuid' => $user->getUuid(),
                'email' => $user->getEmail(),
                'fullName' => $user->getFullName(),
                'phone' => $user->getPhone(),
            ];

            return $row;
        }, $membershipRows)]);
    }

    public function updateMember(Request $request): Response
    {
        $ctx = $this->requireContext();
        $this->permissions->requirePrivilege($ctx, OrganizationPermissions::MEMBERS_MANAGE);
        $organization = $this->requireOrganization((string) ($request->routeParam('id') ?? ''), $ctx);
        $memberships = $this->requireMemberships();
        $membership = $memberships->findByUuid((string) ($request->routeParam('memberId') ?? ''));
        if ($membership === null) {
            throw new NotFoundException('Organization member not found.');
        }
        if ($membership->getOrganizationId() !== $organization->getId()) {
            throw new WrongScopeException('This organization member belongs to a different organization scope.');
        }
        $before = $this->serializeMembership($membership);
        $body = $this->requestData($request);
        if (isset($body['roleSlug']) || isset($body['role_slug'])) {
            $roleSlug = (string) ($body['roleSlug'] ?? $body['role_slug']);
            $role = $this->requireRoles()->findBySlug($roleSlug);
            if ($role === null || strtolower($role->getScope()) !== 'customer') {
                throw new NotFoundException('Assignable organization role not found.');
            }
            $membership->assignRole((int) $role->getId(), $role->getSlug());
        }
        $memberships->update($membership);
        $after = $this->serializeMembership($membership);
        $this->audit->record('organization.member.updated', 'organization_membership', $membership->getUuid(), beforeState: $before, afterState: $after, actorId: (string) $ctx->userId, actorType: 'user');

        return Response::json(['status' => 'ok', 'data' => $after]);
    }

    public function removeMember(Request $request): Response
    {
        $ctx = $this->requireContext();
        $this->permissions->requirePrivilege($ctx, OrganizationPermissions::MEMBERS_MANAGE);
        $organization = $this->requireOrganization((string) ($request->routeParam('id') ?? ''), $ctx);
        $memberships = $this->requireMemberships();
        $membership = $memberships->findByUuid((string) ($request->routeParam('memberId') ?? ''));
        if ($membership === null) {
            throw new NotFoundException('Organization member not found.');
        }
        if ($membership->getOrganizationId() !== $organization->getId()) {
            throw new WrongScopeException('This organization member belongs to a different organization scope.');
        }
        $before = $this->serializeMembership($membership);
        $memberships->delete($membership->getUuid());
        $this->audit->record('organization.member.removed', 'organization_membership', $membership->getUuid(), beforeState: $before, afterState: ['id' => $membership->getUuid(), 'deleted' => true], actorId: (string) $ctx->userId, actorType: 'user');

        return Response::json(['status' => 'ok', 'data' => ['id' => $membership->getUuid(), 'deleted' => true]]);
    }

    public function listWorksites(Request $request): Response
    {
        $ctx = $this->requireContext();

        return Response::json(['status' => 'ok', 'data' => $this->listWorksites->execute(
            organizationUuid: (string) ($request->routeParam('id') ?? ''),
            actor: $ctx,
            limit: max(1, min(100, (int) ($request->query('limit') ?? 50))),
            offset: max(0, (int) ($request->query('offset') ?? 0)),
        )]);
    }

    public function createWorksite(Request $request): Response
    {
        $ctx = $this->requireContext();
        $body = $this->requestData($request);

        return Response::json(['status' => 'ok', 'data' => $this->createWorksite->execute(
            organizationUuid: (string) ($request->routeParam('id') ?? ''),
            name: (string) ($body['name'] ?? ''),
            actor: $ctx,
            location: isset($body['location']) ? (string) $body['location'] : null,
        )], 201);
    }

    public function updateWorksite(Request $request): Response
    {
        $ctx = $this->requireContext();
        $body = $this->requestData($request);

        return Response::json(['status' => 'ok', 'data' => $this->updateWorksite->execute(
            organizationUuid: (string) ($request->routeParam('id') ?? ''),
            worksiteUuid: (string) ($request->routeParam('worksiteId') ?? ''),
            actor: $ctx,
            name: (string) ($body['name'] ?? ''),
            status: isset($body['status']) ? (string) $body['status'] : null,
            location: isset($body['location']) ? (string) $body['location'] : null,
        )]);
    }

    public function showWorksite(Request $request): Response
    {
        $ctx = $this->requireContext();
        $this->permissions->requirePrivilege($ctx, OrganizationPermissions::VIEW);
        $organization = $this->requireOrganization((string) ($request->routeParam('id') ?? ''), $ctx);
        $worksite = $this->requireWorksites()->findByUuid((string) ($request->routeParam('worksiteId') ?? ''));
        if ($worksite === null) {
            throw new NotFoundException('Worksite not found.');
        }
        if ($worksite->getOrganizationId() !== $organization->getId()) {
            throw new WrongScopeException('This worksite belongs to a different organization scope.');
        }

        return Response::json(['status' => 'ok', 'data' => [
            'id' => $worksite->getUuid(),
            'organizationId' => $organization->getUuid(),
            'name' => $worksite->getName(),
            'status' => $worksite->getStatus(),
            'location' => $worksite->getLocation(),
        ]]);
    }

    public function deleteWorksite(Request $request): Response
    {
        $ctx = $this->requireContext();
        $this->permissions->requirePrivilege($ctx, OrganizationPermissions::STRUCTURE_MANAGE);
        $organization = $this->requireOrganization((string) ($request->routeParam('id') ?? ''), $ctx);
        $repo = $this->requireWorksites();
        $worksite = $repo->findByUuid((string) ($request->routeParam('worksiteId') ?? ''));
        if ($worksite === null) {
            throw new NotFoundException('Worksite not found.');
        }
        if ($worksite->getOrganizationId() !== $organization->getId()) {
            throw new WrongScopeException('This worksite belongs to a different organization scope.');
        }
        $before = $this->serializeWorksite($worksite, $organization->getUuid());
        $repo->delete($worksite->getUuid());
        $this->audit->record('organization.worksite.deleted', 'worksite', $worksite->getUuid(), beforeState: $before, afterState: ['id' => $worksite->getUuid(), 'deleted' => true], actorId: (string) $ctx->userId, actorType: 'user');

        return Response::json(['status' => 'ok', 'data' => ['id' => $worksite->getUuid(), 'deleted' => true]]);
    }

    public function listDepartments(Request $request): Response
    {
        $ctx = $this->requireContext();

        return Response::json(['status' => 'ok', 'data' => $this->listDepartments->execute(
            organizationUuid: (string) ($request->routeParam('id') ?? ''),
            actor: $ctx,
            limit: max(1, min(100, (int) ($request->query('limit') ?? 50))),
            offset: max(0, (int) ($request->query('offset') ?? 0)),
        )]);
    }

    public function listPilotSites(Request $request): Response
    {
        $ctx = $this->requireContext();

        return Response::json(['status' => 'ok', 'data' => $this->listPilotSites->execute(
            organizationUuid: (string) ($request->routeParam('id') ?? ''),
            actor: $ctx,
            filters: [
                'worksiteUuid' => $request->query['worksiteUuid'] ?? $request->query['worksite_uuid'] ?? null,
                'pilotStatus' => $request->query['pilotStatus'] ?? $request->query['pilot_status'] ?? null,
                'industry' => $request->query['industry'] ?? null,
            ],
        )]);
    }

    public function createPilotSite(Request $request): Response
    {
        $ctx = $this->requireContext();
        $body = $this->requestData($request);

        return Response::json(['status' => 'ok', 'data' => $this->enrollPilotSite->execute(
            organizationUuid: (string) ($request->routeParam('id') ?? ''),
            worksiteUuid: (string) ($body['worksiteId'] ?? $body['worksite_id'] ?? ''),
            enrollmentDate: (string) ($body['enrollmentDate'] ?? $body['enrollment_date'] ?? ''),
            actor: $ctx,
            pilotStatus: (string) ($body['pilotStatus'] ?? $body['pilot_status'] ?? 'enrolled'),
            targetWorkerCount: (int) ($body['targetWorkerCount'] ?? $body['target_worker_count'] ?? 0),
            actualWorkerCount: (int) ($body['actualWorkerCount'] ?? $body['actual_worker_count'] ?? 0),
            industry: isset($body['industry']) ? (string) $body['industry'] : null,
            notes: isset($body['notes']) ? (string) $body['notes'] : null,
        )], 201);
    }

    public function updatePilotSite(Request $request): Response
    {
        $ctx = $this->requireContext();
        $body = $this->requestData($request);

        return Response::json(['status' => 'ok', 'data' => $this->updatePilotSite->execute(
            organizationUuid: (string) ($request->routeParam('id') ?? ''),
            pilotSiteUuid: (string) ($request->routeParam('pilotSiteId') ?? ''),
            actor: $ctx,
            enrollmentDate: isset($body['enrollmentDate']) || isset($body['enrollment_date']) ? (string) ($body['enrollmentDate'] ?? $body['enrollment_date']) : null,
            pilotStatus: isset($body['pilotStatus']) || isset($body['pilot_status']) ? (string) ($body['pilotStatus'] ?? $body['pilot_status']) : null,
            targetWorkerCount: isset($body['targetWorkerCount']) || isset($body['target_worker_count']) ? (int) ($body['targetWorkerCount'] ?? $body['target_worker_count']) : null,
            actualWorkerCount: isset($body['actualWorkerCount']) || isset($body['actual_worker_count']) ? (int) ($body['actualWorkerCount'] ?? $body['actual_worker_count']) : null,
            industry: isset($body['industry']) ? (string) $body['industry'] : null,
            notes: isset($body['notes']) ? (string) $body['notes'] : null,
        )]);
    }

    public function createDepartment(Request $request): Response
    {
        $ctx = $this->requireContext();
        $body = $this->requestData($request);

        return Response::json(['status' => 'ok', 'data' => $this->createDepartment->execute(
            organizationUuid: (string) ($request->routeParam('id') ?? ''),
            name: (string) ($body['name'] ?? ''),
            actor: $ctx,
            worksiteUuid: isset($body['worksiteId']) ? (string) $body['worksiteId'] : (isset($body['worksite_id']) ? (string) $body['worksite_id'] : null),
            parentDepartmentUuid: isset($body['parentDepartmentId']) ? (string) $body['parentDepartmentId'] : (isset($body['parent_department_id']) ? (string) $body['parent_department_id'] : null),
        )], 201);
    }

    public function updateDepartment(Request $request): Response
    {
        $ctx = $this->requireContext();
        $body = $this->requestData($request);

        return Response::json(['status' => 'ok', 'data' => $this->updateDepartment->execute(
            organizationUuid: (string) ($request->routeParam('id') ?? ''),
            departmentUuid: (string) ($request->routeParam('departmentId') ?? ''),
            actor: $ctx,
            name: (string) ($body['name'] ?? ''),
            status: isset($body['status']) ? (string) $body['status'] : null,
            worksiteUuid: isset($body['worksiteId']) ? (string) $body['worksiteId'] : (isset($body['worksite_id']) ? (string) $body['worksite_id'] : null),
            parentDepartmentUuid: isset($body['parentDepartmentId']) ? (string) $body['parentDepartmentId'] : (isset($body['parent_department_id']) ? (string) $body['parent_department_id'] : null),
        )]);
    }

    public function showDepartment(Request $request): Response
    {
        $ctx = $this->requireContext();
        $this->permissions->requirePrivilege($ctx, OrganizationPermissions::VIEW);
        $organization = $this->requireOrganization((string) ($request->routeParam('id') ?? ''), $ctx);
        $department = $this->requireDepartments()->findByUuid((string) ($request->routeParam('departmentId') ?? ''));
        if ($department === null) {
            throw new NotFoundException('Department not found.');
        }
        if ($department->getOrganizationId() !== $organization->getId()) {
            throw new WrongScopeException('This department belongs to a different organization scope.');
        }

        return Response::json(['status' => 'ok', 'data' => [
            'id' => $department->getUuid(),
            'organizationId' => $organization->getUuid(),
            'worksiteId' => $department->getWorksiteId(),
            'parentDepartmentId' => $department->getParentDepartmentId(),
            'name' => $department->getName(),
            'status' => $department->getStatus(),
        ]]);
    }

    public function deleteDepartment(Request $request): Response
    {
        $ctx = $this->requireContext();
        $this->permissions->requirePrivilege($ctx, OrganizationPermissions::STRUCTURE_MANAGE);
        $organization = $this->requireOrganization((string) ($request->routeParam('id') ?? ''), $ctx);
        $repo = $this->requireDepartments();
        $department = $repo->findByUuid((string) ($request->routeParam('departmentId') ?? ''));
        if ($department === null) {
            throw new NotFoundException('Department not found.');
        }
        if ($department->getOrganizationId() !== $organization->getId()) {
            throw new WrongScopeException('This department belongs to a different organization scope.');
        }
        $before = $this->serializeDepartment($department, $organization->getUuid());
        $repo->delete($department->getUuid());
        $this->audit->record('organization.department.deleted', 'department', $department->getUuid(), beforeState: $before, afterState: ['id' => $department->getUuid(), 'deleted' => true], actorId: (string) $ctx->userId, actorType: 'user');

        return Response::json(['status' => 'ok', 'data' => ['id' => $department->getUuid(), 'deleted' => true]]);
    }

    public function listJobRoles(Request $request): Response
    {
        $ctx = $this->requireContext();

        return Response::json(['status' => 'ok', 'data' => $this->listJobRoles->execute(
            organizationUuid: (string) ($request->routeParam('id') ?? ''),
            actor: $ctx,
            limit: max(1, min(100, (int) ($request->query('limit') ?? 50))),
            offset: max(0, (int) ($request->query('offset') ?? 0)),
        )]);
    }

    public function createJobRole(Request $request): Response
    {
        $ctx = $this->requireContext();
        $body = $this->requestData($request);

        return Response::json(['status' => 'ok', 'data' => $this->createJobRole->execute(
            organizationUuid: (string) ($request->routeParam('id') ?? ''),
            name: (string) ($body['name'] ?? ''),
            actor: $ctx,
            departmentUuid: isset($body['departmentId']) ? (string) $body['departmentId'] : (isset($body['department_id']) ? (string) $body['department_id'] : null),
        )], 201);
    }

    public function updateJobRole(Request $request): Response
    {
        $ctx = $this->requireContext();
        $body = $this->requestData($request);

        return Response::json(['status' => 'ok', 'data' => $this->updateJobRole->execute(
            organizationUuid: (string) ($request->routeParam('id') ?? ''),
            jobRoleUuid: (string) ($request->routeParam('jobRoleId') ?? ''),
            actor: $ctx,
            name: (string) ($body['name'] ?? ''),
            status: isset($body['status']) ? (string) $body['status'] : null,
            departmentUuid: isset($body['departmentId']) ? (string) $body['departmentId'] : (isset($body['department_id']) ? (string) $body['department_id'] : null),
        )]);
    }

    public function showJobRole(Request $request): Response
    {
        $ctx = $this->requireContext();
        $this->permissions->requirePrivilege($ctx, OrganizationPermissions::VIEW);
        $organization = $this->requireOrganization((string) ($request->routeParam('id') ?? ''), $ctx);
        $jobRole = $this->requireJobRoles()->findByUuid((string) ($request->routeParam('jobRoleId') ?? ''));
        if ($jobRole === null) {
            throw new NotFoundException('Job role not found.');
        }
        if ($jobRole->getOrganizationId() !== $organization->getId()) {
            throw new WrongScopeException('This job role belongs to a different organization scope.');
        }

        return Response::json(['status' => 'ok', 'data' => [
            'id' => $jobRole->getUuid(),
            'organizationId' => $organization->getUuid(),
            'departmentId' => $jobRole->getDepartmentId(),
            'name' => $jobRole->getName(),
            'status' => $jobRole->getStatus(),
        ]]);
    }

    public function deleteJobRole(Request $request): Response
    {
        $ctx = $this->requireContext();
        $this->permissions->requirePrivilege($ctx, OrganizationPermissions::STRUCTURE_MANAGE);
        $organization = $this->requireOrganization((string) ($request->routeParam('id') ?? ''), $ctx);
        $repo = $this->requireJobRoles();
        $jobRole = $repo->findByUuid((string) ($request->routeParam('jobRoleId') ?? ''));
        if ($jobRole === null) {
            throw new NotFoundException('Job role not found.');
        }
        if ($jobRole->getOrganizationId() !== $organization->getId()) {
            throw new WrongScopeException('This job role belongs to a different organization scope.');
        }
        $before = $this->serializeJobRole($jobRole, $organization->getUuid());
        $repo->delete($jobRole->getUuid());
        $this->audit->record('organization.job_role.deleted', 'job_role', $jobRole->getUuid(), beforeState: $before, afterState: ['id' => $jobRole->getUuid(), 'deleted' => true], actorId: (string) $ctx->userId, actorType: 'user');

        return Response::json(['status' => 'ok', 'data' => ['id' => $jobRole->getUuid(), 'deleted' => true]]);
    }

    /**
     * @return array<string, mixed>
     */
    private function requestData(Request $request): array
    {
        return array_replace($request->body, $request->json);
    }

    private function requireContext(): UserContext
    {
        $ctx = $this->session->getUserContext();
        if ($ctx === null) {
            throw new \WorkEddy\Shared\Exceptions\AuthenticationException('Unauthenticated');
        }

        return $ctx;
    }

    private function requireOrganization(string $organizationUuid, UserContext $ctx): Organization
    {
        $organization = $this->organizations->findByUuid($organizationUuid);
        if ($organization === null) {
            throw new NotFoundException('Organization not found.');
        }
        if ($ctx->organizationId !== null && $ctx->organizationId !== $organization->getId()) {
            throw new WrongScopeException(
                'This page belongs to a different organization than the one currently selected.',
                $organization->getName(),
                $organization->getUuid(),
            );
        }

        return $organization;
    }

    private function serializeOrganization(Organization $organization): array
    {
        return [
            'id' => $organization->getUuid(),
            'name' => $organization->getName(),
            'slug' => $organization->getSlug(),
            'status' => $organization->getStatus(),
            'contactEmail' => $organization->getContactEmail(),
            'phone' => $organization->getPhone(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeMembership(\WorkEddy\Modules\IAM\Domain\OrganizationMembership $membership): array
    {
        return [
            'id' => $membership->getUuid(),
            'userId' => $membership->getUserId(),
            'organizationUuid' => $membership->getOrganizationUuid(),
            'roleSlug' => $membership->getRoleSlug(),
            'status' => $membership->getStatus(),
            'worksiteId' => $membership->getWorksiteId(),
            'departmentId' => $membership->getDepartmentId(),
            'jobRoleId' => $membership->getJobRoleId(),
            'isPrimary' => $membership->isPrimary(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeAssignableRole(\WorkEddy\Modules\IAM\Domain\Role $role): array
    {
        return [
            'id' => $role->getUuid(),
            'slug' => $role->getSlug(),
            'name' => $role->getName(),
            'description' => $role->getDescription(),
            'scope' => $role->getScope(),
            'isSystem' => $role->isSystem(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeWorksite(\WorkEddy\Modules\Organization\Domain\Worksite $worksite, string $organizationUuid): array
    {
        return [
            'id' => $worksite->getUuid(),
            'organizationId' => $organizationUuid,
            'name' => $worksite->getName(),
            'status' => $worksite->getStatus(),
            'location' => $worksite->getLocation(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeDepartment(\WorkEddy\Modules\Organization\Domain\Department $department, string $organizationUuid): array
    {
        return [
            'id' => $department->getUuid(),
            'organizationId' => $organizationUuid,
            'worksiteId' => $department->getWorksiteId(),
            'parentDepartmentId' => $department->getParentDepartmentId(),
            'name' => $department->getName(),
            'status' => $department->getStatus(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeJobRole(\WorkEddy\Modules\Organization\Domain\JobRole $jobRole, string $organizationUuid): array
    {
        return [
            'id' => $jobRole->getUuid(),
            'organizationId' => $organizationUuid,
            'departmentId' => $jobRole->getDepartmentId(),
            'name' => $jobRole->getName(),
            'status' => $jobRole->getStatus(),
        ];
    }

    private function requireWorksites(): IWorksiteRepository
    {
        return $this->worksites;
    }

    private function requireDepartments(): IDepartmentRepository
    {
        return $this->departments;
    }

    private function requireJobRoles(): IJobRoleRepository
    {
        return $this->jobRoles;
    }

    private function requireMemberships(): IOrganizationMembershipRepository
    {
        return $this->memberships;
    }

    private function requireUsers(): IUserRepository
    {
        return $this->users;
    }

    private function requireRoles(): IRoleRepository
    {
        return $this->roles;
    }
}
