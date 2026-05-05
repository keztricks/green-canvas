<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WardBoundariesTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_404_when_boundary_file_missing(): void
    {
        config(['canvassing.ward_boundary_file' => 'ward-boundaries/does-not-exist.geojson']);
        $user = User::factory()->canvasser()->create();

        $this->actingAs($user)->get(route('canvassing.boundaries'))->assertNotFound();
    }

    public function test_serves_geojson_from_configured_path(): void
    {
        $relativePath = 'ward-boundaries/test-' . uniqid() . '.geojson';
        $absolutePath = storage_path('app/' . $relativePath);
        if (!is_dir(dirname($absolutePath))) {
            mkdir(dirname($absolutePath), 0777, true);
        }
        $body = json_encode(['type' => 'FeatureCollection', 'features' => []]);
        file_put_contents($absolutePath, $body);
        config(['canvassing.ward_boundary_file' => $relativePath]);

        try {
            $user = User::factory()->canvasser()->create();
            $response = $this->actingAs($user)->get(route('canvassing.boundaries'));

            $response->assertOk();
            $this->assertSame('application/geo+json', $response->headers->get('Content-Type'));
            // BinaryFileResponse exposes the file via getFile(), not getContent().
            $this->assertSame($body, file_get_contents($response->getFile()->getPathname()));
        } finally {
            @unlink($absolutePath);
        }
    }
}
