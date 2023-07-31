<?php

namespace Matthenning\EloquentApiFilter\Traits;

use Illuminate\Http\JsonResponse;

trait UsesDefaultShowMethod
{

    /**
     * @param mixed $id
     * @return JsonResponse
     */
    public function show(mixed $id): JsonResponse
    {
        return $this->_show($id);
    }

}