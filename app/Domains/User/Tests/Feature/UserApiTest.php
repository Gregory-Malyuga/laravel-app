<?php

namespace Domains\User\Tests\Feature;

use Domains\User\Domain\Models\User;
use Illuminate\Database\Eloquent\Model;
use Shared\Testing\BaseApiTest;

class UserApiTest extends BaseApiTest
{
    protected function basePath(): string
    {
        return '/api/v1/users';
    }

    /** @return array<string, mixed> */
    protected function makeStorePayload(): array
    {
        return [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'secret1234',
            'role' => 'user',
        ];
    }

    /** @return array<string, mixed> */
    protected function makeUpdatePayload(): array
    {
        return [
            'name' => 'Updated User',
            'email' => 'updated@example.com',
            'password' => 'newsecret1234',
            'role' => 'manager',
        ];
    }

    protected function existingRecord(): Model
    {
        return User::factory()->create();
    }

    public function test_index_paginates(): void
    {
        User::factory()->count(5)->create();

        $response = $this->getJson('/api/v1/users?per_page=2&page=1');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
        $this->assertEquals(1, $response->json('meta.current_page'));
    }

    public function test_index_sorts_asc(): void
    {
        User::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/users?sort=id&direction=asc');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertNotEmpty($data);
        $this->assertLessThanOrEqual($data[1]['id'] ?? PHP_INT_MAX, $data[0]['id']);
    }

    public function test_index_sorts_desc(): void
    {
        User::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/users?sort=id&direction=desc');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertNotEmpty($data);
        $this->assertGreaterThanOrEqual($data[1]['id'] ?? 0, $data[0]['id']);
    }

    public function test_index_filters_by_name(): void
    {
        User::factory()->create(['name' => 'Alice Admin', 'email' => 'alice2@example.com']);
        User::factory()->create(['name' => 'Bob Admin', 'email' => 'bob2@example.com']);

        $response = $this->getJson('/api/v1/users?name='.urlencode('Alice Admin'));

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }
}
