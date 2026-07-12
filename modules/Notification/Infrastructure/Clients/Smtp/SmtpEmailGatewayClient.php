<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Notification\Infrastructure\Clients\Smtp;

use WorkEddy\Modules\Notification\Domain\ProviderEntry;
use WorkEddy\Modules\Notification\Infrastructure\Clients\EmailGatewayClientInterface;
use WorkEddy\Modules\Notification\Infrastructure\Clients\FailureType;
use WorkEddy\Modules\Notification\Infrastructure\Clients\Payload\EmailPayload;
use WorkEddy\Modules\Notification\Infrastructure\Clients\ProviderSendResult;
use WorkEddy\Platform\Clock\IClock;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

final class SmtpEmailGatewayClient implements EmailGatewayClientInterface
{
    public function __construct(private readonly IClock $clock) {}

    public function sendEmail(EmailPayload $payload, ProviderEntry $provider): ProviderSendResult
    {
        $host = $provider->getConfigValue('host', '127.0.0.1');
        $port = (int) $provider->getConfigValue('port', 2525);
        $user = $provider->getConfigValue('user', '');
        $pass = $provider->getConfigValue('pass', '');
        $encryption = $provider->getConfigValue('encryption', 'tls');

        // Build DSN
        $scheme = 'smtp';
        // Symfony Mailer DSN: smtp://user:pass@host:port
        // If user is empty, smtp://host:port

        $credentials = '';
        if ($user !== '') {
            $credentials = urlencode($user);
            if ($pass !== '') {
                $credentials .= ':' . urlencode($pass);
            }
            $credentials .= '@';
        }

        $dsn = sprintf('%s://%s%s:%d', $scheme, $credentials, $host, $port);

        try {
            $transport = Transport::fromDsn($dsn);
            $mailer = new Mailer($transport);

            $email = (new Email())
                ->from(sprintf('%s <%s>', $payload->fromName ?? 'BrowseMX', $payload->fromEmail ?? 'noreply@browsemx.local'))
                ->to($payload->toEmail)
                ->subject($payload->subject);

            if ($payload->replyToEmail !== null && $payload->replyToEmail !== '') {
                $replyTo = $payload->replyToName !== null && $payload->replyToName !== ''
                    ? sprintf('%s <%s>', $payload->replyToName, $payload->replyToEmail)
                    : $payload->replyToEmail;
                $email->replyTo($replyTo);
            }

            if ($payload->isHtml) {
                $email->html($payload->body);
            } else {
                $email->text($payload->body);
            }

            $mailer->send($email);

            return new ProviderSendResult(
                success: true,
                provider: 'smtp',
                providerMessageId: 'email-' . uniqid(),
                sentAt: $this->clock->now()
            );
        } catch (TransportExceptionInterface $e) {
            return new ProviderSendResult(
                success: false,
                provider: 'smtp',
                errorMessage: 'SMTP Network error: ' . $e->getMessage(),
                failureType: FailureType::TEMPORARY_FAILURE
            );
        } catch (\Throwable $e) {
            return new ProviderSendResult(
                success: false,
                provider: 'smtp',
                errorMessage: 'SMTP error: ' . $e->getMessage(),
                failureType: FailureType::TEMPORARY_FAILURE
            );
        }
    }
}
