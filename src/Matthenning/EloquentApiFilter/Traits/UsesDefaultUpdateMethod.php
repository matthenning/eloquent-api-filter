<?php

namespace Matthenning\EloquentApiFilter\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait UsesDefaultUpdateMethod
{

    /**
     * @param Request $request
     * @param mixed $id
     * @return JsonResponse
     */
    public function update(Request $request, mixed $id): JsonResponse
    {
        return $this->_update($request, $id);
    }


}