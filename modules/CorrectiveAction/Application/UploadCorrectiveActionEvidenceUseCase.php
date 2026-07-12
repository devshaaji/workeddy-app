<?php

declare(strict_types=1);

namespace WorkEddy\Modules\CorrectiveAction\Application;

use WorkEddy\Modules\CorrectiveAction\Authorization\CorrectiveActionPermissions;
use WorkEddy\Modules\CorrectiveAction\Domain\Contracts\ICorrectiveActionRepository;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Modules\Storage\Application\DTOs\StoreUploadedFileRequest;
use WorkEddy\Modules\Storage\Domain\Contracts\IStorageService;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Shared\Exceptions\NotFoundException;
use WorkEddy\Shared\Exceptions\ValidationException;
use WorkEddy\Shared\Support\UuidSupport;

final class UploadCorrectiveActionEvidenceUseCase
{
    public function __construct(
        private readonly ICorrectiveActionRepository $repository,
        private readonly IStorageService $storage,
        private readonly IPermissionService $permissions,
        private readonly IAuditService $audit,
    ) {}

    /** @param array<string, mixed> $file @return array<string, mixed> */
    public function execute(string $actionUuid, UserContext $actor, array $file, string $evidenceType = 'photo', ?string $notes = null): array
    {
        $this->permissions->requirePrivilege($actor, CorrectiveActionPermissions::UPLOAD_EVIDENCE);
        $action = $this->repository->findActionByUuid(UuidSupport::requireValid($actionUuid, 'actionUuid'));
        if ($action === null || ($actor->organizationId !== null && $actor->organizationId !== $action->organizationId)) {
            throw new NotFoundException('Corrective action not found.');
        }

        $stored = $this->storage->storeUploadedFile(new StoreUploadedFileRequest(
            file: $file,
            ownerType: 'corrective_action',
            ownerUuid: $action->uuid,
            fieldName: 'evidence',
            visibility: 'private',
            actorId: $actor->userId,
            allowedExtensions: ['jpg', 'jpeg', 'png', 'mp4', 'mov', 'webm', 'pdf'],
            allowedMimeTypes: ['image/jpeg', 'image/png', 'video/mp4', 'video/quicktime', 'video/webm', 'application/pdf'],
        ));
        if ($stored === null) {
            throw new ValidationException(['file' => 'Evidence file is required.']);
        }

        $evidence = $this->repository->addEvidence([
            'uuid' => UuidSupport::generate(),
            'actionUuid' => $action->uuid,
            'storageFileUuid' => $stored->uuid,
            'evidenceType' => $evidenceType,
            'notes' => $notes,
            'uploadedBy' => $actor->userId,
        ]);
        $this->audit->record('corrective_action.evidence_uploaded', 'corrective_action', $action->uuid, afterState: $evidence, actorId: (string) $actor->userId, actorType: 'user');

        return $evidence;
    }
}
