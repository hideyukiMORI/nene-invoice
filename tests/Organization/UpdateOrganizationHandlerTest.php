<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Organization;

use Nene2\Http\JsonResponseFactory;
use Nene2\Routing\Router;
use Nene2\Validation\ValidationException;
use NeneInvoice\Organization\Organization;
use NeneInvoice\Organization\UpdateOrganizationHandler;
use NeneInvoice\Organization\UpdateOrganizationInput;
use NeneInvoice\Organization\UpdateOrganizationUseCaseInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Boundary coverage for `PATCH /admin/organizations/{id}`: partial fields are
 * captured (with `is_active: false` treated as a real value, not absence), and
 * an empty or mistyped patch is a 422 (ValidationException).
 */
final class UpdateOrganizationHandlerTest extends TestCase
{
    private Psr17Factory $psr17;
    private UpdateOrganizationHandler $handler;
    private UpdateOrganizationUseCaseInterface $useCase;

    protected function setUp(): void
    {
        $this->psr17 = new Psr17Factory();

        $this->useCase = new class () implements UpdateOrganizationUseCaseInterface {
            public ?UpdateOrganizationInput $captured = null;
            public ?int $capturedId = null;

            public function execute(?int $actorUserId, int $id, UpdateOrganizationInput $input): Organization
            {
                $this->captured = $input;
                $this->capturedId = $id;

                return new Organization(
                    name: $input->name ?? 'Acme',
                    slug: 'acme',
                    plan: $input->plan ?? 'free',
                    isActive: $input->isActive ?? true,
                    id: $id,
                );
            }
        };

        $this->handler = new UpdateOrganizationHandler($this->useCase, new JsonResponseFactory($this->psr17, $this->psr17));
    }

    private function captured(): ?UpdateOrganizationInput
    {
        // @phpstan-ignore-next-line property.notFound (anonymous-class spy field)
        return $this->useCase->captured;
    }

    /** @param array<string, mixed> $body */
    private function request(array $body): ServerRequestInterface
    {
        return $this->psr17->createServerRequest('PATCH', '/admin/organizations/5')
            ->withAttribute(Router::PARAMETERS_ATTRIBUTE, ['id' => '5'])
            ->withAttribute('nene2.auth.claims', ['sub' => 7, 'org' => null, 'role' => 'superadmin'])
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psr17->createStream((string) json_encode($body)));
    }

    public function test_suspends_via_is_active_false_and_captures_id(): void
    {
        $response = $this->handler->handle($this->request(['is_active' => false]));

        self::assertSame(200, $response->getStatusCode());
        $captured = $this->captured();
        self::assertNotNull($captured);
        self::assertFalse($captured->isActive);
        self::assertNull($captured->name);
        self::assertNull($captured->plan);
        // @phpstan-ignore-next-line property.notFound (anonymous-class spy field)
        self::assertSame(5, $this->useCase->capturedId);
    }

    public function test_captures_name_and_plan(): void
    {
        $this->handler->handle($this->request(['name' => 'Renamed', 'plan' => 'pro']));

        $captured = $this->captured();
        self::assertNotNull($captured);
        self::assertSame('Renamed', $captured->name);
        self::assertSame('pro', $captured->plan);
        self::assertNull($captured->isActive);
    }

    public function test_rejects_empty_patch(): void
    {
        // A valid but empty JSON object `{}` — nothing to change → 422.
        $request = $this->psr17->createServerRequest('PATCH', '/admin/organizations/5')
            ->withAttribute(Router::PARAMETERS_ATTRIBUTE, ['id' => '5'])
            ->withAttribute('nene2.auth.claims', ['sub' => 7, 'org' => null, 'role' => 'superadmin'])
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psr17->createStream('{}'));

        $this->expectException(ValidationException::class);
        $this->handler->handle($request);
    }

    public function test_rejects_non_boolean_is_active(): void
    {
        $this->expectException(ValidationException::class);
        $this->handler->handle($this->request(['is_active' => 'yes']));
    }

    public function test_rejects_empty_name(): void
    {
        $this->expectException(ValidationException::class);
        $this->handler->handle($this->request(['name' => '']));
    }
}
