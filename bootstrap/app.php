<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // SetTenant tiene que correr ANTES que SubstituteBindings: si no, el
        // route-model-binding de {branch}, {caja}, {warehouse}… resuelve cuando
        // todavía no hay empresa activa, el CompanyScope está en fail-closed y
        // toda ruta de ver/editar/borrar devuelve 404 incluso a su dueño.
        //
        // Se sustituye SubstituteBindings por SetTenant en su sitio (después de
        // StartSession, para que auth()->user() ya resuelva) y se vuelve a
        // encolar justo detrás.
        $middleware->replaceInGroup(
            'web',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \App\Http\Middleware\SetTenant::class,
        );

        $middleware->appendToGroup('web', [
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \App\Http\Middleware\EnsureSubscriptionActive::class,
        ]);

        // La API repite el MISMO orden que el grupo web, y por el mismo motivo:
        // SetTenant antes que SubstituteBindings, o el binding de {appointment},
        // {client}… resolvería sin empresa activa y devolvería 404 a su dueño.
        //
        // Aquí no hay sesión: la empresa viaja en la cabecera X-Company-Id, y
        // SetTenant comprueba que el usuario pertenezca a ella.
        $middleware->replaceInGroup(
            'api',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \App\Http\Middleware\SetTenant::class,
        );

        $middleware->appendToGroup('api', [
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \App\Http\Middleware\EnsureSubscriptionActive::class,
        ]);

        $middleware->alias([
            'set-tenant' => \App\Http\Middleware\SetTenant::class,
            'subscription' => \App\Http\Middleware\EnsureSubscriptionActive::class,
            'plan' => \App\Http\Middleware\CheckPlanModule::class,
            'check-role' => \App\Http\Middleware\CheckRole::class,
            'check-company' => \App\Http\Middleware\CheckCompany::class,
            'check-permission' => \App\Http\Middleware\CheckPermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
