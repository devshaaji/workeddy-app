<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Notification\Infrastructure\Templates;

use WorkEddy\Modules\Notification\Contracts\TemplateRendererInterface;
use WorkEddy\Modules\Notification\Domain\NotificationChannel;
use WorkEddy\Modules\Notification\Domain\NotificationType;

final class FileTemplateRenderer implements TemplateRendererInterface
{
    public function __construct(
        private readonly string $templateDirectory
    ) {}

    public function render(NotificationType $type, NotificationChannel $channel, array $data): string
    {
        $file = $this->getTemplateFile($type, $channel);
        if (!file_exists($file)) {
            throw new \RuntimeException(sprintf('Template file not found: %s', $file));
        }

        extract($data);
        ob_start();
        include $file;
        return ob_get_clean();
    }

    public function getSubject(NotificationType $type, NotificationChannel $channel, array $data): ?string
    {
        $subjectFile = $this->getSubjectFile($type, $channel);
        if (file_exists($subjectFile)) {
            extract($data);
            ob_start();
            include $subjectFile;
            return trim(ob_get_clean());
        }

        return 'Notification: ' . ucfirst(str_replace('_', ' ', $type->value));
    }

    private function getTemplateFile(NotificationType $type, NotificationChannel $channel): string
    {
        return $this->findTemplateFile($type, $channel, false);
    }

    private function getSubjectFile(NotificationType $type, NotificationChannel $channel): string
    {
        return $this->findTemplateFile($type, $channel, true);
    }

    private function findTemplateFile(NotificationType $type, NotificationChannel $channel, bool $subject): string
    {
        $suffix = $subject ? '.subject.php' : '.php';
        $base = rtrim($this->templateDirectory, '/');

        $candidates = [];

        $channels = [$channel];
        if ($channel === NotificationChannel::IN_APP) {
            $channels[] = NotificationChannel::EMAIL;
        }
        if ($channel === NotificationChannel::WHATSAPP) {
            $channels[] = NotificationChannel::SMS;
            $channels[] = NotificationChannel::EMAIL;
        }
        if ($channel === NotificationChannel::SMS) {
            $channels[] = NotificationChannel::WHATSAPP;
            $channels[] = NotificationChannel::EMAIL;
        }

        foreach ($channels as $candidateChannel) {
            $candidates[] = sprintf(
                '%s/%s/%s.%s%s',
                $base,
                $candidateChannel->value,
                $type->value,
                $candidateChannel->value,
                $suffix
            );

            // Legacy misplaced file support, e.g. email/iam.auth_otp.sms.php
            $candidates[] = sprintf(
                '%s/%s/%s.%s%s',
                $base,
                NotificationChannel::EMAIL->value,
                $type->value,
                $candidateChannel->value,
                $suffix
            );
        }

        foreach ($candidates as $candidate) {
            if (file_exists($candidate)) {
                return $candidate;
            }
        }

        return $candidates[0];
    }
}
