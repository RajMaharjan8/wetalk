<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The lightweight shape returned by the sample-reports index — enough for a
 * third-party site to list and link to a sample without the full body.
 *
 * @mixin Report
 */
class SampleReportListResource extends JsonResource
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
            'url' => route('samples.show', $this->slug),
            'api_url' => route('api.v1.samples.show', $this->slug),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
