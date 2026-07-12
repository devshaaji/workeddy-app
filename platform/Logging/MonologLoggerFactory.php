<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Logging;

use WorkEddy\Platform\Config\ConfigLoader;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class MonologLoggerFactory implements ILoggerFactory
{
    public function __construct(private readonly string $logDirectory, private readonly string $level = 'info') {}

    public static function fromConfig(ConfigLoader $config): self
    {
        return new self(
            (string) $config->get('logging.root', dirname(__DIR__, 2) . '/var/log'),
            (string) $config->get('logging.level', $config->get('LOG_LEVEL', 'info')),
        );
    }

    public function channel(string $name): LoggerInterface
    {
        if (!class_exists(\Monolog\Logger::class) || !class_exists(\Monolog\Handler\StreamHandler::class)) {
            return new NullLogger();
        }

        try {
            if (!is_dir($this->logDirectory)) {
                @mkdir($this->logDirectory, 0775, true);
            }

            $logger = new \Monolog\Logger($name);
            $logger->pushHandler(new \Monolog\Handler\StreamHandler(
                $this->logDirectory . '/' . $name . '.log',
                \Monolog\Level::fromName($this->level),
            ));

            return $logger;
        } catch (\Throwable) {
            return new NullLogger();
        }
    }
}
