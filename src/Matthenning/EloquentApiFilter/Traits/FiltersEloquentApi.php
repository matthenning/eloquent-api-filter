<?php

namespace Matthenning\EloquentApiFilter\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Matthenning\EloquentApiFilter\EloquentApiFilter;

/**
 * Class FiltersEloquentApi
 * @package Matthenning\EloquentApiFilter
 */
trait FiltersEloquentApi {

    /**
     * @param Request $request
     * @param Builder $query
     * @return Builder
     */
    protected function filterApiRequest(Request $request, Builder $query)
    {
        $eaf = new EloquentApiFilter($request, $query);
        return $eaf->filter();
    }

}