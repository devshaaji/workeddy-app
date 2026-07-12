<?php
$isEdit = ($mode ?? 'create') === 'edit';
$action = $isEdit ? "/api/v1/website/blog/{$post->id}" : "/api/v1/website/blog";
$method = $isEdit ? "PUT" : "POST";
?>

<div class="page-header d-print-none">
    <div class="container-xl">
        <div class="row g-2 align-items-center">
            <div class="col">
                <h2 class="page-title">
                    <?= $isEdit ? 'Edit Post' : 'New Post' ?>
                </h2>
            </div>
            <div class="col-auto ms-auto d-print-none">
                <a href="/admin/website/blog" class="btn btn-secondary">
                    Cancel
                </a>
            </div>
        </div>
    </div>
</div>

<div class="page-body">
    <div class="container-xl">
        <form id="blogForm" class="card">
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label required">Title</label>
                    <input type="text" class="form-control" id="title" value="<?= $isEdit ? htmlspecialchars($post->title) : '' ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label required">Slug</label>
                    <input type="text" class="form-control" id="slug" value="<?= $isEdit ? htmlspecialchars($post->slug) : '' ?>" required>
                    <small class="form-hint">Used in the URL (e.g., /blog/my-post)</small>
                </div>
                <div class="mb-3">
                    <label class="form-label">Excerpt</label>
                    <textarea class="form-control" id="excerpt" rows="2"><?= $isEdit ? htmlspecialchars($post->excerpt ?? '') : '' ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label required">Content</label>
                    <textarea class="form-control" id="content" rows="10" required><?= $isEdit ? htmlspecialchars($post->content) : '' ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label required">Status</label>
                    <select class="form-select" id="status" required>
                        <option value="draft" <?= ($isEdit && $post->status === 'draft') ? 'selected' : '' ?>>Draft</option>
                        <option value="published" <?= ($isEdit && $post->status === 'published') ? 'selected' : '' ?>>Published</option>
                    </select>
                </div>
            </div>
            <div class="card-footer text-end">
                <button type="submit" class="btn btn-primary" id="saveBtn">Save Post</button>
            </div>
        </form>
    </div>
</div>

<script>
    document.getElementById('blogForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = document.getElementById('saveBtn');
        btn.disabled = true;
        btn.textContent = 'Saving...';

        const data = {
            title: document.getElementById('title').value,
            slug: document.getElementById('slug').value,
            excerpt: document.getElementById('excerpt').value,
            content: document.getElementById('content').value,
            status: document.getElementById('status').value
        };

        try {
            const response = await fetch('<?= $action ?>', {
                method: '<?= $method ?>',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(data)
            });
            const result = await response.json();

            if (result.status === 'ok') {
                window.location.href = '/admin/website/blog';
            } else {
                alert(result.message || 'Error saving post');
                btn.disabled = false;
                btn.textContent = 'Save Post';
            }
        } catch (error) {
            console.error(error);
            alert('Network error');
            btn.disabled = false;
            btn.textContent = 'Save Post';
        }
    });
</script>