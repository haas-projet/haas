<?php

declare(strict_types=1);

namespace Tests\Support\Capsules;

use App\Enums\Capsules\ArtifactDistributionStatus;
use App\Enums\Capsules\CapsuleVersionState;
use App\Enums\Capsules\ContributionRole;
use App\Enums\Identity\Role;
use App\Models\Capsules\Artifact;
use App\Models\Capsules\Capsule;
use App\Models\Capsules\CapsuleVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class ArtifactKitFixture
{
    /** @param array<string, mixed> $overrides
     * @return array{User, Capsule, CapsuleVersion, Artifact}
     */
    public static function create(string $slug, array $overrides = []): array
    {
        $owner = User::factory()->verified()->create();
        $moderator = User::factory()->verified()->create(['role' => Role::Moderator]);
        $capsule = Capsule::factory()->create(['owner_id' => $owner->id, 'slug' => $slug]);
        $version = CapsuleVersion::factory()->create([
            'capsule_id' => $capsule->id,
            'version_label' => '1.0.0',
            'body' => "## Procédure\n\nÉtape clairement décrite pour le téléchargement.",
            'limits' => 'Portée réduite au scénario cité.',
            'state' => CapsuleVersionState::Draft,
            'lock_version' => 1,
        ]);
        DB::table('capsule_contributors')->insert([
            'id' => (string) Str::uuid(),
            'version_id' => $version->id,
            'user_id' => $owner->id,
            'contribution_role' => ContributionRole::Diagnosis->value,
            'created_at' => now()->utc(),
            'updated_at' => now()->utc(),
        ]);
        $privatePath = 'kits/'.$slug.'.zip';
        Storage::disk('local')->put('artifacts/'.$privatePath, "ZIP factice pour {$slug}");
        $sha = hash('sha256', "ZIP factice pour {$slug}");
        $noticesPath = 'notices/'.$slug.'.txt';
        $notices = 'Notice de fixture uniquement : aucun droit reel accorde.';
        Storage::disk('local')->put('artifacts/'.$noticesPath, $notices);
        $artifact = Artifact::factory()->create(array_replace([
            'version_id' => $version->id,
            'private_path' => $privatePath,
            'sha256' => $sha,
            'size' => strlen("ZIP factice pour {$slug}"),
            'distribution_status' => ArtifactDistributionStatus::Approved,
            'notices_path' => $noticesPath,
            'notices_sha256' => hash('sha256', $notices),
            'notices_size' => strlen($notices),
        ], $overrides));
        CapsuleVersion::whereKey($version->id)->update([
            'state' => CapsuleVersionState::Published->value,
            'reviewer_id' => $moderator->id,
            'published_at' => now()->utc(),
            'content_digest' => hash('sha256', $slug.' / 1.0.0'),
            'lock_version' => 2,
        ]);
        $version->refresh();

        return [$owner, $capsule, $version, $artifact];
    }
}
