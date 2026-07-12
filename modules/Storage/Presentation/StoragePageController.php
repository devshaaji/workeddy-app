<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Storage\Presentation;

use WorkEddy\Modules\Storage\Authorization\StoragePermissions;
use WorkEddy\Platform\Http\Request;
use WorkEddy\Platform\Http\Response;
use WorkEddy\Platform\Session\ISessionService;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Shared\Exceptions\AuthenticationException;
use WorkEddy\Shared\Presentation\ViewRenderer;

final class StoragePageController
{
    public function __construct(
        private readonly ViewRenderer $view,
        private readonly ISessionService $session,
    ) {}

    public function adminIndex(): Response
    {
        $ctx = $this->context();

        return $this->view->render('modules/Storage/Presentation/Views/admin_storage.php', 'Storage', [
            'pageTitle' => 'File Manager',
            'pagePurpose' => 'Upload, organize, preview, and manage stored files.',
            'can' => [
                'view' => $ctx->hasPrivilege(StoragePermissions::FILE_VIEW),
                'upload' => $ctx->hasPrivilege(StoragePermissions::FILE_UPLOAD),
                'download' => $ctx->hasPrivilege(StoragePermissions::FILE_DOWNLOAD),
                'delete' => $ctx->hasPrivilege(StoragePermissions::FILE_DELETE),
                'manageSettings' => $ctx->hasPrivilege(StoragePermissions::SETTINGS_MANAGE),
            ],
            'pageCss' => ['css/modules/storage-file-manager.css'],
            'pageScripts' => ['js/modules/storage-file-manager.js'],
        ]);
    }

    private function context(): UserContext
    {
        $ctx = $this->session->getUserContext();
        if ($ctx === null) {
            throw new AuthenticationException('Unauthenticated');
        }

        return $ctx;
    }
}
