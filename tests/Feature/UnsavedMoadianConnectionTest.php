<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use GuzzleHttp\Psr7\Response as HttpResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Jooyeshgar\Moadian\Facades\Moadian;
use Jooyeshgar\Moadian\Http\Response;
use Mockery;
use Mockery\MockInterface;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class UnsavedMoadianConnectionTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::factory()->create([
            'fiscal_year' => 1405,
            'moadian_username' => 'saved-user',
            'tax_id' => '11111111111',
            'private_key_path' => 'saved-key.pem',
            'certificate_path' => 'saved-certificate.crt',
        ]);
        $this->user = User::factory()->create();
        $this->user->companies()->attach($this->company);
        $this->actingAs($this->user);
        config(['active-company-id' => $this->company->id]);
        foreach (['companies.edit', 'companies.test-moadian-connection'] as $permission) {
            $this->user->givePermissionTo(Permission::firstOrCreate(['name' => $permission]));
        }
    }

    private function payload(): array
    {
        return [
            'moadian_username' => 'entered-user',
            'tax_id' => '12345678901',
            'private_key' => UploadedFile::fake()->createWithContent('entered.pem', 'entered-private-key'),
            'certificate' => UploadedFile::fake()->createWithContent('entered.crt', 'entered-certificate'),
        ];
    }

    private function client(): MockInterface
    {
        $client = Mockery::mock(\Jooyeshgar\Moadian\Moadian::class);
        Moadian::shouldReceive('for')->once()
            ->with('entered-private-key', 'entered-certificate', 'entered-user')->andReturn($client);

        return $client;
    }

    private function response(int $status = 200): Response
    {
        $body = $status === 200 ? ['result' => []] : ['errors' => [['message' => 'failed', 'code' => '1']]];

        return (new Response)->setResponse(new HttpResponse($status, [], json_encode($body)));
    }

    public function test_uses_only_entered_credentials_and_does_not_save_anything(): void
    {
        $before = $this->company->fresh()->getRawOriginal();
        $activityCount = Activity::count();
        Storage::shouldReceive('get')->never();
        Storage::shouldReceive('put')->never();
        Storage::shouldReceive('delete')->never();
        $client = $this->client();
        $client->shouldReceive('getServerInfo')->once()->andReturn($this->response());
        $client->shouldReceive('getEconomicCodeInformation')->with('12345678901')->once()->andReturn($this->response());

        $this->postJson(route('companies.test-moadian-connection', $this->company), $this->payload())
            ->assertOk()->assertJson(['connected' => true, 'message' => __('Connection to Moadian succeeded.')]);

        $this->assertSame($before, $this->company->fresh()->getRawOriginal());
        $this->assertDatabaseCount('moadian_histories', 0);
        $this->assertSame($activityCount, Activity::count());
        $this->assertFalse(session()->has('_old_input'));
    }

    public function test_can_test_a_company_without_saved_credentials(): void
    {
        $this->company->update(['moadian_username' => null, 'tax_id' => null, 'private_key_path' => null, 'certificate_path' => null]);
        $client = $this->client();
        $client->shouldReceive('getServerInfo')->once()->andReturn($this->response());
        $client->shouldReceive('getEconomicCodeInformation')->with('12345678901')->once()->andReturn($this->response());
        $this->postJson(route('companies.test-moadian-connection', $this->company), $this->payload())
            ->assertOk()->assertJsonPath('connected', true);
        $this->assertNull($this->company->fresh()->moadian_username);
    }

    public function test_missing_inputs_never_fall_back_to_saved_credentials(): void
    {
        Moadian::shouldReceive('for')->never();
        $this->postJson(route('companies.test-moadian-connection', $this->company), [])
            ->assertUnprocessable()->assertJsonValidationErrors(['moadian_username', 'tax_id', 'private_key', 'certificate']);
    }

    public function test_invalid_tax_id_and_file_extensions_are_rejected(): void
    {
        Moadian::shouldReceive('for')->never();
        $payload = $this->payload();
        $payload['tax_id'] = 'invalid';
        $payload['certificate'] = UploadedFile::fake()->createWithContent('certificate.txt', 'invalid');
        $payload['private_key'] = UploadedFile::fake()->createWithContent('key.txt', 'invalid');
        $this->postJson(route('companies.test-moadian-connection', $this->company), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors(['tax_id', 'certificate', 'private_key']);
    }

    public function test_connection_failure_is_reported_without_exception_details(): void
    {
        $this->client()->shouldReceive('getServerInfo')->once()->andThrow(new \RuntimeException('sensitive details'));
        $this->postJson(route('companies.test-moadian-connection', $this->company), $this->payload())
            ->assertOk()->assertExactJson([
                'connected' => false,
                'message' => __('Connection to Moadian failed. Please check the entered settings and try again.'),
            ]);
    }

    public function test_unsuccessful_server_response_is_reported_as_failure(): void
    {
        $client = $this->client();
        $client->shouldReceive('getServerInfo')->once()->andReturn($this->response(400));
        $client->shouldReceive('getEconomicCodeInformation')->never();
        $this->postJson(route('companies.test-moadian-connection', $this->company), $this->payload())
            ->assertOk()->assertJsonPath('connected', false);
    }

    public function test_unsuccessful_taxpayer_response_is_reported_as_failure(): void
    {
        $client = $this->client();
        $client->shouldReceive('getServerInfo')->once()->andReturn($this->response());
        $client->shouldReceive('getEconomicCodeInformation')->with('12345678901')->once()->andReturn($this->response(400));
        $this->postJson(route('companies.test-moadian-connection', $this->company), $this->payload())
            ->assertOk()->assertJsonPath('connected', false);
    }

    public function test_company_membership_and_permission_are_required(): void
    {
        Moadian::shouldReceive('for')->never();
        $other = Company::factory()->create();
        $this->postJson(route('companies.test-moadian-connection', $other), $this->payload())->assertForbidden();
        $this->user->revokePermissionTo('companies.test-moadian-connection');
        $this->postJson(route('companies.test-moadian-connection', $this->company), $this->payload())->assertForbidden();
    }

    public function test_edit_page_offers_inline_testing_without_submitting_company_form(): void
    {
        $this->get(route('companies.edit', $this->company))->assertOk()
            ->assertSee('@click="testConnection()"', false)
            ->assertSee('type="button"', false)
            ->assertSee(__('The connection test uses the entered settings and selected key files without saving. Select both key files for each test.'))
            ->assertDontSee('form="test-moadian-connection"', false);
        $this->get(route('companies.test-moadian-connection', $this->company))->assertStatus(405);
    }
}
