<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Desk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DepartmentApiTest extends TestCase
{
    use RefreshDatabase;

    private function signIn(string $role = 'admin', string $status = 'active'): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => $role, 'status' => $status]));
    }

    public function test_department_endpoints_require_active_authentication(): void
    {
        $this->getJson('/api/v1/departments')->assertUnauthorized();
        $this->postJson('/api/v1/departments', ['name' => 'Conversion'])->assertUnauthorized();
        $this->signIn(status: 'pending');
        $this->getJson('/api/v1/departments')->assertForbidden();
    }

    public function test_active_coworkers_can_read_but_cannot_manage_departments(): void
    {
        $department = Department::factory()->create();
        $this->signIn('agent');
        $this->getJson('/api/v1/departments')->assertOk();
        $this->getJson('/api/v1/departments/'.$department->id)->assertOk();
        $this->postJson('/api/v1/departments', ['name' => 'New'])->assertForbidden();
        $this->patchJson('/api/v1/departments/'.$department->id, ['isactive' => false])->assertForbidden();
        $this->deleteJson('/api/v1/departments/'.$department->id)->assertForbidden();
    }

    public function test_admin_can_create_a_department_with_exact_fields_and_desk_summaries(): void
    {
        $this->signIn();
        $desk = Desk::factory()->create(['name' => 'Conversion A']);
        $response = $this->postJson('/api/v1/departments', [
            'name' => 'Conversion',
            'description' => 'New client relationships',
            'isactive' => true,
            'desk_ids' => [$desk->id],
        ])->assertCreated();

        $id = $response->json('data.id');
        $department = Department::findOrFail($id);
        $response->assertExactJson(['data' => [
            'id' => $id,
            'name' => 'Conversion',
            'description' => 'New client relationships',
            'isactive' => true,
            'created_at' => $department->created_at->toISOString(),
            'updated_at' => $department->updated_at->toISOString(),
            'attached_desks' => [['id' => $desk->id, 'name' => 'Conversion A']],
        ]]);
        $this->assertTrue($desk->fresh()->department->is(Department::findOrFail($id)));
        $this->assertDatabaseHas('departments', ['id' => $id, 'is_active' => true]);
    }

    public function test_department_defaults_and_empty_attachments_are_explicit(): void
    {
        $this->signIn();
        $this->postJson('/api/v1/departments', ['name' => 'Retention'])
            ->assertCreated()
            ->assertJsonPath('data.description', null)
            ->assertJsonPath('data.isactive', true)
            ->assertJsonPath('data.attached_desks', []);
    }

    public function test_timestamp_migration_preserves_legacy_departments_and_unknown_dates(): void
    {
        $this->signIn();
        $department = Department::factory()->create(['name' => 'Legacy']);
        $desk = Desk::factory()->for($department)->create();
        $migration = require database_path('migrations/2026_11_18_103331_add_timestamps_to_departments_table.php');
        $migration->down();
        $migration->up();

        $this->getJson('/api/v1/departments/'.$department->id)->assertOk()
            ->assertJsonPath('data.name', 'Legacy')
            ->assertJsonPath('data.created_at', null)
            ->assertJsonPath('data.updated_at', null)
            ->assertJsonPath('data.attached_desks.0.id', $desk->id);

        $this->patchJson('/api/v1/departments/'.$department->id, ['desk_ids' => []])->assertOk()
            ->assertJsonPath('data.created_at', null)
            ->assertJsonPath('data.updated_at', $department->fresh()->updated_at->toISOString());
        $this->assertNull($desk->fresh()->department_id);
    }

    public function test_description_accepts_multibyte_text_within_the_character_limit(): void
    {
        $this->signIn();
        $description = str_repeat('é', 40000);
        $this->postJson('/api/v1/departments', ['name' => 'International', 'description' => $description])
            ->assertCreated()->assertJsonPath('data.description', $description);
    }

    public function test_list_is_paginated_and_includes_inactive_departments_and_all_attached_desks(): void
    {
        $this->signIn();
        $department = Department::factory()->create(['name' => 'A', 'is_active' => false]);
        Department::factory()->create(['name' => 'B']);
        $desk = Desk::factory()->for($department)->create(['name' => 'Inactive desk', 'is_active' => false]);
        $this->getJson('/api/v1/departments?per_page=1')
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.isactive', false)
            ->assertJsonPath('data.0.attached_desks', [['id' => $desk->id, 'name' => $desk->name]]);
        $this->getJson('/api/v1/departments?per_page=101')
            ->assertUnprocessable()->assertJsonValidationErrors('per_page');
    }

    public function test_update_preserves_omitted_fields_and_attachments(): void
    {
        $this->signIn();
        $department = Department::factory()->create(['name' => 'Conversion', 'description' => 'Original']);
        $desk = Desk::factory()->for($department)->create();
        $this->patchJson('/api/v1/departments/'.$department->id, ['isactive' => false])
            ->assertOk()->assertJsonPath('data.name', 'Conversion')
            ->assertJsonPath('data.description', 'Original')
            ->assertJsonPath('data.isactive', false)
            ->assertJsonPath('data.attached_desks.0.id', $desk->id);
        $this->assertFalse($department->fresh()->is_active);
        $this->assertTrue($desk->fresh()->is_active);
    }

    public function test_put_can_replace_attachments_and_explicitly_clear_description(): void
    {
        $this->signIn();
        $department = Department::factory()->create(['description' => 'Original']);
        $old = Desk::factory()->for($department)->create();
        $replacement = Desk::factory()->create();
        $this->putJson('/api/v1/departments/'.$department->id, [
            'name' => 'Renamed', 'description' => null, 'desk_ids' => [$replacement->id],
        ])->assertOk()->assertJsonPath('data.name', 'Renamed')
            ->assertJsonPath('data.description', null)
            ->assertJsonPath('data.attached_desks', [['id' => $replacement->id, 'name' => $replacement->name]]);
        $this->assertNull($old->fresh()->department_id);
        $this->assertSame($department->id, $replacement->fresh()->department_id);
        $this->patchJson('/api/v1/departments/'.$department->id, ['desk_ids' => []])
            ->assertOk()->assertJsonPath('data.attached_desks', []);
        $this->assertNull($replacement->fresh()->department_id);
        $this->assertModelExists($old);
        $this->assertModelExists($replacement);
    }

    public function test_department_fields_and_attachment_inputs_are_validated(): void
    {
        $this->signIn();
        $this->postJson('/api/v1/departments', [])->assertUnprocessable()->assertJsonValidationErrors('name');

        foreach ([
            ['name' => '   '],
            ['name' => str_repeat('a', 256)],
            ['description' => ['invalid']],
            ['description' => str_repeat('a', 65536)],
            ['isactive' => 'invalid'],
            ['isactive' => null],
            ['desk_ids' => null],
            ['desk_ids' => 'not-an-array'],
            ['desk_ids' => ['named-key' => 'f8a6eca6-eec1-49de-88f2-4527d1409e39']],
            ['attached_desks' => [['id' => 'read-only', 'name' => 'Read only']]],
            ['is_active' => false],
        ] as $invalid) {
            $field = array_key_first($invalid);
            $this->postJson('/api/v1/departments', array_merge(['name' => 'Valid'], $invalid))
                ->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->assertDatabaseCount('departments', 0);
    }

    public function test_invalid_missing_or_duplicate_desk_ids_are_rejected(): void
    {
        $this->signIn();
        $desk = Desk::factory()->create();
        foreach ([
            ['not-a-uuid'],
            ['f8a6eca6-eec1-49de-88f2-4527d1409e39'],
            [$desk->id, $desk->id],
        ] as $ids) {
            $this->postJson('/api/v1/departments', ['name' => 'New', 'desk_ids' => $ids])
                ->assertUnprocessable()->assertJsonValidationErrors('desk_ids.0');
        }
        $this->assertNull($desk->fresh()->department_id);
    }

    public function test_desks_cannot_be_taken_from_another_department_and_failed_updates_roll_back(): void
    {
        $this->signIn();
        $target = Department::factory()->create(['name' => 'Original']);
        $existing = Desk::factory()->for($target)->create();
        $other = Department::factory()->create();
        $assigned = Desk::factory()->for($other)->create();
        $available = Desk::factory()->create();

        $this->patchJson('/api/v1/departments/'.$target->id, [
            'name' => 'Should not persist', 'desk_ids' => [$assigned->id, $available->id],
        ])->assertUnprocessable()->assertJsonValidationErrors('desk_ids');
        $this->assertSame('Original', $target->fresh()->name);
        $this->assertSame($target->id, $existing->fresh()->department_id);
        $this->assertSame($other->id, $assigned->fresh()->department_id);
        $this->assertNull($available->fresh()->department_id);

        $this->postJson('/api/v1/departments', ['name' => 'Rolled back', 'desk_ids' => [$assigned->id]])
            ->assertUnprocessable()->assertJsonValidationErrors('desk_ids');
        $this->assertDatabaseCount('departments', 2);
    }

    public function test_a_department_can_keep_its_existing_desks_during_sync(): void
    {
        $this->signIn();
        $department = Department::factory()->create();
        $desk = Desk::factory()->for($department)->create();
        $this->patchJson('/api/v1/departments/'.$department->id, ['desk_ids' => [$desk->id]])
            ->assertOk()->assertJsonCount(1, 'data.attached_desks');
    }

    public function test_referenced_departments_cannot_be_deleted_and_desks_are_preserved(): void
    {
        $this->signIn();
        $department = Department::factory()->create();
        $desk = Desk::factory()->for($department)->create();
        $this->deleteJson('/api/v1/departments/'.$department->id)->assertConflict();
        $this->assertModelExists($department);
        $this->assertModelExists($desk);
        $this->patchJson('/api/v1/departments/'.$department->id, ['desk_ids' => []])->assertOk();
        $this->deleteJson('/api/v1/departments/'.$department->id)->assertNoContent();
        $this->assertModelExists($desk);
    }

    public function test_unknown_department_ids_return_not_found(): void
    {
        $this->signIn();
        foreach (['not-a-uuid', 'f8a6eca6-eec1-49de-88f2-4527d1409e39'] as $id) {
            $this->getJson('/api/v1/departments/'.$id)->assertNotFound();
            $this->patchJson('/api/v1/departments/'.$id, ['name' => 'New'])->assertNotFound();
            $this->deleteJson('/api/v1/departments/'.$id)->assertNotFound();
        }
    }

    public function test_migration_preserves_existing_identity_only_departments_and_desk_links(): void
    {
        $department = Department::factory()->create();
        $desk = Desk::factory()->for($department)->create();
        $migration = require database_path('migrations/2026_11_17_132111_add_details_to_departments_table.php');
        $migration->down();
        $this->assertSame($department->id, DB::table('departments')->value('id'));
        $migration->up();
        $this->assertNull($department->fresh()->name);
        $this->assertNull($department->fresh()->description);
        $this->assertTrue($department->fresh()->is_active);
        $this->assertSame($department->id, $desk->fresh()->department_id);
    }

    public function test_swagger_documents_department_management_and_request_fields(): void
    {
        Gate::define('viewApiDocs', fn (?User $user = null): bool => true);
        $spec = $this->getJson('/api/openapi.json')->assertOk()->json();
        foreach (['get', 'post'] as $method) {
            $this->assertArrayHasKey($method, $spec['paths']['/departments']);
        }
        foreach (['get', 'put', 'patch', 'delete'] as $method) {
            $this->assertArrayHasKey($method, $spec['paths']['/departments/{department}']);
        }
        $body = $spec['paths']['/departments']['post']['requestBody']['content']['application/json']['schema'];
        if (isset($body['$ref'])) {
            $body = $spec['components']['schemas'][basename($body['$ref'])];
        }
        $this->assertContains('name', $body['required']);
        foreach (['name', 'description', 'isactive', 'desk_ids'] as $field) {
            $this->assertArrayHasKey($field, $body['properties']);
        }
        $this->assertArrayNotHasKey('attached_desks', $body['properties']);
        $this->assertArrayNotHasKey('is_active', $body['properties']);
    }
}
