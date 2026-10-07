<?php

namespace Database\Factories\Lab;

use App\Models\Lab\TestEvent;
use App\Models\Lab\TestOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Commande fictive dérivée d'un événement B1. Par défaut, `source_event_id`
 * déclenche la création paresseuse d'un `TestEvent` via `TestEvent::factory()` ;
 * les champs `run_id`, `order_ref`, `amount_minor` et `currency` sont alors
 * lus sur cet événement par des closures d'état. Cette construction évite
 * d'instancier l'événement dans `definition()` : si l'appelant passe lui-même
 * un `source_event_id` par `->state([...])` ou `->create([...])`, aucun
 * `TestEvent` superflu n'est créé.
 *
 * @extends Factory<TestOrder>
 */
class TestOrderFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'source_event_id' => TestEvent::factory(),
            'run_id' => fn (array $attributes): string => $this->source($attributes)->run_id,
            'order_ref' => fn (array $attributes): string => $this->source($attributes)->order_ref,
            'amount_minor' => fn (array $attributes): int => $this->source($attributes)->amount_minor,
            'currency' => fn (array $attributes): string => $this->source($attributes)->currency,
        ];
    }

    /** @param array<string, mixed> $attributes */
    private function source(array $attributes): TestEvent
    {
        return TestEvent::query()->where('id', $attributes['source_event_id'])->firstOrFail();
    }

    public function fromEvent(TestEvent $event): self
    {
        return $this->state(fn (): array => [
            'source_event_id' => $event->getKey(),
            'run_id' => $event->run_id,
            'order_ref' => $event->order_ref,
            'amount_minor' => $event->amount_minor,
            'currency' => $event->currency,
        ]);
    }
}
