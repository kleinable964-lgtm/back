<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\CustomerStatusController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\DeskController;
use App\Http\Controllers\LookupController;
use App\Http\Controllers\OpenApiController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\EnsureActiveUser;
use Dedoc\Scramble\Http\Middleware\RestrictedDocsAccess;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/health', function (): JsonResponse {
    return response()->json([
        'status' => 'ok',
        'service' => config('app.name'),
    ]);
})->name('api.health');

Route::get('/user', function (Request $request): ?Authenticatable {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/openapi.json', OpenApiController::class)
    ->middleware(RestrictedDocsAccess::class)
    ->name('api.openapi');

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('auth/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::middleware(['auth:sanctum', EnsureActiveUser::class, 'throttle:120,1'])->group(function (): void {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::patch('auth/preferences', [ProfileController::class, 'updatePreferences']);
        Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
        Route::apiResource('users', UserController::class)->only(['index', 'show'])->scoped(['user' => 'uuid']);
        Route::apiResource('desks', DeskController::class)->only(['index', 'show']);
        Route::apiResource('departments', DepartmentController::class)->only(['index', 'show']);
        Route::apiResource('brands', BrandController::class)->only(['index', 'show']);
        Route::apiResource('clients', ClientController::class)->only(['index', 'show']);
        Route::apiResource('customer-statuses', CustomerStatusController::class)->only(['index', 'show'])
            ->parameters(['customer-statuses' => 'customerStatus']);
        foreach (['countries', 'affiliates'] as $lookup) {
            Route::get($lookup, [LookupController::class, 'index'])->defaults('lookup', $lookup)->name($lookup.'.index');
        }
        Route::middleware('can:manage-crm')->group(function (): void {
            Route::apiResource('users', UserController::class)->only(['store', 'update'])->scoped(['user' => 'uuid']);
            Route::apiResource('desks', DeskController::class)->only(['store', 'update', 'destroy']);
            Route::apiResource('departments', DepartmentController::class)->only(['store', 'update', 'destroy']);
            Route::put('desks/{desk}/users', [DeskController::class, 'members']);
            Route::apiResource('brands', BrandController::class)->only(['store', 'update', 'destroy']);
            Route::apiResource('clients', ClientController::class)->only(['store', 'update', 'destroy']);
            Route::apiResource('customer-statuses', CustomerStatusController::class)->only(['store', 'update', 'destroy'])
                ->parameters(['customer-statuses' => 'customerStatus']);
        });
    });
});
