<?php

declare(strict_types=1);

use WorkEddy\Modules\Organization\Presentation\OrganizationController;
use WorkEddy\Modules\Organization\Presentation\OrganizationPageController;
use WorkEddy\Platform\Http\RouteRegistrar;

return function (RouteRegistrar $routes): void {
    $uuid = '[0-9a-fA-F-]{36}';

    $routes->group('', function (RouteRegistrar $web) use ($uuid): void {
        $web->add('GET', '/organizations', [OrganizationPageController::class, 'index'], ['auth']);
        $web->add('GET', '/organizations/{id:' . $uuid . '}', [OrganizationPageController::class, 'show'], ['auth']);
        $web->add('GET', '/organizations/{id:' . $uuid . '}/worksites', [OrganizationPageController::class, 'worksites'], ['auth']);
        $web->add('GET', '/organizations/{id:' . $uuid . '}/pilot-sites', [OrganizationPageController::class, 'pilotSites'], ['auth']);
        $web->add('GET', '/organizations/{id:' . $uuid . '}/departments', [OrganizationPageController::class, 'departments'], ['auth']);
        $web->add('GET', '/organizations/{id:' . $uuid . '}/job-roles', [OrganizationPageController::class, 'jobRoles'], ['auth']);
        $web->add('GET', '/organizations/{id:' . $uuid . '}/members', [OrganizationPageController::class, 'members'], ['auth']);
    });

    $routes->group('/api/v1', function (RouteRegistrar $api) use ($uuid): void {
        $api->add('GET', '/organizations', [OrganizationController::class, 'list'], ['auth']);
        $api->add('POST', '/organizations', [OrganizationController::class, 'create'], ['auth']);
        $api->add('GET', '/organizations/{id:' . $uuid . '}', [OrganizationController::class, 'show'], ['auth']);
        $api->add('PUT', '/organizations/{id:' . $uuid . '}', [OrganizationController::class, 'update'], ['auth']);
        $api->add('PATCH', '/organizations/{id:' . $uuid . '}/status', [OrganizationController::class, 'updateStatus'], ['auth']);
        $api->add('GET', '/organizations/{id:' . $uuid . '}/assignable-roles', [OrganizationController::class, 'listAssignableRoles'], ['auth']);
        $api->add('GET', '/organizations/{id:' . $uuid . '}/members', [OrganizationController::class, 'listMembers'], ['auth']);
        $api->add('POST', '/organizations/{id:' . $uuid . '}/members', [OrganizationController::class, 'inviteMember'], ['auth']);
        $api->add('PUT', '/organizations/{id:' . $uuid . '}/members/{memberId:' . $uuid . '}', [OrganizationController::class, 'updateMember'], ['auth']);
        $api->add('DELETE', '/organizations/{id:' . $uuid . '}/members/{memberId:' . $uuid . '}', [OrganizationController::class, 'removeMember'], ['auth']);
        $api->add('GET', '/organizations/{id:' . $uuid . '}/worksites', [OrganizationController::class, 'listWorksites'], ['auth']);
        $api->add('POST', '/organizations/{id:' . $uuid . '}/worksites', [OrganizationController::class, 'createWorksite'], ['auth']);
        $api->add('GET', '/organizations/{id:' . $uuid . '}/worksites/{worksiteId:' . $uuid . '}', [OrganizationController::class, 'showWorksite'], ['auth']);
        $api->add('PUT', '/organizations/{id:' . $uuid . '}/worksites/{worksiteId:' . $uuid . '}', [OrganizationController::class, 'updateWorksite'], ['auth']);
        $api->add('DELETE', '/organizations/{id:' . $uuid . '}/worksites/{worksiteId:' . $uuid . '}', [OrganizationController::class, 'deleteWorksite'], ['auth']);
        $api->add('GET', '/organizations/{id:' . $uuid . '}/pilot-sites', [OrganizationController::class, 'listPilotSites'], ['auth']);
        $api->add('POST', '/organizations/{id:' . $uuid . '}/pilot-sites', [OrganizationController::class, 'createPilotSite'], ['auth']);
        $api->add('PUT', '/organizations/{id:' . $uuid . '}/pilot-sites/{pilotSiteId:' . $uuid . '}', [OrganizationController::class, 'updatePilotSite'], ['auth']);
        $api->add('GET', '/organizations/{id:' . $uuid . '}/departments', [OrganizationController::class, 'listDepartments'], ['auth']);
        $api->add('POST', '/organizations/{id:' . $uuid . '}/departments', [OrganizationController::class, 'createDepartment'], ['auth']);
        $api->add('GET', '/organizations/{id:' . $uuid . '}/departments/{departmentId:' . $uuid . '}', [OrganizationController::class, 'showDepartment'], ['auth']);
        $api->add('PUT', '/organizations/{id:' . $uuid . '}/departments/{departmentId:' . $uuid . '}', [OrganizationController::class, 'updateDepartment'], ['auth']);
        $api->add('DELETE', '/organizations/{id:' . $uuid . '}/departments/{departmentId:' . $uuid . '}', [OrganizationController::class, 'deleteDepartment'], ['auth']);
        $api->add('GET', '/organizations/{id:' . $uuid . '}/job-roles', [OrganizationController::class, 'listJobRoles'], ['auth']);
        $api->add('POST', '/organizations/{id:' . $uuid . '}/job-roles', [OrganizationController::class, 'createJobRole'], ['auth']);
        $api->add('GET', '/organizations/{id:' . $uuid . '}/job-roles/{jobRoleId:' . $uuid . '}', [OrganizationController::class, 'showJobRole'], ['auth']);
        $api->add('PUT', '/organizations/{id:' . $uuid . '}/job-roles/{jobRoleId:' . $uuid . '}', [OrganizationController::class, 'updateJobRole'], ['auth']);
        $api->add('DELETE', '/organizations/{id:' . $uuid . '}/job-roles/{jobRoleId:' . $uuid . '}', [OrganizationController::class, 'deleteJobRole'], ['auth']);
    });
};
