<?php

namespace App\Http\Resources\Administration;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FunctionalPositionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'boss' => $this->boss,
            'boss_hierarchy' => $this->boss_hierarchy,
            'employees' => EmployeeResource::collection($this->whenLoaded('employees'))
            // 'employees' => $this->whenLoaded('employees')
        ];
    }
}
