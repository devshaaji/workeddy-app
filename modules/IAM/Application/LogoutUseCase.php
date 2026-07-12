<?php

/**
 * LogoutUseCase — destroy session, audit.
 */

declare(strict_types=1);

namespace WorkEddy\Modules\IAM\Application;

use WorkEddy\Platform\Session\ISessionService;
use WorkEddy\Platform\Audit\IAuditService;

use WorkEddy\Platform\Logging\ILoggerFactory;
use WorkEddy\Platform\Session\UserContext;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class LogoutUseCase
{
    private LoggerInterface $logger;

    public function __construct(
        private readonly ISessionService $session,
        private readonly IAuditService   $audit,
        ?ILoggerFactory $loggerFactory = null,
    ) {
        $this->logger = $loggerFactory?->channel('security') ?? new NullLogger();
    }

    public function execute(?UserContext $ctx): void
    {
        if ($ctx !== null) {
            $this->audit->record(
                action: 'iam.logout',
                entityType: 'User',
                entityId: (string) $ctx->userId,
                afterState: ['module' => 'IAM'],
                actorId: (string) $ctx->userId,
            );

            $this->logger->info('User logged out.', [
                'userId' => $ctx->userId,
                'roleType' => $ctx->roleType,

            ]);
        }

        $this->session->destroy();
    }
}
