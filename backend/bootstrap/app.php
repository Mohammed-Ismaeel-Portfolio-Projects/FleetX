<?php

use App\Http\Middleware\EnsureUserHasRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenExpiredException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenInvalidException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        [
            'prefix' => 'api/v1',
            'middleware' => ['api', 'auth:api'],
        ],
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (AuthenticationException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
        });

        $exceptions->render(function (UnauthorizedHttpException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Unauthorized.'], 401);
            }
        });

        $exceptions->render(function (TokenInvalidException $e, $request) {
            return response()->json(['message' => 'Token is Invalid'], 401);
        });

        $exceptions->render(function (TokenExpiredException $e, $request) {
            return response()->json(['message' => 'Token is Expired'], 401);
        });

        $exceptions->render(function (JWTException $e, $request) {
            return response()->json(['message' => 'Authorization Token not found'], 401);
        });
    })->create();
