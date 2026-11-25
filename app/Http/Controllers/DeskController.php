<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeskMembershipRequest;
use App\Http\Requests\DeskRequest;
use App\Http\Requests\ListRequest;
use App\Http\Resources\DeskResource;
use App\Models\Desk;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class DeskController extends Controller
{
    public function index(ListRequest $request): AnonymousResourceCollection
    {
        $query = Desk::query()->with('users');

        return DeskResource::collection($query->orderBy('id')->paginate($request->validated('per_page', 25)));
    }

    public function store(DeskRequest $request): DeskResource
    {
        $data = $request->validated();

        return new DeskResource(Desk::create($data)->refresh()->load('users'));
    }

    public function show(Request $request, Desk $desk): DeskResource
    {

        return new DeskResource($desk->load('users'));
    }

    public function update(DeskRequest $request, Desk $desk): DeskResource
    {
        $data = $request->validated();

        $desk->update($data);

        return new DeskResource($desk->refresh()->load('users'));
    }

    public function destroy(Request $request, Desk $desk): Response
    {
        abort_unless($request->user()->role === 'admin', 403);
        try {
            $desk->delete();
        } catch (QueryException $exception) {
            if (! in_array((string) $exception->getCode(), ['23000', '23503'], true)) {
                throw $exception;
            }
            abort(409, 'This record is still referenced. Reassign related records before deleting it.');
        }

        return response()->noContent();
    }

    public function members(DeskMembershipRequest $request, Desk $desk): DeskResource
    {
        $ids = User::whereIn('uuid', $request->validated('user_ids'))->pluck('id');
        $desk->users()->sync($ids);

        return new DeskResource($desk->load('users'));
    }
}
