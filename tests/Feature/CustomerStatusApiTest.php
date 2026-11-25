<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\CustomerStatus;
use App\Models\User;
use Dedoc\Scramble\Scramble;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerStatusApiTest extends TestCase
{
    use RefreshDatabase;

    private function signIn(string $role = 'admin', string $status = 'active'): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => $role, 'status' => $status]));
    }

    public function test_all_status_endpoints_require_active_authentication(): void
    {
        $status = CustomerStatus::factory()->create();
        $path = '/api/v1/customer-statuses/'.$status->id;
        $this->getJson('/api/v1/customer-statuses')->assertUnauthorized();
        $this->getJson($path)->assertUnauthorized();
        $this->postJson('/api/v1/customer-statuses', [])->assertUnauthorized();
        $this->patchJson($path, [])->assertUnauthorized();
        $this->deleteJson($path)->assertUnauthorized();

        foreach (['pending', 'disabled'] as $state) {
            $this->signIn(status: $state);
            $this->getJson('/api/v1/customer-statuses')->assertForbidden();
            $this->postJson('/api/v1/customer-statuses', ['name' => 'New', 'color' => '#22c55e'])->assertForbidden();
        }
    }

    public function test_active_agents_and_managers_can_read_but_cannot_write_statuses(): void
    {
        $status = CustomerStatus::factory()->create(['disabled_for' => ['conversion']]);
        $path = '/api/v1/customer-statuses/'.$status->id;
        foreach (['agent', 'manager'] as $role) {
            $this->signIn($role);
            $this->getJson('/api/v1/customer-statuses')->assertOk()->assertJsonCount(1, 'data');
            $this->getJson($path)->assertOk()->assertJsonPath('data.disabled_for', ['conversion']);
            $this->postJson('/api/v1/customer-statuses', ['name' => 'New', 'color' => '#22c55e'])->assertForbidden();
            $this->patchJson($path, ['name' => 'Changed'])->assertForbidden();
            $this->deleteJson($path)->assertForbidden();
        }
        $this->assertSame($status->name, $status->fresh()->name);
    }

    public function test_admin_can_create_statuses_with_exact_public_fields_and_normalized_hex_colors(): void
    {
        $this->signIn();
        $response = $this->postJson('/api/v1/customer-statuses', [
            'name' => 'Qualified', 'color' => '#22C55E', 'is_default' => true, 'disabled_for' => ['retention'],
        ])->assertCreated();
        $status = CustomerStatus::findOrFail($response->json('data.id'));
        $response->assertExactJson(['data' => [
            'id' => $status->id, 'name' => 'Qualified', 'color' => '#22c55e',
            'is_default' => true, 'disabled_for' => ['retention'],
            'created_at' => $status->created_at->toISOString(),
            'updated_at' => $status->updated_at->toISOString(),
        ]]);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $status->id);
        $this->assertSame(['retention'], $status->disabled_for);
        $this->assertTrue($status->is_default);
    }

    public function test_optional_fields_default_to_false_and_an_empty_workflow_list(): void
    {
        $this->signIn();
        $this->postJson('/api/v1/customer-statuses', ['name' => 'New', 'color' => '#000000'])
            ->assertCreated()->assertJsonPath('data.is_default', false)->assertJsonPath('data.disabled_for', []);
        $this->postJson('/api/v1/customer-statuses', ['name' => 'Both disabled', 'color' => '#ffffff', 'disabled_for' => ['conversion', 'retention']])
            ->assertCreated()->assertJsonPath('data.disabled_for', ['conversion', 'retention']);
    }

    public function test_partial_updates_preserve_omitted_fields_and_can_clear_disabled_workflows(): void
    {
        $this->signIn();
        $status = CustomerStatus::factory()->create([
            'name' => 'Original', 'is_default' => true, 'disabled_for' => ['retention'],
            'is_ftd_status' => true, 'order_weight' => 7,
        ]);
        $path = '/api/v1/customer-statuses/'.$status->id;
        $this->patchJson($path, ['color' => '#ABCDEF'])
            ->assertOk()->assertJsonPath('data.color', '#abcdef')->assertJsonPath('data.name', 'Original')
            ->assertJsonPath('data.is_default', true)->assertJsonPath('data.disabled_for', ['retention']);
        $this->putJson($path, ['name' => 'Renamed', 'disabled_for' => []])
            ->assertOk()->assertJsonPath('data.name', 'Renamed')->assertJsonPath('data.color', '#abcdef')
            ->assertJsonPath('data.disabled_for', [])->assertJsonPath('data.is_default', true)
            ->assertJsonPath('data.created_at', $status->created_at->toISOString());
        $this->assertTrue($status->fresh()->is_ftd_status);
        $this->assertSame(7, $status->fresh()->order_weight);
    }

    public function test_switching_the_default_never_reassigns_existing_customers(): void
    {
        $this->signIn();
        $old = CustomerStatus::factory()->create(['is_default' => true]);
        $client = Client::factory()->for($old)->create();
        $newId = $this->postJson('/api/v1/customer-statuses', [
            'name' => 'New default', 'color' => '#22c55e', 'is_default' => true,
        ])->assertCreated()->assertJsonPath('data.is_default', true)->json('data.id');
        $this->assertFalse($old->fresh()->is_default);
        $this->assertSame($old->id, $client->fresh()->customer_status_id);
        $this->assertSame(1, CustomerStatus::where('is_default', true)->count());

        $this->patchJson('/api/v1/customer-statuses/'.$old->id, ['is_default' => true])->assertOk();
        $this->assertFalse(CustomerStatus::findOrFail($newId)->is_default);
        $this->assertTrue($old->fresh()->is_default);
        $this->patchJson('/api/v1/customer-statuses/'.$old->id, ['is_default' => false])->assertOk();
        $this->assertSame(0, CustomerStatus::where('is_default', true)->count());
    }

    public function test_database_prevents_two_defaults_even_when_bypassing_the_controller(): void
    {
        CustomerStatus::factory()->create(['is_default' => true]);
        $this->expectException(UniqueConstraintViolationException::class);
        CustomerStatus::factory()->create(['is_default' => true]);
    }

    public function test_invalid_create_and_update_fields_return_errors_without_changing_the_default(): void
    {
        $this->signIn();
        $status = CustomerStatus::factory()->create(['is_default' => true]);
        $this->postJson('/api/v1/customer-statuses', [])->assertUnprocessable()->assertJsonValidationErrors(['name', 'color']);
        foreach ([
            ['name' => '   '], ['name' => str_repeat('a', 256)], ['name' => null],
            ['color' => '#fff'], ['color' => '#22c55eFF'], ['color' => '22c55e'],
            ['color' => '#gggggg'], ['color' => 'green'], ['color' => null], ['color' => ['#22c55e']],
            ['is_default' => null], ['is_default' => 'yes'],
            ['disabled_for' => null], ['disabled_for' => 'retention'],
            ['disabled_for' => ['retension']], ['disabled_for' => ['admin']],
            ['disabled_for' => ['conversion', 'conversion']],
            ['disabled_for' => ['conversion', 'retention', 'other']],
            ['disabled_for' => ['named-key' => 'retention']], ['disabled_for' => [null]],
            ['id' => 'f8a6eca6-eec1-49de-88f2-4527d1409e39'],
            ['created_at' => '2020-01-01T00:00:00Z'], ['updated_at' => '2020-01-01T00:00:00Z'],
        ] as $invalid) {
            $field = array_key_first($invalid);
            if ($field === 'disabled_for' && is_array($invalid[$field]) && array_is_list($invalid[$field]) && count($invalid[$field]) <= 2) {
                $field = 'disabled_for.0';
            }
            $this->postJson('/api/v1/customer-statuses', array_merge([
                'name' => 'Valid', 'color' => '#22c55e', 'is_default' => true,
            ], $invalid))->assertUnprocessable()->assertJsonValidationErrors($field);
            $this->patchJson('/api/v1/customer-statuses/'.$status->id, $invalid)
                ->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->assertDatabaseCount('customer_statuses', 1);
        $this->assertTrue($status->fresh()->is_default);
        $this->assertSame($status->color, $status->fresh()->color);
    }

    public function test_lists_are_paginated_and_filter_only_the_requested_workflow(): void
    {
        $this->signIn();
        $all = CustomerStatus::factory()->create(['name' => 'A available', 'disabled_for' => []]);
        $conversion = CustomerStatus::factory()->create(['name' => 'B conversion', 'disabled_for' => ['retention']]);
        $retention = CustomerStatus::factory()->create(['name' => 'C retention', 'disabled_for' => ['conversion']]);
        CustomerStatus::factory()->create(['name' => 'D disabled', 'disabled_for' => ['conversion', 'retention']]);
        $legacy = CustomerStatus::factory()->create(['name' => 'E legacy', 'disabled_for' => null]);
        $this->getJson('/api/v1/customer-statuses?per_page=2&page=2')->assertOk()
            ->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 5)->assertJsonPath('data.0.id', $retention->id);
        $this->getJson('/api/v1/customer-statuses?available_for=conversion')->assertOk()
            ->assertJsonPath('data.*.id', [$all->id, $conversion->id, $legacy->id]);
        $this->getJson('/api/v1/customer-statuses?available_for=retention')->assertOk()
            ->assertJsonPath('data.*.id', [$all->id, $retention->id, $legacy->id]);
        $this->getJson('/api/v1/customer-statuses/'.$legacy->id)->assertOk()->assertJsonPath('data.disabled_for', []);
        foreach (['per_page=101' => 'per_page', 'page=0' => 'page', 'available_for=retension' => 'available_for', 'available_for[]=conversion' => 'available_for'] as $query => $field) {
            $this->getJson('/api/v1/customer-statuses?'.$query)->assertUnprocessable()->assertJsonValidationErrors($field);
        }
    }

    public function test_referenced_statuses_cannot_be_deleted_but_unused_ones_can(): void
    {
        $this->signIn();
        $status = CustomerStatus::factory()->create(['is_default' => true]);
        $client = Client::factory()->for($status)->create();
        $this->deleteJson('/api/v1/customer-statuses/'.$status->id)->assertConflict();
        $this->assertModelExists($status);
        $this->assertSame($status->id, $client->fresh()->customer_status_id);
        $unused = CustomerStatus::factory()->create();
        $this->deleteJson('/api/v1/customer-statuses/'.$unused->id)->assertNoContent();
        $this->assertModelMissing($unused);
    }

    public function test_missing_status_ids_return_not_found(): void
    {
        $this->signIn();
        foreach (['not-a-uuid', 'f8a6eca6-eec1-49de-88f2-4527d1409e39'] as $id) {
            $path = '/api/v1/customer-statuses/'.$id;
            $this->getJson($path)->assertNotFound();
            $this->patchJson($path, ['name' => 'Changed'])->assertNotFound();
            $this->deleteJson($path)->assertNotFound();
        }
    }

    public function test_upgrade_preserves_legacy_statuses_dates_and_customers_and_keeps_the_oldest_default(): void
    {
        $this->signIn();
        $older = CustomerStatus::factory()->create(['created_at' => '2026-01-01 00:00:00', 'is_default' => true]);
        $newer = CustomerStatus::factory()->create(['created_at' => '2026-02-01 00:00:00']);
        $client = Client::factory()->for($newer)->create();
        $migration = require database_path('migrations/2026_11_23_093723_add_management_fields_to_customer_statuses_table.php');
        $migration->down();
        DB::table('customer_statuses')->where('id', $newer->id)->update(['is_default' => true]);
        $migration->up();
        $this->assertTrue($older->fresh()->is_default);
        $this->assertFalse($newer->fresh()->is_default);
        $this->assertSame($newer->id, $client->fresh()->customer_status_id);
        $this->assertSame($newer->name, $newer->fresh()->name);
        $this->assertSame($newer->color, $newer->fresh()->color);
        $this->assertTrue($newer->created_at->equalTo($newer->fresh()->created_at));
        $this->assertTrue($newer->updated_at->equalTo($newer->fresh()->updated_at));
        $this->getJson('/api/v1/customer-statuses/'.$newer->id)->assertOk()->assertJsonPath('data.disabled_for', []);
    }

    public function test_swagger_documents_status_crud_hex_colors_and_workflow_choices(): void
    {
        Gate::define('viewApiDocs', fn (?User $user = null): bool => true);
        Scramble::throwOnError();
        $spec = $this->getJson('/api/openapi.json')->assertOk()->json();
        foreach (['get', 'post'] as $method) {
            $this->assertArrayHasKey($method, $spec['paths']['/customer-statuses']);
        }
        foreach (['get', 'put', 'patch', 'delete'] as $method) {
            $this->assertArrayHasKey($method, $spec['paths']['/customer-statuses/{customerStatus}']);
        }
        $body = $spec['paths']['/customer-statuses']['post']['requestBody']['content']['application/json']['schema'];
        if (isset($body['$ref'])) {
            $body = $spec['components']['schemas'][basename($body['$ref'])];
        }
        $this->assertEqualsCanonicalizing(['name', 'color'], $body['required']);
        $this->assertEqualsCanonicalizing(['name', 'color', 'is_default', 'disabled_for'], array_keys($body['properties']));
        $this->assertSame('boolean', $body['properties']['is_default']['type']);
        $this->assertContains('#22c55e', $body['properties']['color']['examples']);
        $this->assertStringContainsString('[0-9a-fA-F]{6}', $body['properties']['color']['pattern']);
        $this->assertEqualsCanonicalizing(['conversion', 'retention'], $body['properties']['disabled_for']['items']['enum']);

        $resource = $spec['components']['schemas']['CustomerStatusResource'];
        $this->assertEqualsCanonicalizing(['id', 'name', 'color', 'is_default', 'disabled_for', 'created_at', 'updated_at'], array_keys($resource['properties']));
    }
}
