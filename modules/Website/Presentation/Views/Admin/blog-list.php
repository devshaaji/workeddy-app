<div class="page-header d-print-none">
    <div class="container-xl">
        <div class="row g-2 align-items-center">
            <div class="col">
                <h2 class="page-title">
                    Blog Posts
                </h2>
                <div class="text-muted mt-1"><?= count($posts ?? []) ?> posts published</div>
            </div>
            <div class="col-auto ms-auto d-print-none">
                <div class="btn-list">
                    <a href="/admin/website/blog/create" class="btn btn-primary d-none d-sm-inline-block">
                        <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path d="M12 5l0 14" />
                            <path d="M5 12l14 0" />
                        </svg>
                        New Post
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="page-body">
    <div class="container-xl">
        <div class="card">
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th class="w-1"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($posts ?? [] as $post): ?>
                            <tr>
                                <td>
                                    <div class="d-flex py-1 align-items-center">
                                        <div class="flex-fill">
                                            <div class="font-weight-medium"><?= htmlspecialchars($post->title) ?></div>
                                            <div class="text-muted"><a href="#" class="text-reset"><?= htmlspecialchars($post->slug) ?></a></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($post->status === 'published'): ?>
                                        <span class="badge bg-success me-1"></span> Published
                                    <?php else: ?>
                                        <span class="badge bg-warning me-1"></span> Draft
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= $post->createdAt->format('Y-m-d') ?>
                                </td>
                                <td>
                                    <a href="/admin/website/blog/<?= $post->id ?>/edit" class="btn btn-sm btn-outline-primary">Edit</a>
                                    <button class="btn btn-sm btn-outline-danger" onclick="deletePost(<?= $post->id ?>)">Delete</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($posts)): ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">No posts found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    async function deletePost(id) {
        if (!confirm('Are you sure you want to delete this post?')) return;

        try {
            const response = await fetch(`/api/v1/website/blog/${id}`, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json'
                }
            });
            const result = await response.json();
            if (result.status === 'ok') {
                window.location.reload();
            } else {
                alert(result.message || 'Error deleting post');
            }
        } catch (error) {
            console.error(error);
            alert('Network error');
        }
    }
</script>