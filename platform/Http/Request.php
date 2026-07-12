<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Http;

final class Request
{
    /**
     * @param array<string, string> $headers
     * @param array<string, mixed> $query
     * @param array<string, mixed> $body
     * @param array<string, mixed> $json
     * @param array<string, mixed> $files
     * @param array<string, string> $routeParams
     */
    public function __construct(
        public readonly string $method,
        public readonly string $uri,
        public readonly array $headers = [],
        public readonly array $query = [],
        public readonly array $body = [],
        public readonly array $json = [],
        public readonly array $files = [],
        public readonly array $routeParams = [],
        private readonly ?string $clientIp = null,
        public readonly array $attributes = [],
    ) {}

    public static function capture(): self
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headers[strtolower(str_replace('_', '-', substr($key, 5)))] = (string) $value;
            }
        }

        $rawBody = file_get_contents('php://input') ?: '';
        $json = [];
        if ($rawBody !== '') {
            $decoded = json_decode($rawBody, true);
            if ($decoded === null && json_last_error() !== JSON_ERROR_NONE && str_contains(strtolower($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json')) {
                throw new \WorkEddy\Shared\Exceptions\ValidationException(
                    ['json' => 'MALFORMED_JSON'],
                    'Malformed JSON request body',
                );
            }
            $json = is_array($decoded) ? $decoded : [];
        }

        return new self(
            method: $_SERVER['REQUEST_METHOD'] ?? 'GET',
            uri: parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/',
            headers: $headers,
            query: $_GET,
            body: $_POST,
            json: $json,
            files: $_FILES,
            clientIp: $_SERVER['REMOTE_ADDR'] ?? null,
            attributes: [],
        );
    }

    /**
     * @param array<string, string> $routeParams
     */
    public function withRouteParams(array $routeParams): self
    {
        return new self(
            method: $this->method,
            uri: $this->uri,
            headers: $this->headers,
            query: $this->query,
            body: $this->body,
            json: $this->json,
            files: $this->files,
            routeParams: $routeParams,
            clientIp: $this->clientIp,
            attributes: $this->attributes,
        );
    }

    public function withAttribute(string $key, mixed $value): self
    {
        $attributes = $this->attributes;
        $attributes[$key] = $value;

        return new self(
            method: $this->method,
            uri: $this->uri,
            headers: $this->headers,
            query: $this->query,
            body: $this->body,
            json: $this->json,
            files: $this->files,
            routeParams: $this->routeParams,
            clientIp: $this->clientIp,
            attributes: $attributes,
        );
    }

    public function attribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->json[$key] ?? $this->body[$key] ?? $default;
    }

    public function header(string $name, ?string $default = null): ?string
    {
        $needle = strtolower($name);
        foreach ($this->headers as $key => $value) {
            if (strtolower($key) === $needle) {
                return $value;
            }
        }

        return $default;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function route(string $key, ?string $default = null): ?string
    {
        return $this->routeParam($key, $default);
    }

    public function routeParam(string $key, ?string $default = null): ?string
    {
        return $this->routeParams[$key] ?? $default;
    }

    public function getClientIp(): ?string
    {
        return $this->clientIp;
    }

    public function expectsJson(): bool
    {
        return str_starts_with($this->uri, '/api') || str_contains($this->header('accept', '') ?? '', 'application/json');
    }

    public function bearerToken(): ?string
    {
        $auth = $this->header('authorization');
        if ($auth !== null && str_starts_with($auth, 'Bearer ')) {
            return substr($auth, 7);
        }

        return null;
    }


    // --- Getters ---

    public function getMethod(): string
    {
        return $this->method;
    }
    public function getUri(): string
    {
        return $this->uri;
    }
}
