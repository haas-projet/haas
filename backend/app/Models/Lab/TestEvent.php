<?php

namespace App\Models\Lab;

use Database\Factories\Lab\TestEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['run_id', 'event_id', 'order_ref', 'amount_minor', 'currency', 'received_at'])]
class TestEvent extends Model
{
    /** @use HasFactory<TestEventFactory> */
    use HasFactory, HasUuids;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'received_at' => 'immutable_datetime',
            'processed_at' => 'immutable_datetime',
        ];
    }

    public function getConnectionName(): ?string
    {
        return LabConnection::NAME;
    }

    /** @return HasOne<TestOrder, $this> */
    public function order(): HasOne
    {
        return $this->hasOne(TestOrder::class, 'source_event_id');
    }
}
