<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Http;

final class MiddlewarePipeline
{
    /** @var list<IMiddleware> */
    private array $middleware = [];

    public function pipe(IMiddleware ...$middleware): self
    {
        foreach ($middleware as $item) {
            $this->middleware[] = $item;
        }

        return $this;
    }

    /**
     * @param callable(Request): Response $handler
     */
    public function handle(Request $request, callable $handler): Response
    {
        $pipeline = array_reduce(
            array_reverse($this->middleware),
            static fn(callable $next, IMiddleware $middleware): callable =>
            static fn(Request $req): Response => $middleware->process($req, $next),
            $handler,
        );

        return $pipeline($request);
    }
}
