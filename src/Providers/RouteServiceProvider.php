<?php

namespace PROLANCEE\DYNAMIC\CRUD\Api\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use PROLANCEE\DYNAMIC\CRUD\Api\App\Http\Middleware\{
    ValidateIntermediateRoutes,
    ForceJsonResponse,
    Admotum,
    Sanctum
};
use PROLANCEE\DYNAMIC\CRUD\Api\Classes\Responsor\RouterResponder;
use PROLANCEE\Support\Classes\Config\AppMetaData;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

class RouteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        $router     = $this->app['router'];
        $startPoint = AppMetaData::getStartPoint(['end' => '/*'], 'api');

        /* ---------------------------------
         | REGISTER MIDDLEWARE ALIASES
         --------------------------------- */
        $router->aliasMiddleware(
            'prolancee.dynamic.crud.api.sanctum',
            Sanctum::class
        );

        $router->aliasMiddleware(
            'prolancee.dynamic.crud.api.admotum',
            Admotum::class
        );

        /* ---------------------------------
         | PACKAGE MIDDLEWARE GROUP
         --------------------------------- */
        $router->middlewareGroup('prolancee.dynamic.crud.api', [
            ValidateIntermediateRoutes::class,
            ForceJsonResponse::class,
        ]);

        /* ---------------------------------
         | RATE LIMITING
         --------------------------------- */
        $this->configureRateLimiting();

        /* ---------------------------------
         | PACKAGE FALLBACK (404)
         --------------------------------- */
        Route::fallback(function () use ($startPoint) {
            if (request()->is($startPoint)) {
                $exception = RouterResponder::validateRoute(
                    'routeNotFound',
                    request()->path()
                );

                if ($exception instanceof JsonResponse) {
                    return $exception;
                }
            }
        });

        /* ---------------------------------
         | CUSTOM 405 HANDLER
         --------------------------------- */
        $this->app->make('Illuminate\Contracts\Debug\ExceptionHandler')
            ->renderable(function (MethodNotAllowedHttpException $e, $req) use ($startPoint) {
                if ($req->is($startPoint)) {
                    $exception = RouterResponder::validateRoute(
                        'methodNotAllowed',
                        $req->path()
                    );

                    if ($exception instanceof JsonResponse) {
                        return $exception;
                    }
                }
            });

        /* ---------------------------------
         | ROUTES (SKIP IF CACHED)
         --------------------------------- */
        if ($this->app->routesAreCached()) {
            return;
        }

        /* ---------------------------------
         | LOAD ROUTES
         --------------------------------- */
        Route::prefix(AppMetaData::getStartPoint([], 'api'))
            ->middleware([
                'prolancee.dynamic.crud.api',
                'throttle:prolancee-api',
            ])
            ->group(__DIR__ . '/../routes/api.php');
    }

    /**
     * Define rate limiting rules for API requests.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('prolancee-api', function (Request $req) {
            return Limit::perMinute(60)
                ->by($req->user()?->id ?: $req->ip())
                ->response(fn () => response()->json([
                    'message' => 'Too many API requests. Please slow down.'
                ], 429));
        });
    }
}
