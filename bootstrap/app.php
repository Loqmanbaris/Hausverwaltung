<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

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
        // Zu große Uploads (zum Beispiel viele Fotos auf einmal) freundlich abfangen.
        $exceptions->render(function (\Illuminate\Http\Exceptions\PostTooLargeException $e, Request $request) {
            if ($request->expectsJson()) {
                return null;
            }

            return response(
                '<!DOCTYPE html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
                .'<title>Dateien zu groß</title></head><body style="font:16px/1.5 system-ui,sans-serif;max-width:32rem;margin:2rem auto;padding:0 1rem">'
                .'<h1>Die Dateien sind zu groß</h1>'
                .'<p>Der Server hat die Dateien nicht angenommen, weil sie zusammen zu groß sind. '
                .'Bitte weniger oder kleinere Fotos auf einmal auswählen. Es wurde nichts gespeichert.</p>'
                .'<p><a href="javascript:history.back()">Zurück zum Formular</a></p></body></html>',
                413
            );
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
