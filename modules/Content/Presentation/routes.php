<?php

declare(strict_types=1);

use WorkEddy\Modules\Content\Authorization\ContentPermissions;
use WorkEddy\Modules\Content\Presentation\ContentApiController;
use WorkEddy\Modules\Content\Presentation\ContentPageController;
use WorkEddy\Platform\Http\RouteRegistrar;

return function (RouteRegistrar $routes): void {
    $uuidPattern = '[0-9A-Fa-f]{8}-[0-9A-Fa-f]{4}-[1-5][0-9A-Fa-f]{3}-[89ABab][0-9A-Fa-f]{3}-[0-9A-Fa-f]{12}';
    $pageKeyPattern = '[a-z0-9-]+';

        $routes->module('Content', static function (RouteRegistrar $module) use ($uuidPattern, $pageKeyPattern): void {
        $module->group('/content', static function (RouteRegistrar $web) use ($uuidPattern, $pageKeyPattern): void {
            $web->add('GET', '', [ContentPageController::class, 'index'], ['permission:' . ContentPermissions::PAGES_READ]);
            $web->add('GET', '/create', [ContentPageController::class, 'create'], ['permission:' . ContentPermissions::PAGES_CREATE]);
            $web->add('GET', '/methodology-and-limitations', [ContentPageController::class, 'methodology'], ['permission:' . ContentPermissions::PAGES_READ]);
            $web->add('GET', '/pages/{pageUuid:' . $uuidPattern . '}', [ContentPageController::class, 'show'], ['permission:' . ContentPermissions::PAGES_READ]);
            $web->add('GET', '/pages/{pageUuid:' . $uuidPattern . '}/edit', [ContentPageController::class, 'edit'], ['permission:' . ContentPermissions::PAGES_UPDATE]);
            $web->add('GET', '/pages/{pageUuid:' . $uuidPattern . '}/preview', [ContentPageController::class, 'preview'], ['permission:' . ContentPermissions::PREVIEW]);
            $web->add('GET', '/pages/{pageUuid:' . $uuidPattern . '}/revisions', [ContentPageController::class, 'history'], ['permission:' . ContentPermissions::PAGES_READ]);
            $web->add('GET', '/pages/{pageUuid:' . $uuidPattern . '}/revisions/{revisionUuid:' . $uuidPattern . '}', [ContentPageController::class, 'revision'], ['permission:' . ContentPermissions::PAGES_READ]);
            $web->add('GET', '/media', [ContentPageController::class, 'media'], ['permission:' . ContentPermissions::MEDIA_READ]);
            $web->add('GET', '/{pageKey:' . $pageKeyPattern . '}', [ContentPageController::class, 'showByKey'], ['permission:' . ContentPermissions::PAGES_READ]);
        }, ['auth']);

        $module->group('/api/v1/content', static function (RouteRegistrar $api) use ($uuidPattern): void {
            $api->add('GET', '/pages', [ContentApiController::class, 'listPages'], ['permission:' . ContentPermissions::PAGES_READ]);
            $api->add('POST', '/pages', [ContentApiController::class, 'createPage'], ['permission:' . ContentPermissions::PAGES_CREATE]);
            $api->add('GET', '/pages/{pageUuid:' . $uuidPattern . '}', [ContentApiController::class, 'getPage'], ['permission:' . ContentPermissions::PAGES_READ]);
            $api->add('POST', '/pages/{pageUuid:' . $uuidPattern . '}/archive', [ContentApiController::class, 'archivePage'], ['permission:' . ContentPermissions::PAGES_ARCHIVE]);
            $api->add('POST', '/pages/{pageUuid:' . $uuidPattern . '}/restore', [ContentApiController::class, 'restorePage'], ['permission:' . ContentPermissions::PAGES_ARCHIVE]);

            $api->add('GET', '/pages/{pageUuid:' . $uuidPattern . '}/draft', [ContentApiController::class, 'getDraft'], ['permission:' . ContentPermissions::PAGES_UPDATE]);
            $api->add('PUT', '/pages/{pageUuid:' . $uuidPattern . '}/draft', [ContentApiController::class, 'saveDraft'], ['permission:' . ContentPermissions::PAGES_UPDATE]);
            $api->add('GET', '/pages/{pageUuid:' . $uuidPattern . '}/preview', [ContentApiController::class, 'previewDraft'], ['permission:' . ContentPermissions::PREVIEW]);
            $api->add('POST', '/pages/{pageUuid:' . $uuidPattern . '}/publish', [ContentApiController::class, 'publish'], ['permission:' . ContentPermissions::PAGES_PUBLISH]);

            $api->add('GET', '/pages/{pageUuid:' . $uuidPattern . '}/revisions', [ContentApiController::class, 'listRevisions'], ['permission:' . ContentPermissions::PAGES_READ]);
            $api->add('GET', '/pages/{pageUuid:' . $uuidPattern . '}/revisions/{revisionUuid:' . $uuidPattern . '}', [ContentApiController::class, 'getRevision'], ['permission:' . ContentPermissions::PAGES_READ]);
            $api->add('POST', '/pages/{pageUuid:' . $uuidPattern . '}/revisions/{revisionUuid:' . $uuidPattern . '}/restore', [ContentApiController::class, 'restoreRevision'], ['permission:' . ContentPermissions::PAGES_RESTORE]);

            $api->add('GET', '/media', [ContentApiController::class, 'listMedia'], ['permission:' . ContentPermissions::MEDIA_READ]);
            $api->add('POST', '/media', [ContentApiController::class, 'uploadMedia'], ['permission:' . ContentPermissions::MEDIA_UPLOAD]);
            $api->add('GET', '/media/{mediaUuid:' . $uuidPattern . '}', [ContentApiController::class, 'getMedia'], ['permission:' . ContentPermissions::MEDIA_READ]);
            $api->add('PUT', '/media/{mediaUuid:' . $uuidPattern . '}', [ContentApiController::class, 'updateMedia'], ['permission:' . ContentPermissions::MEDIA_UPDATE]);
            $api->add('POST', '/media/{mediaUuid:' . $uuidPattern . '}/archive', [ContentApiController::class, 'archiveMedia'], ['permission:' . ContentPermissions::MEDIA_ARCHIVE]);
        }, ['auth']);
    });
};
