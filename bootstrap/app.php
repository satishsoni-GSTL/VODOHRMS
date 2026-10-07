<?php

use App\Http\Middleware\AuthenticateBiometricDevice;
use App\Http\Middleware\AuthenticateMobileDevice;
use App\Models\WorkflowDefinition;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'auth.biometric-device' => AuthenticateBiometricDevice::class,
            'auth.mobile' => AuthenticateMobileDevice::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Mobile app: a request type whose approval workflow isn't set up yet gets a clear
        // message instead of a bare 404.
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            $missing = $e->getPrevious();

            if ($request->is('api/mobile/*') && $missing instanceof ModelNotFoundException && $missing->getModel() === WorkflowDefinition::class) {
                return response()->json([
                    'message' => 'This request type has no approval workflow configured yet. Please contact HR.',
                ], 422);
            }
        });
    })->create();
