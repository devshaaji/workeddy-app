<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Content\Domain;

final class ContentPageRevision
{
    /**
     * @param array<string, mixed> $contentSnapshot
     */
    public function __construct(
        public readonly ?int $id,
        public readonly string $uuid,
        public readonly ?int $pageId,
        public readonly int $versionNumber,
        public readonly string $revisionStatus,
        public readonly string $title,
        public readonly ?string $seoTitle,
        public readonly ?string $seoDescription,
        public readonly ?string $changeSummary,
        public readonly array $contentSnapshot,
        public readonly ?int $createdBy,
        public readonly string $createdAt,
        public readonly ?int $updatedBy,
        public readonly ?string $updatedAt,
        public readonly ?int $publishedBy,
        public readonly ?string $publishedAt,
    ) {}

    public function withPersistence(int $id, ?int $pageId): self
    {
        return new self(
            id: $id,
            uuid: $this->uuid,
            pageId: $pageId,
            versionNumber: $this->versionNumber,
            revisionStatus: $this->revisionStatus,
            title: $this->title,
            seoTitle: $this->seoTitle,
            seoDescription: $this->seoDescription,
            changeSummary: $this->changeSummary,
            contentSnapshot: $this->contentSnapshot,
            createdBy: $this->createdBy,
            createdAt: $this->createdAt,
            updatedBy: $this->updatedBy,
            updatedAt: $this->updatedAt,
            publishedBy: $this->publishedBy,
            publishedAt: $this->publishedAt,
        );
    }

    /**
     * @param array<string, mixed> $contentSnapshot
     */
    public function updateDraft(
        string $title,
        ?string $seoTitle,
        ?string $seoDescription,
        array $contentSnapshot,
        int $actorId,
        string $updatedAt,
        ?string $changeSummary,
    ): self {
        return new self(
            id: $this->id,
            uuid: $this->uuid,
            pageId: $this->pageId,
            versionNumber: $this->versionNumber,
            revisionStatus: 'draft',
            title: $title,
            seoTitle: $seoTitle,
            seoDescription: $seoDescription,
            changeSummary: $changeSummary,
            contentSnapshot: $contentSnapshot,
            createdBy: $this->createdBy,
            createdAt: $this->createdAt,
            updatedBy: $actorId,
            updatedAt: $updatedAt,
            publishedBy: null,
            publishedAt: null,
        );
    }

    public function publish(int $actorId, string $publishedAt, ?string $changeSummary = null): self
    {
        return new self(
            id: $this->id,
            uuid: $this->uuid,
            pageId: $this->pageId,
            versionNumber: $this->versionNumber,
            revisionStatus: 'published',
            title: $this->title,
            seoTitle: $this->seoTitle,
            seoDescription: $this->seoDescription,
            changeSummary: $changeSummary ?? $this->changeSummary,
            contentSnapshot: $this->contentSnapshot,
            createdBy: $this->createdBy,
            createdAt: $this->createdAt,
            updatedBy: $this->updatedBy,
            updatedAt: $this->updatedAt,
            publishedBy: $actorId,
            publishedAt: $publishedAt,
        );
    }

    public function supersede(): self
    {
        return new self(
            id: $this->id,
            uuid: $this->uuid,
            pageId: $this->pageId,
            versionNumber: $this->versionNumber,
            revisionStatus: 'superseded',
            title: $this->title,
            seoTitle: $this->seoTitle,
            seoDescription: $this->seoDescription,
            changeSummary: $this->changeSummary,
            contentSnapshot: $this->contentSnapshot,
            createdBy: $this->createdBy,
            createdAt: $this->createdAt,
            updatedBy: $this->updatedBy,
            updatedAt: $this->updatedAt,
            publishedBy: $this->publishedBy,
            publishedAt: $this->publishedAt,
        );
    }
}
