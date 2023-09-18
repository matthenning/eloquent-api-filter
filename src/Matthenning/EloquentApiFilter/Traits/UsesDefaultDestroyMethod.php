<?php

namespace Matthenning\EloquentApiFilter\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait UsesDefaultDestroyMethod
{

    /**
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        return $this->_destroy($request, $id);
    }


}