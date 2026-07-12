<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Content\Domain;

final class ContentPage
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $uuid,
        public readonly string $pageKey,
        public readonly string $routePath,
        public readonly string $contentType,
        public readonly string $templateKey,
        public readonly string $audience,
        public readonly string $status,
        public readonly ?int $publishedRevisionId,
        public readonly ?int $draftRevisionId,
        public readonly int $lockVersion,
        public readonly ?int $createdBy,
        public readonly string $createdAt,
        public readonly string $updatedAt,
        public readonly ?string $archivedAt,
        public readonly ?int $archivedBy,
    ) {}

    public function withPersistence(int $id, int $draftRevisionId): self
    {
        return new self(
            id: $id,
            uuid: $this->uuid,
            pageKey: $this->pageKey,
            routePath: $this->routePath,
            contentType: $this->contentType,
            templateKey: $this->templateKey,
            audience: $this->audience,
            status: $this->status,
            publishedRevisionId: $this->publishedRevisionId,
            draftRevisionId: $draftRevisionId,
            lockVersion: $this->lockVersion,
            createdBy: $this->createdBy,
            createdAt: $this->createdAt,
            updatedAt: $this->updatedAt,
            archivedAt: $this->archivedAt,
            archivedBy: $this->archivedBy,
        );
    }

    public function withDraftRevision(?int $draftRevisionId, string $updatedAt, ?string $status = null): self
    {
        return new self(
            id: $this->id,
            uuid: $this->uuid,
            pageKey: $this->pageKey,
            routePath: $this->routePath,
            contentType: $this->contentType,
            templateKey: $this->templateKey,
            audience: $this->audience,
            status: $status ?? $this->status,
            publishedRevisionId: $this->publishedRevisionId,
            draftRevisionId: $draftRevisionId,
            lockVersion: $this->lockVersion + 1,
            createdBy: $this->createdBy,
            createdAt: $this->createdAt,
            updatedAt: $updatedAt,
            archivedAt: $this->archivedAt,
            archivedBy: $this->archivedBy,
        );
    }

    public function withPublishedRevision(int $publishedRevisionId, string $updatedAt): self
    {
        return new self(
            id: $this->id,
            uuid: $this->uuid,
            pageKey: $this->pageKey,
            routePath: $this->routePath,
            contentType: $this->contentType,
            templateKey: $this->templateKey,
            audience: $this->audience,
            status: 'published',
            publishedRevisionId: $publishedRevisionId,
            draftRevisionId: null,
            lockVersion: $this->lockVersion + 1,
            createdBy: $this->createdBy,
            createdAt: $this->createdAt,
            updatedAt: $updatedAt,
            archivedAt: $this->archivedAt,
            archivedBy: $this->archivedBy,
        );
    }

    public function archive(int $actorId, string $archivedAt): self
    {
        return new self(
            id: $this->id,
            uuid: $this->uuid,
            pageKey: $this->pageKey,
            routePath: $this->routePath,
            contentType: $this->contentType,
            templateKey: $this->templateKey,
            audience: $this->audience,
            status: 'archived',
            publishedRevisionId: $this->publishedRevisionId,
            draftRevisionId: $this->draftRevisionId,
            lockVersion: $this->lockVersion + 1,
            createdBy: $this->createdBy,
            createdAt: $this->createdAt,
            updatedAt: $archivedAt,
            archivedAt: $archivedAt,
            archivedBy: $actorId,
        );
    }

    public function restore(string $updatedAt): self
    {
        $status = $this->publishedRevisionId !== null ? 'published' : 'draft';

        return new self(
            id: $this->id,
            uuid: $this->uuid,
            pageKey: $this->pageKey,
            routePath: $this->routePath,
            contentType: $this->contentType,
            templateKey: $this->templateKey,
            audience: $this->audience,
            status: $status,
            publishedRevisionId: $this->publishedRevisionId,
            draftRevisionId: $this->draftRevisionId,
            lockVersion: $this->lockVersion + 1,
            createdBy: $this->createdBy,
            createdAt: $this->createdAt,
            updatedAt: $updatedAt,
            archivedAt: null,
            archivedBy: null,
        );
    }
}
