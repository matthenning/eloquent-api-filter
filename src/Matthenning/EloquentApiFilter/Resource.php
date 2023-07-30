<?php

namespace Matthenning\EloquentApiFilter;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class Resource extends JsonResource
{

    public function toArray(Request $request): array
    {
        return $this->toArray();
    }

}