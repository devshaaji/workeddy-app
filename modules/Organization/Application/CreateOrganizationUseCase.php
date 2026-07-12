<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Organization\Application;

use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Modules\Organization\Authorization\OrganizationPermissions;
use WorkEddy\Modules\Organization\Domain\Contracts\IOrganizationRepository;
use WorkEddy\Modules\Organization\Domain\Organization;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Events\EventPublisherInterface;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Platform\Transaction\TransactionManagerInterface;
use WorkEddy\Shared\Exceptions\ConflictException;
use WorkEddy\Shared\Exceptions\ValidationException;
use WorkEddy\Shared\Support\UuidSupport;

final class CreateOrganizationUseCase
{
    public function __construct(
        private readonly IOrganizationRepository $organizations,
        private readonly IPermissionService $permissions,
        private readonly TransactionManagerInterface $tx,
        private readonly IAuditService $audit,
        private readonly EventPublisherInterface $events,
    ) {}

    /**
     * @return array{id: string, name: string, slug: string, contactEmail: ?string, phone: ?string, status: string}
     */
    public function execute(string $name, ?string $contactEmail, UserContext $actor, ?string $phone = null): array
    {
        $this->permissions->requirePrivilege($actor, OrganizationPermissions::MANAGE);

        $normalizedName = trim($name);
        if ($normalizedName === '') {
            throw new ValidationException(['name' => 'Organization name is required.']);
        }

        if ($contactEmail !== null && trim($contactEmail) !== '' && filter_var($contactEmail, FILTER_VALIDATE_EMAIL) === false) {
            throw new ValidationException(['contactEmail' => 'A valid contact email is required.']);
        }

        $slug = $this->slugify($normalizedName);
        if ($this->organizations->findBySlug($slug) !== null) {
            throw new ConflictException("Organization slug '{$slug}' already exists.");
        }

        $organization = new Organization(
            id: null,
            uuid: UuidSupport::generate(),
            name: $normalizedName,
            slug: $slug,
            status: 'active',
            contactEmail: $contactEmail !== null && trim($contactEmail) !== '' ? trim($contactEmail) : null,
            phone: $phone !== null && trim($phone) !== '' ? trim($phone) : null,
        );

        $organizationId = null;
        $this->tx->transactional(function () use ($organization, $actor, &$organizationId): void {
            $organizationId = $this->organizations->create($organization);
            $this->audit->record(
                action: 'organization.created',
                entityType: 'Organization',
                entityId: $organization->getUuid(),
                afterState: [
                    'name' => $organization->getName(),
                    'slug' => $organization->getSlug(),
                    'status' => $organization->getStatus(),
                ],
                actorId: (string) $actor->userId,
                actorType: 'User',
            );
        });

        $this->events->publish(
            'organization.created',
            [
                'organization_id' => $organizationId,
                'organization_uuid' => $organization->getUuid(),
                'name' => $organization->getName(),
                'slug' => $organization->getSlug(),
                'contact_email' => $organization->getContactEmail(),
            ],
            idempotencyKey: sprintf('organization.created:%s', $organization->getUuid()),
        );

        return [
            'id' => $organization->getUuid(),
            'name' => $organization->getName(),
            'slug' => $organization->getSlug(),
            'contactEmail' => $organization->getContactEmail(),
            'phone' => $organization->getPhone(),
            'status' => $organization->getStatus(),
        ];
    }

    private function slugify(string $name): string
    {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-');

        return $slug !== '' ? $slug : 'organization';
    }
}
