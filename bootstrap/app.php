<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Los forms de modales y menús van por HTMX. Si la validación falla,
        // el redirect de siempre vuelve como una página entera que htmx
        // descarta, y el form queda igual sin ningún aviso. A esos pedidos se
        // les contesta 422 con el primer error, que htmx-config.js muestra
        // como toast (`formInvalid`).
        $exceptions->render(function (ValidationException $e, Request $request) {
            if (! $request->header('HX-Request')) {
                return null;
            }

            return response('', 422)->withHeaders([
                'HX-Trigger' => json_encode([
                    'formInvalid' => ['message' => $e->validator->errors()->first()],
                ]),
            ]);
        });
    })->create();
