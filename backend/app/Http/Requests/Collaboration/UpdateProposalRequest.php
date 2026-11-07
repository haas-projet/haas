<?php

namespace App\Http\Requests\Collaboration;

use App\Data\Collaboration\ProposalData;
use App\Queries\Collaboration\VisibleProposalsQuery;
use App\Support\Collaboration\ProposalContent;
use Illuminate\Validation\Validator;

final class UpdateProposalRequest extends StoreProposalRequest
{
    public function authorize(): bool
    {
        return $this->member()->can('update', app(VisibleProposalsQuery::class)->get($this->member(), $this->targetId()));
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [...ProposalContent::rules(true), 'lock_version' => ['required', 'integer:strict', 'min:1', 'max:2147483646']];
    }

    public function withValidator(Validator $validator): void
    {
        parent::withValidator($validator);
        $validator->after(function (Validator $validator): void {
            if (array_intersect(array_keys($this->all()), ProposalContent::FIELDS) === []) {
                $validator->errors()->add('changes', 'Fournissez au moins un champ de contenu à modifier.');
            }
        });
    }

    public function proposalData(): ProposalData
    {
        $data = $this->validated();

        return new ProposalData(array_intersect_key($data, array_flip(ProposalContent::FIELDS)), $data['lock_version']);
    }
}
