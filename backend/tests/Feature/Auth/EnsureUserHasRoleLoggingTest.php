<?php

namespace Tests\Feature\Auth;

use App\Http\Middleware\EnsureUserHasRole;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * INT-031 — EnsureUserHasRole must not log identity/JWT data.
 * Authorization semantics are tested in isolation (no production test route).
 */
class EnsureUserHasRoleLoggingTest extends TestCase
{
    /** @var list<string> */
    private array $loggedMessages = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->loggedMessages = [];
        Event::listen(MessageLogged::class, function (MessageLogged $event): void {
            $this->loggedMessages[] = (string) $event->message;
        });
    }

    public function test_matching_role_is_allowed(): void
    {
        $response = $this->runMiddleware($this->userWithRole(99, 'admin'), 'admin', 'dispatcher');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('ok', $response->getContent());
    }

    public function test_non_matching_role_remains_forbidden(): void
    {
        try {
            $this->runMiddleware($this->userWithRole(99, 'client'), 'admin', 'dispatcher');
            $this->fail('Expected HTTP 403');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_missing_user_remains_forbidden(): void
    {
        try {
            $this->runMiddleware(null, 'admin');
            $this->fail('Expected HTTP 403');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_allowed_request_does_not_log_identity_or_jwt(): void
    {
        $this->loggedMessages = [];
        $this->runMiddleware($this->userWithRole(42, 'admin'), 'admin');

        $this->assertNoIdentityOrJwtLogs(42, 'admin');
        $combined = implode("\n", $this->loggedMessages);
        $this->assertStringNotContainsString('EnsureUserHasRole middleware - Reached', $combined);
        $this->assertStringNotContainsString('EnsureUserHasRole - Access granted', $combined);
        $this->assertStringNotContainsString('Required roles:', $combined);
    }

    public function test_forbidden_request_does_not_log_identity_or_jwt(): void
    {
        $this->loggedMessages = [];

        try {
            $this->runMiddleware($this->userWithRole(42, 'driver'), 'admin');
            $this->fail('Expected HTTP 403');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $this->assertNoIdentityOrJwtLogs(42, 'driver');
        $combined = implode("\n", $this->loggedMessages);
        $this->assertStringNotContainsString('EnsureUserHasRole - Access denied', $combined);
        $this->assertStringNotContainsString('Required roles:', $combined);
    }

    private function assertNoIdentityOrJwtLogs(int $userId, string $roleName): void
    {
        $combined = implode("\n", $this->loggedMessages);

        $this->assertStringNotContainsString('User ID: '.$userId, $combined);
        $this->assertStringNotContainsString('User role: '.$roleName, $combined);
        $this->assertStringNotContainsString('Authorization header', $combined);
        $this->assertStringNotContainsString('JWT', $combined);
        $this->assertStringNotContainsString('Bearer ', $combined);
    }

    private function runMiddleware(?object $user, string ...$roles): Response
    {
        $request = Request::create('/internal-role-check', 'GET');
        $request->setUserResolver(fn () => $user);

        $middleware = new EnsureUserHasRole;
        $result = $middleware->handle(
            $request,
            fn () => new Response('ok', 200),
            ...$roles
        );

        $this->assertInstanceOf(Response::class, $result);

        return $result;
    }

    private function userWithRole(int $id, string $roleName): object
    {
        $role = (object) ['name' => $roleName];

        return (object) [
            'id' => $id,
            'role' => $role,
        ];
    }
}
