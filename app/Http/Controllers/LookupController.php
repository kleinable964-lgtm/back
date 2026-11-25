<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListRequest;
use App\Models\Affiliate;
use App\Models\Country;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

class LookupController extends Controller
{
    public function index(ListRequest $request, string $lookup): AnonymousResourceCollection
    {
        [$model, $columns] = match ($lookup) {
            'countries' => [Country::class, ['id', 'name', 'iso_code']],
            'affiliates' => [Affiliate::class, ['id', 'name']],
            default => abort(404),
        };

        return JsonResource::collection($model::query()->select($columns)->orderBy('id')->paginate($request->validated('per_page', 25)));
    }
}
