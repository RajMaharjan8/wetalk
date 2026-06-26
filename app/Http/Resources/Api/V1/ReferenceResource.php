<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Reference;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A bibliography reference. Exposes the raw structured data plus a `citation`
 * string pre-formatted in the report's chosen style (set by the controller).
 *
 * @mixin Reference
 */
class ReferenceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'type' => $this->type,
            'data' => $this->data,
            'citation' => $this->citation ?? null,
        ];
    }
}
