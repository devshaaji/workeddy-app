<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Website\Infrastructure;

use WorkEddy\Modules\Website\Domain\Contracts\IBlogPostRepository;
use WorkEddy\Modules\Website\Domain\Entities\BlogPost;
use WorkEddy\Platform\Clock\IClock;
use WorkEddy\Shared\Support\DateFormatter;
use Doctrine\DBAL\Connection;

final class DbalBlogPostRepository implements IBlogPostRepository
{
    public function __construct(
        private readonly Connection $db,
        private readonly IClock $clock,
    ) {}

    public function findById(int $id): ?BlogPost
    {
        $row = $this->db->fetchAssociative('SELECT * FROM website_blog_posts WHERE id = ?', [$id]);
        return $row ? $this->mapRowToEntity($row) : null;
    }

    public function findBySlug(string $slug): ?BlogPost
    {
        $row = $this->db->fetchAssociative('SELECT * FROM website_blog_posts WHERE slug = ?', [$slug]);
        return $row ? $this->mapRowToEntity($row) : null;
    }

    public function save(BlogPost $post): void
    {
        $data = [
            'uuid' => $post->uuid,
            'title' => $post->title,
            'slug' => $post->slug,
            'content' => $post->content,
            'excerpt' => $post->excerpt,
            'author_id' => $post->authorId,
            'status' => $post->status,
            'published_at' => $post->publishedAt?->format('Y-m-d H:i:s'),
            'updated_at' => ($this->clock->now())->format('Y-m-d H:i:s'),
        ];

        if ($post->id === 0) {
            $data['created_at'] = $post->createdAt->format('Y-m-d H:i:s');
            $this->db->insert('website_blog_posts', $data);
        } else {
            $this->db->update('website_blog_posts', $data, ['id' => $post->id]);
        }
    }

    public function delete(int $id): void
    {
        $this->db->delete('website_blog_posts', ['id' => $id]);
    }

    public function listAll(): array
    {
        $rows = $this->db->fetchAllAssociative('SELECT * FROM website_blog_posts ORDER BY created_at DESC');
        return array_map(fn($row) => $this->mapRowToEntity($row), $rows);
    }

    private function mapRowToEntity(array $row): BlogPost
    {
        return new BlogPost(
            id: (int) $row['id'],
            uuid: $row['uuid'],
            title: $row['title'],
            slug: $row['slug'],
            content: $row['content'],
            excerpt: $row['excerpt'],
            authorId: $row['author_id'] !== null ? (int) $row['author_id'] : null,
            status: $row['status'],
            publishedAt: $row['published_at'] ? DateFormatter::fromNaiveDbString($row['published_at']) : null,
            createdAt: DateFormatter::fromNaiveDbString($row['created_at']) ?? new \DateTimeImmutable(),
            updatedAt: DateFormatter::fromNaiveDbString($row['updated_at']) ?? new \DateTimeImmutable(),
        );
    }
}
