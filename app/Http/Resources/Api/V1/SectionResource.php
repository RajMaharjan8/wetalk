<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Section;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A single report section in the API. Placement tells consumers whether the
 * section is front matter, a numbered body chapter, or unnumbered back matter.
 *
 * @mixin Section
 */
class SectionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'placement' => $this->placement,
            'order' => $this->order,
            'title' => $this->title,
            'content' => $this->content,
        ];
    }
}
