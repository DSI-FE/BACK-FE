<?php

namespace App\Http\Resources\Attendance;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PermissionRequestResource extends JsonResource
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
            'justification' => $this->justification,
            'date_ini' => $this->date_ini,
            'date_end' => $this->date_end,
            'time_ini' => $this->time_ini,
            'time_end' => $this->time_end
        ];
    }
}
