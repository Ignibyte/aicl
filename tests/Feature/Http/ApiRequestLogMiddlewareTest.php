<?php

namespace Aicl\Tests\Feature\Http;

use Aicl\Http\Middleware\ApiRequestLogMiddleware;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ApiRequestLogMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    // ── Logging Behavior ────────────────────────────────────

    public function test_logs_api_request_details(): void
    {
        $channelMock = \Mockery::mock(\Psr\Log\LoggerInterface::class);
        $channelMock->shouldReceive('info')
            ->once()
            ->with('API Request', \Mockery::on(function (array $context) {
                return $context['method'] === 'GET'
                    && $context['path'] === 'api/v1/widgets'
                    && $context['status'] === 200
                    && is_float($context['duration_ms'])
                    && array_key_exists('ip', $context)
                    && array_key_exists('user_agent', $context);
            }));

        Log::shouldReceive('channel')
            ->with('api-requests')
            ->once()
            ->andReturn($channelMock);

        $middleware = new ApiRequestLogMiddleware;
        $request = Request::create('/api/v1/widgets', 'GET');
        $response = $middleware->handle($request, fn () => new Response('ok'));

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_logs_method_and_path(): void
    {
        $channelMock = \Mockery::mock(\Psr\Log\LoggerInterface::class);
        $channelMock->shouldReceive('info')
            ->once()
            ->with('API Request', \Mockery::on(function (array $context) {
                return $context['method'] === 'POST'
                    && $context['path'] === 'api/v1/users';
            }));

        Log::shouldReceive('channel')
            ->with('api-requests')
            ->once()
            ->andReturn($channelMock);

        $middleware = new ApiRequestLogMiddleware;
        $request = Request::create('/api/v1/users', 'POST');
        $middleware->handle($request, fn () => new Response('created', 201));
    }

    public function test_logs_null_user_id_for_unauthenticated(): void
    {
        $channelMock = \Mockery::mock(\Psr\Log\LoggerInterface::class);
        $channelMock->shouldReceive('info')
            ->once()
            ->with('API Request', \Mockery::on(function (array $context) {
                return $context['user_id'] === null;
            }));

        Log::shouldReceive('channel')
            ->with('api-requests')
            ->once()
            ->andReturn($channelMock);

        $middleware = new ApiRequestLogMiddleware;
        $request = Request::create('/api/v1/test', 'GET');
        $middleware->handle($request, fn () => new Response('ok'));
    }

    public function test_logs_user_id_for_authenticated(): void
    {
        $user = User::factory()->create();

        $channelMock = \Mockery::mock(\Psr\Log\LoggerInterface::class);
        $channelMock->shouldReceive('info')
            ->once()
            ->with('API Request', \Mockery::on(function (array $context) use ($user) {
                return $context['user_id'] === $user->getKey();
            }));

        Log::shouldReceive('channel')
            ->with('api-requests')
            ->once()
            ->andReturn($channelMock);

        $middleware = new ApiRequestLogMiddleware;
        $request = Request::create('/api/v1/test', 'GET');
        $request->setUserResolver(fn () => $user);
        $middleware->handle($request, fn () => new Response('ok'));
    }

    public function test_logs_response_status(): void
    {
        $channelMock = \Mockery::mock(\Psr\Log\LoggerInterface::class);
        $channelMock->shouldReceive('info')
            ->once()
            ->with('API Request', \Mockery::on(function (array $context) {
                return $context['status'] === 404;
            }));

        Log::shouldReceive('channel')
            ->with('api-requests')
            ->once()
            ->andReturn($channelMock);

        $middleware = new ApiRequestLogMiddleware;
        $request = Request::create('/api/v1/missing', 'GET');
        $middleware->handle($request, fn () => new Response('not found', 404));
    }

    public function test_response_is_returned_unchanged(): void
    {
        $channelMock = \Mockery::mock(\Psr\Log\LoggerInterface::class);
        $channelMock->shouldReceive('info')->once();

        Log::shouldReceive('channel')
            ->with('api-requests')
            ->once()
            ->andReturn($channelMock);

        $middleware = new ApiRequestLogMiddleware;
        $request = Request::create('/api/v1/test', 'PUT');

        $originalResponse = new Response('updated', 200, ['X-Custom' => 'value']);
        $response = $middleware->handle($request, fn () => $originalResponse);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('updated', $response->getContent());
        $this->assertSame('value', $response->headers->get('X-Custom'));
    }
}
