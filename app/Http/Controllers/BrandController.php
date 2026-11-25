<?php

namespace App\Http\Controllers;

use App\Http\Requests\BrandRequest;
use App\Http\Requests\ListRequest;
use App\Http\Resources\BrandResource;
use App\Models\Brand;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class BrandController extends Controller
{
    public function index(ListRequest $request): AnonymousResourceCollection
    {
        $query = Brand::query();

        return BrandResource::collection($query->orderBy('id')->paginate($request->validated('per_page', 25)));
    }

    public function store(BrandRequest $request): BrandResource
    {
        $data = $request->validated();
        if (isset($data['transactional_email_templates'])) {
            $data['transactional_email_templates'] = (object) $data['transactional_email_templates'];
        }

        return new BrandResource(Brand::create($data)->refresh());
    }

    public function show(Request $request, Brand $brand): BrandResource
    {

        return new BrandResource($brand);
    }

    public function update(BrandRequest $request, Brand $brand): BrandResource
    {
        $data = $request->validated();
        if (isset($data['transactional_email_templates'])) {
            $data['transactional_email_templates'] = (object) $data['transactional_email_templates'];
        }

        $brand->update($data);

        return new BrandResource($brand->refresh());
    }

    public function destroy(Request $request, Brand $brand): Response
    {
        abort_unless($request->user()->role === 'admin', 403);
        try {
            $brand->delete();
        } catch (QueryException $exception) {
            if (! in_array((string) $exception->getCode(), ['23000', '23503'], true)) {
                throw $exception;
            }
            abort(409, 'This record is still referenced. Reassign related records before deleting it.');
        }

        return response()->noContent();
    }
}
