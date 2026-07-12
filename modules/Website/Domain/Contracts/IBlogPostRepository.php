<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Website\Domain\Contracts;

use WorkEddy\Modules\Website\Domain\Entities\BlogPost;

interface IBlogPostRepository
{
    public function findById(int $id): ?BlogPost;
    public function findBySlug(string $slug): ?BlogPost;
    public function save(BlogPost $post): void;
    public function delete(int $id): void;
    /** @return BlogPost[] */
    public function listAll(): array;
}
