<?php

namespace App\Http\Controllers;

use App\Http\Requests\DepartmentRequest;
use App\Http\Requests\ListRequest;
use App\Http\Resources\DepartmentResource;
use App\Models\Department;
use App\Models\Desk;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DepartmentController extends Controller
{
    /** List departments with their attached desk summaries, including inactive departments. */
    public function index(ListRequest $request): AnonymousResourceCollection
    {
        return DepartmentResource::collection(
            Department::with('desks:id,name,department_id')
                ->orderBy('name')->orderBy('id')
                ->paginate($request->validated('per_page', 25)),
        );
    }

    /**
     * Create a department.
     *
     * isactive defaults to true. Optional desk_ids attaches currently unassigned desks.
     */
    public function store(DepartmentRequest $request): DepartmentResource
    {
        $department = DB::transaction(function () use ($request): Department {
            $department = Department::create($this->attributes($request));
            if ($request->has('desk_ids')) {
                $this->syncDesks($department, $request->validated('desk_ids'));
            }

            return $department;
        });

        return new DepartmentResource($department->refresh()->load('desks:id,name,department_id'));
    }

    /** Get a department with attached_desks containing only desk id and name. */
    public function show(Department $department): DepartmentResource
    {
        return new DepartmentResource($department->load('desks:id,name,department_id'));
    }

    /**
     * Update a department.
     *
     * Omitted fields are unchanged. desk_ids replaces all attachments; [] detaches all.
     * Desks belonging to another department return 422 and must be reassigned explicitly.
     */
    public function update(DepartmentRequest $request, Department $department): DepartmentResource
    {
        $department = DB::transaction(function () use ($request, $department): Department {
            $department = Department::query()->lockForUpdate()->findOrFail($department->id);
            $department->update($this->attributes($request));
            if ($request->has('desk_ids')) {
                $this->syncDesks($department, $request->validated('desk_ids'));
                $department->touch();
            }

            return $department;
        });

        return new DepartmentResource($department->refresh()->load('desks:id,name,department_id'));
    }

    /** Delete an empty department. Attached desks prevent deletion (409); detach them first. */
    public function destroy(Request $request, Department $department): Response
    {
        abort_unless($request->user()->role === 'admin', 403);

        try {
            $department->delete();
        } catch (QueryException $exception) {
            if (! in_array((string) $exception->getCode(), ['23000', '23503'], true)) {
                throw $exception;
            }

            abort(409, 'This department still has attached desks. Detach or reassign them before deleting it.');
        }

        return response()->noContent();
    }

    /** @return array{name?: string, description?: string|null, is_active?: bool} */
    private function attributes(DepartmentRequest $request): array
    {
        $attributes = $request->safe()->only(['name', 'description']);
        if ($request->has('isactive')) {
            $attributes['is_active'] = $request->boolean('isactive');
        }

        return $attributes;
    }

    /** @param list<string> $deskIds */
    private function syncDesks(Department $department, array $deskIds): void
    {
        $desks = Desk::whereIn('id', $deskIds)->orderBy('id')->lockForUpdate()->get(['id', 'department_id']);
        if ($desks->count() !== count($deskIds)) {
            throw ValidationException::withMessages(['desk_ids' => ['One or more desks no longer exist.']]);
        }

        foreach ($desks as $desk) {
            if ($desk->department_id !== null && $desk->department_id !== $department->id) {
                throw ValidationException::withMessages([
                    'desk_ids' => ['A selected desk already belongs to another department. Reassign it explicitly before attaching it here.'],
                ]);
            }
        }

        $department->desks()->whereNotIn('id', $deskIds)->update(['department_id' => null]);
        Desk::whereIn('id', $deskIds)->update(['department_id' => $department->id]);
    }
}
