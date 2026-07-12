<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Notification\Application;

use WorkEddy\Modules\Notification\Domain\NotificationRecipient;
use WorkEddy\Modules\Organization\Domain\Contracts\IOrganizationRepository;

final class OrganizationNotificationRecipientFactory
{
    public function __construct(
        private readonly IOrganizationRepository $organizations,
    ) {}

    /**
     * Build a NotificationRecipient from an organization ID or UUID.
     */
    public function fromOrganizationId(int|string $organizationId): ?NotificationRecipient
    {
        $reference = trim((string) $organizationId);
        if ($reference === '') {
            return null;
        }

        $organization = ctype_digit($reference)
            ? $this->organizations->findById((int) $reference)
            : $this->organizations->findByUuid($reference);
        if ($organization === null) {
            return null;
        }

        return new NotificationRecipient(
            recipientId: (string) ($organization->getId() ?? $organization->getUuid()),
            recipientType: 'organization',
            name: $organization->getName(),
            email: $organization->getContactEmail(),
            phone: $organization->getPhone(),
        );
    }
}
