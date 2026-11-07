<?php

namespace App\Http\Requests\Projects;

use App\Data\Projects\UpdateProjectData;
use App\Queries\Projects\FindAccessibleProjectQuery;
use App\Support\Projects\ProjectContent;
use Illuminate\Validation\Validator;

final class UpdateProjectRequest extends ProjectCommandRequest
{
    public function authorize(): bool
    {
        $project = app(FindAccessibleProjectQuery::class)->get($this->member(), $this->projectId());

        return $this->member()->can('update', $project);
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return ['lock_version' => ['required', 'integer:strict', 'min:1', 'max:2147483646'], ...ProjectContent::rules()];
    }

    public function withValidator(Validator $validator): void
    {
        parent::withValidator($validator);
        $validator->after(function (Validator $validator): void {
            if (array_intersect(array_keys($this->all()), [...ProjectContent::FIELDS, 'technology_ids']) === []) {
                $validator->errors()->add('changes', 'Fournissez au moins un champ à modifier.');
            }
        });
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [...ProjectContent::messages(), 'lock_version.*' => 'Indiquez une version entière JSON entre 1 et 2147483646.'];
    }

    public function projectData(): UpdateProjectData
    {
        $data = $this->validated();

        return new UpdateProjectData($data['lock_version'], array_intersect_key($data, array_flip(ProjectContent::FIELDS)), $data['technology_ids'] ?? null);
    }
}
