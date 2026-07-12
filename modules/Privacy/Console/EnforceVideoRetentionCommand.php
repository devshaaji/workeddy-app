<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Privacy\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use WorkEddy\Modules\Assessment\Domain\Contracts\IAssessmentRepository;
use WorkEddy\Modules\Privacy\Application\EnforceVideoRetentionUseCase;
use WorkEddy\Modules\Privacy\Domain\Contracts\IPrivacyRepository;
use WorkEddy\Modules\Privacy\Domain\RetentionPolicy;
use WorkEddy\Platform\Console\Command\CommandLockRunner;
use WorkEddy\Platform\Session\UserContext;

#[AsCommand(name: 'privacy:video-retention:enforce', description: 'Enforce configured video retention policies for completed assessment videos.')]
final class EnforceVideoRetentionCommand extends Command
{
    public function __construct(
        private readonly IPrivacyRepository $privacy,
        private readonly IAssessmentRepository $assessments,
        private readonly EnforceVideoRetentionUseCase $enforceRetention,
        private readonly CommandLockRunner $locks,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Maximum retention policies to inspect.', 100)
            ->addOption('assessment-limit', null, InputOption::VALUE_REQUIRED, 'Maximum assessments to inspect per organization.', 500);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $policyLimit = max(1, min(1000, (int) $input->getOption('limit')));
        $assessmentLimit = max(1, min(5000, (int) $input->getOption('assessment-limit')));

        return $this->locks->run('console:privacy:video-retention:enforce', 900, function () use ($policyLimit, $assessmentLimit, $output): int {
            $processedAssessments = 0;
            $deletedStorageFileUuids = [];
            $policies = $this->privacy->listRetentionPolicies($policyLimit);

            foreach ($policies as $policy) {
                if ($policy->rawVideoPolicy !== RetentionPolicy::RAW_DELETE_AFTER_PROCESSING) {
                    continue;
                }

                $actor = new UserContext(
                    userId: $policy->updatedBy,
                    organizationId: $policy->organizationId,
                    organizationUuid: $policy->organizationUuid,
                    roleType: 'system',
                    privileges: [],
                );

                foreach ($this->assessments->findAllByOrganizationId($policy->organizationId, $assessmentLimit) as $assessment) {
                    if (!$this->hasCompletedVideo($assessment)) {
                        continue;
                    }

                    $result = $this->enforceRetention->execute($assessment->getUuid(), $actor);
                    $processedAssessments++;
                    foreach ($result['deletedStorageFileUuids'] ?? [] as $storageFileUuid) {
                        $deletedStorageFileUuids[] = (string) $storageFileUuid;
                    }
                }
            }

            $output->writeln(json_encode([
                'success' => true,
                'policies_inspected' => count($policies),
                'assessments_processed' => $processedAssessments,
                'deleted_storage_file_uuids' => $deletedStorageFileUuids,
            ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

            return Command::SUCCESS;
        });
    }

    private function hasCompletedVideo(object $assessment): bool
    {
        if (!method_exists($assessment, 'getVideos')) {
            return false;
        }

        foreach ($assessment->getVideos() as $video) {
            if (method_exists($video, 'getProcessingStatus') && $video->getProcessingStatus() === 'completed') {
                return true;
            }
        }

        return false;
    }
}
