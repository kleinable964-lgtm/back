<?php

use App\Http\Controllers\OpenApiController;
use Dedoc\Scramble\Http\Middleware\RestrictedDocsAccess;
use Illuminate\Support\Facades\Route;

Route::middleware(RestrictedDocsAccess::class)->withoutMiddleware('web')->group(function (): void {
    Route::get('/swagger', [OpenApiController::class, 'ui'])->name('swagger');
    Route::get('/swagger/assets/{asset}', [OpenApiController::class, 'asset'])->name('swagger.asset');
});
