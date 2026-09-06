<?php

namespace Tests\Feature;

use Tests\TestCase;

class SpaRoutingTest extends TestCase
{
    public function test_application_routes_serve_the_vue_shell(): void
    {
        foreach (['/', '/app', '/app/missing-page'] as $path) {
            $this->get($path)
                ->assertOk()
                ->assertViewIs('app')
                ->assertSee('<div id="app"></div>', false);
        }
    }

    public function test_unknown_api_routes_do_not_serve_the_vue_shell(): void
    {
        $this->getJson('/api/missing-endpoint')
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/json');
    }
}
