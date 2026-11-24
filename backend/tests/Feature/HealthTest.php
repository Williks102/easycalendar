<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthTest extends TestCase
{
    public function test_health_endpoint_responds(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_unknown_api_route_answers_json(): void
    {
        $this->getJson('/api/v1/unknown')->assertNotFound()->assertJsonStructure(['message']);
    }
}
