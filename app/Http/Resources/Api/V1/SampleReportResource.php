<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The full sample-report payload — metadata plus its visible sections and
 * formatted references. Used by the show endpoint.
 *
 * @mixin Report
 */
class SampleReportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'title' => $this->title,
            'abstract' => $this->abstract,
            'format' => $this->reference_format,
            'student_name' => $this->student_name,
            'academic_year' => $this->academic_year,
            'semester' => $this->semester,
            'module_code' => $this->module_code,
            'module_title' => $this->module_title,
            'url' => route('samples.show', $this->slug),
            'sections' => SectionResource::collection($this->whenLoaded('sections')),
            'references' => ReferenceResource::collection($this->whenLoaded('references')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
