<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Capsules\Artifact;
use App\Models\Capsules\Capsule;
use App\Models\Capsules\CapsuleContributor;
use App\Models\Capsules\CapsuleVersion;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class CapsuleModelProtectionTest extends TestCase
{
    /**
     * @param  class-string<Model>  $modelClass
     * @param  list<string>  $attributes
     */
    #[DataProvider('protectedModels')]
    public function test_server_attributes_cannot_be_mass_assigned(string $modelClass, array $attributes): void
    {
        $model = new $modelClass;
        foreach ($attributes as $attribute) {
            $this->assertFalse($model->isFillable($attribute), $modelClass.'.'.$attribute);
        }
    }

    /** @return iterable<string, array{class-string<Model>, list<string>}> */
    public static function protectedModels(): iterable
    {
        yield 'capsule' => [Capsule::class, ['id', 'owner_id', 'source_request_id', 'visibility', 'created_at', 'updated_at']];
        yield 'version' => [CapsuleVersion::class, ['id', 'capsule_id', 'state', 'reviewer_id', 'published_at', 'lock_version', 'created_at', 'updated_at']];
        yield 'contributor' => [CapsuleContributor::class, ['id', 'version_id', 'user_id', 'contribution_role', 'created_at', 'updated_at']];
        yield 'artifact' => [Artifact::class, ['id', 'version_id', 'private_path', 'sha256', 'size', 'distribution_status', 'notices_path', 'created_at', 'updated_at']];
    }
}
