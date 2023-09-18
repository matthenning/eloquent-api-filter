<?php

namespace Matthenning\EloquentApiFilter\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait UsesDefaultStoreMethod
{

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        return $this->_store($request);
    }


}