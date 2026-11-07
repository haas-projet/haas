<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ResolutionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Une résolution référence une proposition appartenant à la même demande.
// FK composite posée en base (voir migration create_resolutions_table) :
// (request_id, proposal_id) → proposals (request_id, id).
// Contrainte d'appartenance proposition/demande demandée par
// docs/execution/tasks.json:147 et docs/execution/PLAN_COMMITS.md:133.
/**
 * @property string $id
 * @property string $request_id
 * @property string $proposal_id
 * @property string $accepted_by
 * @property string $validation_note
 * @property string|null $environment
 * @property int|null $proposal_version
 * @property CarbonImmutable $accepted_at
 * @property CarbonImmutable|null $revoked_at
 */
#[Fillable(['validation_note', 'environment'])]
class Resolution extends Model
{
    /** @use HasFactory<ResolutionFactory> */
    use HasFactory, HasUuids;

    /** @return BelongsTo<HelpRequest, $this> */
    public function request(): BelongsTo
    {
        return $this->belongsTo(HelpRequest::class, 'request_id');
    }

    /** @return BelongsTo<Proposal, $this> */
    public function proposal(): BelongsTo
    {
        return $this->belongsTo(Proposal::class, 'proposal_id');
    }

    /** @return BelongsTo<User, $this> */
    public function acceptor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'proposal_version' => 'integer',
            'accepted_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }
}
