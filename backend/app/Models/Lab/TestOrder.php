<?php

namespace App\Models\Lab;

use Database\Factories\Lab\TestOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['run_id', 'source_event_id', 'order_ref', 'amount_minor', 'currency'])]
class TestOrder extends Model
{
    /** @use HasFactory<TestOrderFactory> */
    use HasFactory, HasUuids;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
        ];
    }

    public function getConnectionName(): ?string
    {
        return LabConnection::NAME;
    }

    /** @return BelongsTo<TestEvent, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(TestEvent::class, 'source_event_id');
    }
}
