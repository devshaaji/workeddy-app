<?php

/**
 * System Settings controller — admin operations.
 */

declare(strict_types=1);

namespace WorkEddy\Modules\IAM\Presentation;

use WorkEddy\Platform\Settings\SettingsRegistry;
use WorkEddy\Platform\Settings\SettingsService;
use WorkEddy\Platform\Session\ISessionService;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Platform\Http\Request;
use WorkEddy\Shared\Exceptions\ForbiddenException;
use WorkEddy\Shared\Exceptions\ValidationException;

final class SettingsController
{
    public function __construct(
        private readonly SettingsRegistry $registry,
        private readonly SettingsService $settingsService,
        private readonly ISessionService $session,
        private readonly IPermissionService $permissions,
    ) {}

    public function index(Request $request): array
    {
        $ctx = $this->session->getUserContext();
        if ($ctx === null) {
            return ['status' => 'error', 'message' => 'Unauthenticated'];
        }

        $result = [];
        $requestedModule = $request->query('module');
        $modules = $requestedModule !== null && trim((string) $requestedModule) !== ''
            ? [trim((string) $requestedModule)]
            : $this->registry->getRegisteredModules();

        foreach ($modules as $module) {
            $metadata = $this->registry->getPageMetadata($module);
            if ($metadata === null || !$metadata->canView($ctx)) {
                continue;
            }
            $definitions = $this->registry->getForModule($module);
            $values = $this->settingsService->getAllForModule($module);

            $moduleSettings = [];
            foreach ($definitions as $key => $def) {
                $moduleSettings[] = [
                    'key' => $def->key,
                    'qualifiedKey' => $def->qualifiedKey(),
                    'type' => $def->type->value,
                    'editable' => $def->editable,
                    'default' => $def->default,
                    'value' => $values[$def->key] ?? $def->default,
                ];
            }
            $result[$module] = $moduleSettings;
        }

        return ['status' => 'ok', 'data' => $result];
    }

    public function update(Request $request): array
    {
        $ctx = $this->session->getUserContext();
        if ($ctx === null) {
            return ['status' => 'error', 'message' => 'Unauthenticated'];
        }

        $body = array_replace($request->body, $request->json);
        if (!array_key_exists('value', $body)) {
            throw new ValidationException(['value' => 'Value is required.']);
        }

        $module = $request->routeParam('module');
        $key = $request->routeParam('key');
        $qualifiedKey = $module . '.' . $key;

        $this->requireModuleEditAccess($ctx, (string) $module);
        $this->settingsService->set($qualifiedKey, $body['value'], (string) $ctx->userId);

        return ['status' => 'ok', 'data' => [
            'key' => $qualifiedKey,
        ]];
    }

    public function updateModule(Request $request): array
    {
        $ctx = $this->session->getUserContext();
        if ($ctx === null) {
            return ['status' => 'error', 'message' => 'Unauthenticated'];
        }

        $body = array_replace($request->body, $request->json);
        $values = $body['values'] ?? $body;
        if (!is_array($values) || $values === []) {
            throw new ValidationException(['values' => 'At least one setting value is required.']);
        }

        $module = trim((string) ($request->routeParam('module', '')));
        if ($module === '') {
            throw new ValidationException(['module' => 'Module is required.']);
        }

        $this->requireModuleEditAccess($ctx, $module);
        $this->settingsService->setMany($module, $values, (string) $ctx->userId);

        return ['status' => 'ok', 'data' => [
            'module' => $module,
            'values' => $this->settingsService->getAllForModule($module),
        ]];
    }

    public function reset(Request $request): array
    {
        $ctx = $this->session->getUserContext();
        if ($ctx === null) {
            return ['status' => 'error', 'message' => 'Unauthenticated'];
        }

        $module = $request->routeParam('module', '');
        $key = $request->routeParam('key', '');
        $qualifiedKey = $module . '.' . $key;

        $this->requireModuleEditAccess($ctx, (string) $module);
        $this->settingsService->reset($qualifiedKey, (string) $ctx->userId);

        return ['status' => 'ok', 'data' => [
            'key' => $qualifiedKey,
        ]];
    }

    public function resetModule(Request $request): array
    {
        $ctx = $this->session->getUserContext();
        if ($ctx === null) {
            return ['status' => 'error', 'message' => 'Unauthenticated'];
        }

        $body = array_replace($request->body, $request->json);
        $keys = $body['keys'] ?? [];
        if (!is_array($keys) || $keys === []) {
            throw new ValidationException(['keys' => 'At least one setting key is required.']);
        }

        $module = $request->routeParam('module', '');
        if ($module === '') {
            throw new ValidationException(['module' => 'Module is required.']);
        }

        $this->requireModuleEditAccess($ctx, $module);
        foreach ($keys as $key) {
            $key = trim((string) $key);
            if ($key === '') {
                continue;
            }

            $this->settingsService->reset($module . '.' . $key, (string) $ctx->userId);
        }

        return ['status' => 'ok', 'data' => [
            'module' => $module,
            'values' => $this->settingsService->getAllForModule($module),
        ]];
    }

    private function requireModuleEditAccess(\WorkEddy\Platform\Session\UserContext $ctx, string $module): void
    {
        $metadata = $this->registry->getPageMetadata($module);
        if ($metadata === null || !$metadata->canEdit($ctx)) {
            throw new ForbiddenException('You do not have permission to update settings for this module.');
        }
    }
}
