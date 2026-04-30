<?php

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\EnsureFullSession;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EnsureFullSessionTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function returns_401_when_no_user(): void
    {
        $middleware = new EnsureFullSession();
        $request = Request::create('/protected', 'GET');

        $response = $middleware->handle($request, fn () => null);

        $this->assertSame(401, $response->getStatusCode());
        $payload = json_decode($response->getContent(), true);
        $this->assertSame('UNAUTHENTICATED', $payload['error_code']);
    }

    #[Test]
    public function returns_403_when_token_missing_full_ability(): void
    {
        $middleware = new EnsureFullSession();
        $request = Request::create('/protected', 'GET');

        $token = Mockery::mock(PersonalAccessToken::class);
        $token->shouldReceive('can')->with('full')->andReturn(false);

        $user = Mockery::mock();
        $user->shouldReceive('currentAccessToken')->andReturn($token);

        $request->setUserResolver(fn () => $user);

        $response = $middleware->handle($request, fn () => null);

        $this->assertSame(403, $response->getStatusCode());
        $payload = json_decode($response->getContent(), true);
        $this->assertSame('VERIFICATION_REQUIRED', $payload['error_code']);
        $this->assertFalse($payload['is_verified']);
    }

    #[Test]
    public function returns_403_when_no_current_access_token(): void
    {
        $middleware = new EnsureFullSession();
        $request = Request::create('/protected', 'GET');

        $user = Mockery::mock();
        $user->shouldReceive('currentAccessToken')->andReturn(null);

        $request->setUserResolver(fn () => $user);

        $response = $middleware->handle($request, fn () => null);

        $this->assertSame(403, $response->getStatusCode());
    }

    #[Test]
    public function passes_through_when_token_has_full_ability(): void
    {
        $middleware = new EnsureFullSession();
        $request = Request::create('/protected', 'GET');

        $token = Mockery::mock(PersonalAccessToken::class);
        $token->shouldReceive('can')->with('full')->andReturn(true);

        $user = Mockery::mock();
        $user->shouldReceive('currentAccessToken')->andReturn($token);

        $request->setUserResolver(fn () => $user);

        $next = fn ($req) => response('ok', 200);

        $response = $middleware->handle($request, $next);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('ok', $response->getContent());
    }
}
