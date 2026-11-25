<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerStatusListRequest;
use App\Http\Requests\CustomerStatusRequest;
use App\Http\Resources\CustomerStatusResource;
use App\Models\CustomerStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerStatusController extends Controller
{
    /** List customer statuses. available_for optionally excludes statuses disabled for a workflow. */
    public function index(CustomerStatusListRequest $request): AnonymousResourceCollection
    {
        $query = CustomerStatus::query();
        if ($workflow = $request->validated('available_for')) {
            $query->where(fn (Builder $query): Builder => $query
                ->whereNull('disabled_for')->orWhereJsonDoesntContain('disabled_for', $workflow));
        }

        return CustomerStatusResource::collection(
            $query->orderBy('name')->orderBy('id')->paginate($request->validated('per_page', 25)),
        );
    }

    /**
     * Create a customer status.
     *
     * Color requires a six-digit hex value such as #22c55e. is_default defaults to false,
     * disabled_for defaults to []. Choosing a default clears the previous default atomically.
     */
    public function store(CustomerStatusRequest $request): CustomerStatusResource
    {
        return new CustomerStatusResource($this->saveStatus($request));
    }

    /**
     * Get a customer status by UUID.
     */
    public function show(CustomerStatus $customerStatus): CustomerStatusResource
    {
        return new CustomerStatusResource($customerStatus);
    }

    /**
     * Update a customer status.
     *
     * Omitted fields are preserved. disabled_for replaces the workflow list; [] enables both.
     * Only one status may be default. Existing customer assignments are not changed.
     */
    public function update(CustomerStatusRequest $request, CustomerStatus $customerStatus): CustomerStatusResource
    {
        return new CustomerStatusResource($this->saveStatus($request, $customerStatus));
    }

    /**
     * Delete an unused status. Statuses assigned to customers cannot be deleted (409).
     */
    public function destroy(Request $request, CustomerStatus $customerStatus): Response
    {
        abort_unless($request->user()->role === 'admin', 403);

        try {
            $customerStatus->delete();
        } catch (QueryException $exception) {
            if (! in_array((string) $exception->getCode(), ['23000', '23503'], true)) {
                throw $exception;
            }

            abort(409, 'This status is assigned to customers. Reassign them before deleting it.');
        }

        return response()->noContent();
    }

    private function saveStatus(CustomerStatusRequest $request, ?CustomerStatus $customerStatus = null): CustomerStatus
    {
        try {
            return DB::transaction(function () use ($request, $customerStatus): CustomerStatus {
                if ($request->boolean('is_default')) {
                    CustomerStatus::query()->where('is_default', true)
                        ->when($customerStatus, fn (Builder $query): Builder => $query->whereKeyNot($customerStatus->id))
                        ->update(['is_default' => false]);
                }

                if ($customerStatus !== null) {
                    $customerStatus = CustomerStatus::query()->lockForUpdate()->findOrFail($customerStatus->id);
                    $customerStatus->update($request->validated());
                } else {
                    $customerStatus = CustomerStatus::create(['disabled_for' => [], ...$request->validated()]);
                }

                return $customerStatus->refresh();
            }, attempts: 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'is_default' => ['The default status changed during this request. Please try again.'],
            ]);
        }
    }
}
