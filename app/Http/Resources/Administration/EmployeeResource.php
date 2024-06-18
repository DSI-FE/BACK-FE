<?php

namespace App\Http\Resources\Administration;

use App\Http\Resources\Attendance\PermissionRequestResource;


use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
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
            'lastname' => $this->lastname,
            'email' => $this->email,
            'permission_requests' => PermissionRequestResource::collection($this->whenLoaded('permissionRequests')),
            // 'pivot' => $this->whenLoaded('pivot')
        ];
    }
}
