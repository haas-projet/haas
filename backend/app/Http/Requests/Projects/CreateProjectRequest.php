<?php

namespace App\Http\Requests\Projects;

use App\Data\Projects\CreateProjectData;
use App\Models\Project;
use App\Support\Projects\ProjectContent;

final class CreateProjectRequest extends ProjectCommandRequest
{
    public function authorize(): bool
    {
        return $this->member()->can('create', Project::class);
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return ProjectContent::rules();
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ProjectContent::messages();
    }

    public function projectData(): CreateProjectData
    {
        $data = $this->validated();

        return new CreateProjectData(array_intersect_key($data, array_flip(ProjectContent::FIELDS)), $data['technology_ids'] ?? []);
    }
}
