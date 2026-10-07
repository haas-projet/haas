<?php

namespace App\Http\Requests\Demo;

use App\Data\Demo\DemoOrderData;
use App\Data\Idempotency\IdempotencyKey;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Entrée HTTP de la brique B2 (lot B38) : commande fictive à enregistrer.
 *
 * - L'en-tête `Idempotency-Key` est obligatoire et doit être un UUID v4 ;
 *   son absence ou son invalidité produit un 422 `VALIDATION_FAILED` sur
 *   le champ virtuel `idempotency_key`, aucune écriture n'est tentée.
 * - Le corps n'accepte que les champs listés dans `rules()` : toute clé
 *   supplémentaire est rejetée pour éviter un mass-assignment implicite.
 * - Aucun identifiant client, aucun identifiant HAAS, aucune URL n'est
 *   accepté : la démonstration manipule exclusivement des valeurs
 *   bornées et fictives.
 */
final class RecordDemoOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Aucune Policy du socle HAAS n'est consultée : B2 est accessible
        // sans session, cookie, ni compte. Les règles d'intégrité restent
        // portées par la validation, la contrainte SQL et le service.
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'order_ref' => ['required', 'string', 'regex:/^demo-[0-9]{4}$/D'],
            'amount_minor' => ['required', 'integer:strict', 'min:1', 'max:1000000'],
            'currency' => ['required', 'string', 'in:EUR'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            // Clé d'idempotence : lue à partir de l'en-tête HTTP, validée comme un
            // champ virtuel pour que l'erreur apparaisse à l'emplacement standard
            // du contrat `{error.fields.idempotency_key:[...]}`.
            $raw = $this->header('Idempotency-Key');
            if ($raw === null || $raw === '') {
                $validator->errors()->add('idempotency_key', 'La clé d’idempotence est requise.');
            } elseif (! IdempotencyKey::valid($raw)) {
                $validator->errors()->add('idempotency_key', 'La clé d’idempotence doit être un UUID v4.');
            }

            // Interdire toute clé de corps inconnue, comme PaginatedRequest.
            foreach (array_diff(array_keys($this->all()), array_keys($this->rules())) as $field) {
                $validator->errors()->add($field, 'Ce champ ne peut pas être utilisé.');
            }
        });
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'order_ref.required' => 'La référence de commande est requise.',
            'order_ref.max' => 'La référence de commande ne doit pas dépasser 64 caractères.',
            'order_ref.regex' => 'Utilisez une référence fictive demo- suivie de quatre chiffres.',
            'amount_minor.required' => 'Le montant est requis.',
            'amount_minor.integer' => 'Le montant doit être un entier.',
            'amount_minor.min' => 'Le montant doit être strictement positif.',
            'amount_minor.max' => 'Le montant fictif ne doit pas dépasser 1 000 000 unités.',
            'currency.required' => 'La devise est requise.',
            'currency.in' => 'La démonstration utilise uniquement EUR fictifs.',
            'currency.regex' => 'La devise doit être un code ISO 4217 à trois lettres majuscules.',
        ];
    }

    public function toData(): DemoOrderData
    {
        /** @var array{order_ref:string,amount_minor:int,currency:string} $validated */
        $validated = $this->validated();

        return new DemoOrderData(
            orderRef: $validated['order_ref'],
            amountMinor: (int) $validated['amount_minor'],
            currency: $validated['currency'],
        );
    }

    public function idempotencyKey(): string
    {
        return (string) $this->header('Idempotency-Key');
    }
}
