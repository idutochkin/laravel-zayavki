<?php

namespace App\Http\Resources;

use App\Models\StopFactorResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StopFactorResult
 */
class StopFactorResultResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->code,
            'passed' => $this->passed,
            'message' => $this->message,
        ];
    }
}
