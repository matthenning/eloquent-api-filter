<?php

namespace Matthenning\EloquentApiFilter\Traits;

use Illuminate\Http\JsonResponse;

trait UsesDefaultIndexMethod
{

    /**
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        return $this->_index();
    }

}