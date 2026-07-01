<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Organization;

use Nene2\Http\JsonResponseFactory;
use Nene2\Validation\ValidationException;
use NeneInvoice\Organization\CreateOrganizationHandler;
use NeneInvoice\Organization\CreateOrganizationInput;
use NeneInvoice\Organization\CreateOrganizationUseCaseInterface;
use NeneInvoice\Organization\Organization;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Boundary coverage for the initial-admin fields on `POST /admin/organizations`.
 * They are both-or-neither and, when present, must be a valid email plus a
 * password meeting the operator policy — otherwise a 422 (ValidationException).
 */
final class CreateOrganizationHandlerTest extends TestCase
{
    private Psr17Factory $psr17;
    private CreateOrganizationHandler $handler;
    private CreateOrganizationUseCaseInterface $useCase;

    protected function setUp(): void
    {
        $this->psr17 = new Psr17Factory();

        $this->useCase = new class () implements CreateOrganizationUseCaseInterface {
            public ?CreateOrganizationInput $captured = null;

            public function execute(?int $actorUserId, CreateOrganizationInput $input): Organization
            {
                $this->captured = $input;

                return new Organization($input->name, $input->slug, $input->plan, true, 1);
            }
        };

        $this->handler = new CreateOrganizationHandler($this->useCase, new JsonResponseFactory($this->psr17, $this->psr17));
    }

    private function captured(): ?CreateOrganizationInput
    {
        // @phpstan-ignore-next-line property.notFound (anonymous-class spy field)
        return $this->useCase->captured;
    }

    /** @param array<string, mixed> $body */
    private function request(array $body): ServerRequestInterface
    {
        return $this->psr17->createServerRequest('POST', '/admin/organizations')
            ->withAttribute('nene2.auth.claims', ['sub' => 7, 'org' => null, 'role' => 'superadmin'])
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psr17->createStream((string) json_encode($body)));
    }

    public function test_creates_org_only_when_admin_fields_absent(): void
    {
        $response = $this->handler->handle($this->request(['name' => 'Acme', 'slug' => 'acme']));

        self::assertSame(201, $response->getStatusCode());
        $captured = $this->captured();
        self::assertNotNull($captured);
        self::assertNull($captured->adminEmail);
        self::assertNull($captured->adminPassword);
    }

    public function test_passes_through_valid_initial_admin(): void
    {
        $response = $this->handler->handle($this->request([
            'name' => 'Beta',
            'slug' => 'beta',
            'admin_email' => 'owner@beta.example',
            'admin_password' => 'correct horse battery',
        ]));

        self::assertSame(201, $response->getStatusCode());
        $captured = $this->captured();
        self::assertNotNull($captured);
        self::assertSame('owner@beta.example', $captured->adminEmail);
        self::assertSame('correct horse battery', $captured->adminPassword);
    }

    public function test_rejects_admin_email_without_password(): void
    {
        $this->expectException(ValidationException::class);
        $this->handler->handle($this->request(['name' => 'A', 'slug' => 'a', 'admin_email' => 'owner@a.example']));
    }

    public function test_rejects_admin_password_without_email(): void
    {
        $this->expectException(ValidationException::class);
        $this->handler->handle($this->request(['name' => 'A', 'slug' => 'a', 'admin_password' => 'correct horse battery']));
    }

    public function test_rejects_invalid_admin_email(): void
    {
        $this->expectException(ValidationException::class);
        $this->handler->handle($this->request([
            'name' => 'A',
            'slug' => 'a',
            'admin_email' => 'not-an-email',
            'admin_password' => 'correct horse battery',
        ]));
    }

    public function test_rejects_short_admin_password(): void
    {
        $this->expectException(ValidationException::class);
        $this->handler->handle($this->request([
            'name' => 'A',
            'slug' => 'a',
            'admin_email' => 'owner@a.example',
            'admin_password' => 'short',
        ]));
    }
}
