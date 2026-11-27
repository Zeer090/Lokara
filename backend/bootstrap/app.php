<?php

use App\Http\Middleware\Peran;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Illuminate\Http\Request as HttpRequest;

// Build and configure the application
$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('api/*') ? null : route('login'));
        $middleware->alias(['peran' => Peran::class]);


    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*') || $request->wantsJson()) {
                $pesan = collect($e->errors())->flatten()->first()
                    ?? 'Data yang dikirim tidak valid.';
                return response()->json(['galat' => $pesan], 400);
            }
    
    });
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->wantsJson()) {
                return response()->json(['galat' => 'Anda belum masuk atau token tidak valid.'], 401);
            }
    
    });
        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->wantsJson()) {
                return response()->json(['galat' => $e->getMessage() ?: 'Anda tidak memiliki akses untuk tindakan ini.'], 403);
            }
    
    });
        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if ($request->is('api/*') || $request->wantsJson()) {
                return response()->json(['galat' => 'Data tidak ditemukan.'], 404);
            }
    
    });
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->wantsJson()) {
                return response()->json(['galat' => 'Endpoint tidak ditemukan.'], 404);
            }
    
    });
        $exceptions->render(function (TooManyRequestsHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->wantsJson()) {
                return response()->json(['galat' => 'Terlalu banyak permintaan. Coba lagi nanti.'], 429);
            }
    
    });
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e, Request $request) {
            if ($request->is('api/*') || $request->wantsJson()) {
                return response()->json(['galat' => $e->getMessage()], $e->getStatusCode());
            }
    
    });
        $exceptions->render(function (\Throwable $e, Request $request) {
            \Illuminate\Support\Facades\Log::error($e);
            if ($request->is('api/*') || $request->wantsJson()) {
                $pesan = config('app.debug')
                    ? $e->getMessage()
                    : 'Terjadi kesalahan pada server.';
                $code = method_exists($e, 'getStatusCode')
                    ? $e->getStatusCode()
                    : 500;
                if ($code < 400 || $code > 599) {
                    $code = 500;
                }
                return response()->json(['galat' => $pesan], $code);
            }
    
    });

    })
    ->create();

// Bind the request early for Sanctum compatibility (now $app is an Application instance)
$app->instance('request', HttpRequest::capture());

return $app;