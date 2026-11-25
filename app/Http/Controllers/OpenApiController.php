<?php

namespace App\Http\Controllers;

use Dedoc\Scramble\CacheableGenerator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class OpenApiController extends Controller
{
    public function __invoke(CacheableGenerator $generator): JsonResponse
    {
        try {
            DB::select('SELECT 1');
        } catch (QueryException $exception) {
            report($exception);

            return response()->json([
                'message' => 'Cannot connect to the configured database. Check MySQL credentials in .env, then run php artisan config:clear.',
            ], 503);
        }

        $spec = $generator();

        foreach (Route::getRoutes() as $route) {
            if (in_array('PATCH', $route->methods(), true) && str_starts_with($route->uri(), 'api/v1/')) {
                $path = '/'.substr($route->uri(), 7);
                $path = preg_replace('/\{([^}:]+):[^}]+\}/', '{$1}', $path);
                if (isset($spec['paths'][$path]['put'])) {
                    $spec['paths'][$path]['patch'] = $spec['paths'][$path]['put'];
                    $spec['paths'][$path]['patch']['operationId'] .= 'Patch';
                }
            }
        }

        return response()->json($spec);
    }

    public function ui(): View
    {
        return view('swagger');
    }

    public function asset(string $asset): BinaryFileResponse
    {
        $contentType = match ($asset) {
            'swagger-ui.css' => 'text/css',
            'swagger-ui-bundle.js', 'swagger-ui-standalone-preset.js' => 'application/javascript',
            default => abort(404),
        };

        return response()->file(base_path('vendor/swagger-api/swagger-ui/dist/'.$asset), [
            'Content-Type' => $contentType,
        ]);
    }
}
