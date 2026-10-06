<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemoPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_health_route_skips_basic_auth(): void
    {
        config(['demo.basic_auth' => ['user' => 'demo', 'password' => 'secret']]);

        $this->get('/up')->assertOk();
    }

    public function test_pages_are_behind_basic_auth_once_credentials_are_set(): void
    {
        config(['demo.basic_auth' => ['user' => 'demo', 'password' => 'secret']]);

        $this->get('/files')->assertUnauthorized();

        $this->withHeader('Authorization', 'Basic '.base64_encode('demo:secret'))
            ->get('/files')
            ->assertOk();
    }

    public function test_the_files_page_lists_objects_and_streams_them_through_the_app(): void
    {
        Storage::fake('s3');
        Storage::disk('s3')->put('demo/lessons/README.txt', 'hello');

        $this->get('/files')
            ->assertOk()
            ->assertSee('README.txt')
            ->assertSee('Where these files live');

        $this->get('/files/demo/lessons/README.txt')->assertOk();
    }

    public function test_files_outside_the_demo_prefix_are_not_served(): void
    {
        Storage::fake('s3');
        Storage::disk('s3')->put('private/secret.txt', 'nope');

        $this->get('/files/private/secret.txt')->assertNotFound();
    }

    public function test_the_status_endpoint_reports_an_empty_demo(): void
    {
        $this->getJson('/import/status')
            ->assertOk()
            ->assertJson([
                'import' => null,
                'workers' => [],
            ]);
    }
}
