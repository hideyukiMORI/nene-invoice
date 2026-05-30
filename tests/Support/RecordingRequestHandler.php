<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Support;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Test double that records the request it received and returns a 200. Used to
 * assert what a middleware passed downstream (e.g. request attributes).
 */
final class RecordingRequestHandler implements RequestHandlerInterface
{
    public ?ServerRequestInterface $seen = null;

    public function __construct(private readonly Psr17Factory $psr17 = new Psr17Factory())
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->seen = $request;

        return $this->psr17->createResponse(200);
    }
}
