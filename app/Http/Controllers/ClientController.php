<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClientRequest;
use App\Http\Requests\ListRequest;
use App\Http\Resources\ClientResource;
use App\Models\Client;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ClientController extends Controller
{
    public function index(ListRequest $request): AnonymousResourceCollection
    {
        $query = Client::query()->with('assignedUser');
        if ($request->user()->role !== 'admin') {
            $query->where('assigned_user_id', $request->user()->id);
        }

        return ClientResource::collection($query->orderBy('id')->paginate($request->validated('per_page', 25)));
    }

    public function store(ClientRequest $request): ClientResource
    {
        $data = $request->validated();
        if (array_key_exists('assigned_user_id', $data) && $data['assigned_user_id'] !== null) {
            $data['assigned_user_id'] = User::where('uuid', $data['assigned_user_id'])->firstOrFail()->id;
        }

        return new ClientResource(Client::create($data)->refresh()->load('assignedUser'));
    }

    public function show(Request $request, Client $client): ClientResource
    {
        abort_unless($request->user()->role === 'admin' || $client->assigned_user_id === $request->user()->id, 404);

        return new ClientResource($client->load('assignedUser'));
    }

    public function update(ClientRequest $request, Client $client): ClientResource
    {
        $data = $request->validated();
        if (array_key_exists('assigned_user_id', $data) && $data['assigned_user_id'] !== null) {
            $data['assigned_user_id'] = User::where('uuid', $data['assigned_user_id'])->firstOrFail()->id;
        }
        $client->update($data);

        return new ClientResource($client->refresh()->load('assignedUser'));
    }

    public function destroy(Request $request, Client $client): Response
    {
        abort_unless($request->user()->role === 'admin', 403);
        try {
            $client->delete();
        } catch (QueryException $exception) {
            if (! in_array((string) $exception->getCode(), ['23000', '23503'], true)) {
                throw $exception;
            }
            abort(409, 'This record is still referenced. Reassign related records before deleting it.');
        }

        return response()->noContent();
    }
}
