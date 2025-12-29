<?php

use Illuminate\Support\Facades\Route;
use PROLANCEE\DYNAMIC\CRUD\Api\App\Http\Controllers\{
    SanctumController,
    AdmotumController
};
use PROLANCEE\DYNAMIC\CRUD\Api\Classes\Routes\RouteRegistrar;

/*
|--------------------------------------------------------------------------
| PROLANCEE DYNAMIC CRUD INTERNAL API ROUTES
|--------------------------------------------------------------------------
| - Base middleware: prolancee.dynamic.crud.api
| - Throttling: prolancee-api
| - Auth applied per controller (Sanctum / Admotum)
*/

$baseMiddleware = [
    'prolancee.dynamic.crud.api',
    'throttle:prolancee-api',
];

/*
|--------------------------------------------------------------------------
| Admotum Token Generation (Public)
|--------------------------------------------------------------------------
*/
Route::post(
    '/{v}/admotum/generate/access-token',
    [AdmotumController::class, 'generateAccessTokenControl']
)->middleware($baseMiddleware);

/*
|--------------------------------------------------------------------------
| Sanctum-Protected Routes
|--------------------------------------------------------------------------
*/
RouteRegistrar::register(
    [
        ...$baseMiddleware,
        'prolancee.dynamic.crud.api.sanctum',
    ],
    SanctumController::class,
    'sanctum'
);

/*
|--------------------------------------------------------------------------
| Admotum-Protected Routes
|--------------------------------------------------------------------------
*/
RouteRegistrar::register(
    [
        ...$baseMiddleware,
        'prolancee.dynamic.crud.api.admotum',
    ],
    AdmotumController::class,
    'admotum'
);
