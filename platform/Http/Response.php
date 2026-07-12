<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Http;

final class Response
{
    /**
     * @param array<string, string> $headers
     * @param string|\Closure(): void $body
     */
    public function __construct(
        private string|\Closure $body,
        private int $status = 200,
        private array $headers = [],
    ) {}

    /**
     * @param array<string, mixed> $payload
     */
    public static function json(array $payload, int $status = 200): self
    {
        return new self(json_encode($payload, JSON_THROW_ON_ERROR), $status, ['Content-Type' => 'application/json']);
    }

    public static function html(string $html, int $status = 200): self
    {
        return new self($html, $status, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    /**
     * @param array<string, string> $headers
     * @param callable(): void $stream
     */
    public static function stream(callable $stream, int $status = 200, array $headers = []): self
    {
        return new self($stream(...), $status, $headers);
    }

    /**
     * @param array<string, string>|null $errors
     */
    public static function error(string $message, int $status, ?array $errors = null, ?string $code = null): self
    {
        return self::json(['code' => $code ?? self::defaultErrorCode($status), 'message' => $message, 'errors' => $errors ?? []], $status);
    }

    public static function success(array $payload, string $message = 'Operation successful', int $status = 200): self
    {
        return self::json(['status' => 'ok', 'message' => $message, 'data' => $payload], $status);
    }

    public static function redirect(string $location, int $status = 302): self
    {
        return new self('', $status, ['Location' => $location]);
    }

    public function withHeader(string $name, string $value): self
    {
        $clone = clone $this;
        $clone->headers[$name] = $value;

        return $clone;
    }

    public function getStatusCode(): int
    {
        return $this->status;
    }

    public function getBody(): string
    {
        if ($this->body instanceof \Closure) {
            ob_start();
            ($this->body)();

            return (string) ob_get_clean();
        }

        return $this->body;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }

        if ($this->body instanceof \Closure) {
            ($this->body)();
            return;
        }

        echo $this->body;
    }

    private static function defaultErrorCode(int $status): string
    {
        return match ($status) {
            400 => 'BAD_REQUEST',
            401 => 'UNAUTHENTICATED',
            403 => 'FORBIDDEN',
            404 => 'NOT_FOUND',
            405 => 'METHOD_NOT_ALLOWED',
            409 => 'CONFLICT',
            422 => 'UNPROCESSABLE_ENTITY',
            429 => 'TOO_MANY_REQUESTS',
            default => 'INTERNAL_ERROR',
        };
    }
}
