<?php

declare(strict_types=1);

namespace WorkEddy\Tests\Privacy;

use PHPUnit\Framework\TestCase;
use WorkEddy\Modules\Privacy\Application\IssueSignedVideoAccessUseCase;
use WorkEddy\Modules\Privacy\Application\ReadSignedVideoAccessUseCase;
use WorkEddy\Modules\Privacy\Domain\SignedVideoAccess;
use WorkEddy\Modules\Storage\Application\DTOs\StoredFileDTO;
use WorkEddy\Modules\Storage\Application\DTOs\StoreUploadedFileRequest;
use WorkEddy\Modules\Storage\Domain\Contracts\IStorageService;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Clock\IClock;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Shared\Exceptions\ForbiddenException;

final class SignedVideoAccessTest extends TestCase
{
    public function test_signed_video_access_logs_and_streams_until_expiry(): void
    {
        $clock = new MutableSignedAccessClock('2026-07-07 10:00:00');
        $audit = new RecordingSignedAccessAuditService();
        $storage = new ReadableStorageService();
        $actor = new UserContext(userId: 44, organizationId: 3, organizationUuid: '11111111-1111-4111-8111-111111111111', roleType: 'staff', privileges: []);

        $issued = (new IssueSignedVideoAccessUseCase($audit, $clock, 'test-secret'))->execute(
            organizationUuid: '11111111-1111-4111-8111-111111111111',
            assessmentUuid: '44444444-4444-4444-8444-444444444444',
            storageFileUuid: '33333333-3333-4333-8333-333333333333',
            actor: $actor,
            purpose: 'review',
            ttlSeconds: 60,
        );

        self::assertStringContainsString('/api/v1/privacy/signed-video-access/', $issued['signedUrl']);
        self::assertSame('privacy.video.signed_access_issued', $audit->records[0]['action']);

        $read = (new ReadSignedVideoAccessUseCase($storage, $clock, $audit, 'test-secret'))->execute($issued['token']);
        self::assertSame('video/mp4', $read['mimeType']);
        self::assertSame('video-bytes', $read['body']);
        self::assertSame('privacy.video.signed_access_streamed', $audit->records[1]['action']);

        $clock->now = '2026-07-07 10:02:00';
        $this->expectException(ForbiddenException::class);
        (new ReadSignedVideoAccessUseCase($storage, $clock, $audit, 'test-secret'))->execute($issued['token']);
    }

    public function test_signed_video_access_ttl_is_clamped(): void
    {
        $clock = new MutableSignedAccessClock('2026-07-07 10:00:00');
        $audit = new RecordingSignedAccessAuditService();
        $actor = new UserContext(userId: 44, organizationId: 3, organizationUuid: '11111111-1111-4111-8111-111111111111', roleType: 'staff', privileges: []);

        $issued = (new IssueSignedVideoAccessUseCase($audit, $clock, 'test-secret'))->execute(
            organizationUuid: '11111111-1111-4111-8111-111111111111',
            assessmentUuid: '44444444-4444-4444-8444-444444444444',
            storageFileUuid: '33333333-3333-4333-8333-333333333333',
            actor: $actor,
            purpose: 'review',
            ttlSeconds: 9999,
        );

        self::assertLessThanOrEqual(900, strtotime($issued['expiresAt']) - strtotime('2026-07-07 10:00:00'));
    }
}

final class MutableSignedAccessClock implements IClock
{
    public function __construct(public string $now) {}
    public function now(): \DateTimeImmutable { return new \DateTimeImmutable($this->now); }
}

final class RecordingSignedAccessAuditService implements IAuditService
{
    public array $records = [];
    public function record(string $action, string $entityType, string $entityId, ?array $beforeState = null, ?array $afterState = null, ?string $actorId = null, ?string $actorType = null, ?string $idempotencyKey = null, ?array $metadata = []): void
    {
        $this->records[] = compact('action', 'entityType', 'entityId', 'beforeState', 'afterState', 'actorId', 'actorType', 'idempotencyKey', 'metadata');
    }
}

final class ReadableStorageService implements IStorageService
{
    public function storeUploadedFile(StoreUploadedFileRequest $request): ?StoredFileDTO { return null; }
    public function findByUuid(string $uuid, bool $includePendingDeletion = false): StoredFileDTO
    {
        return new StoredFileDTO(null, $uuid, 'local', 'private', 'active', 'videos/lift.mp4', 'assessment', '44444444-4444-4444-8444-444444444444', 'video', 'lift.mp4', 'video/mp4', 'mp4', 11);
    }
    public function list(array $filters = []): array { return []; }
    public function count(array $filters = []): int { return 0; }
    public function summary(array $filters = []): array { return ['totalFiles' => 0, 'totalBytes' => 0, 'byCategory' => []]; }
    public function read(string $uuid): string { return 'video-bytes'; }
    public function requestDeletion(string $uuid, ?int $actorId = null): StoredFileDTO { return $this->findByUuid($uuid); }
    public function restore(string $uuid, ?int $actorId = null): StoredFileDTO { return $this->findByUuid($uuid); }
    public function delete(string $uuid, ?int $actorId = null): StoredFileDTO { return $this->findByUuid($uuid); }
    public function usageCount(string $uuid): int { return 0; }
}
