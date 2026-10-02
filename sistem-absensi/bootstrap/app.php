<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role'      => \App\Http\Middleware\RoleMiddleware::class,
            'admin.access' => \App\Http\Middleware\AdminAccessMiddleware::class,
            // Tahap 4B: privilege middleware — proteksi halaman berdasarkan privilege
            'privilege' => \App\Http\Middleware\PrivilegeMiddleware::class,
            'superadmin.org' => \App\Http\Middleware\CheckSuperAdminOrganization::class,
            'org.context' => \App\Http\Middleware\EnsureOrganizationContext::class,
            'feature' => \App\Http\Middleware\EnsureFeatureEnabled::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\SessionTimeout::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {

        // ── Session expired / CSRF token mismatch ──────────────────────
        // Catches: 419 Page Expired errors when session expires.
        // Redirects users gracefully to login page with clear feedback.
        $exceptions->render(function (\Illuminate\Session\TokenMismatchException $e, Request $request) {
            $message = 'Sesi Anda telah berakhir. Silakan login kembali.';

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $message,
                    'error'   => 'session_expired',
                ], 419);
            }

            return redirect()->route('login')
                ->with('error', $message)
                ->withErrors(['message' => $message]);
        });

        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, Request $request) {
            if ($e->getStatusCode() === 419) {
                $message = 'Sesi Anda telah berakhir. Silakan login kembali.';

                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => $message,
                        'error'   => 'session_expired',
                    ], 419);
                }

                return redirect()->route('login')
                    ->with('error', $message)
                    ->withErrors(['message' => $message]);
            }

            return null;
        });

        // ── Database connection errors ─────────────────────────────────
        // Catches: DNS failure, host unreachable, connection refused,
        // SSL handshake errors, etc. — anything that prevents reaching DB.
        // Shows a friendly message instead of the Laravel error page.
        $dbConnectionCodes = ['08000', '08003', '08006', '08001', '08004', 'HY000'];

        $exceptions->render(function (QueryException $e, Request $request) use ($dbConnectionCodes) {
            $sqlState = (string) ($e->getCode() ?? '');
            $message  = strtolower($e->getMessage());

            // Detect connection-level failures (not query logic errors)
            $isConnectionError =
                in_array($sqlState, $dbConnectionCodes) ||
                str_contains($message, 'could not translate host name') ||
                str_contains($message, 'unknown host') ||
                str_contains($message, 'connection refused') ||
                str_contains($message, 'connection timed out') ||
                str_contains($message, 'no route to host') ||
                str_contains($message, 'could not connect to server') ||
                str_contains($message, 'network is unreachable') ||
                str_contains($message, 'ssl') ;

            if (! $isConnectionError) {
                return null; // let Laravel handle other DB errors normally
            }

            $friendlyMessage = 'Tidak dapat terhubung ke database. '
                . 'Periksa koneksi internet Anda dan coba lagi.';

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $friendlyMessage,
                    'error'   => 'database_connection_failed',
                ], 503);
            }

            return redirect('/login')
                ->with('error', $friendlyMessage);
        });

    })->create();
