<?php

namespace Matthenning\EloquentApiFilter\Traits;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Matthenning\EloquentApiFilter\EloquentApiFilter;

/**
 * Class FiltersEloquentApi
 * @package Matthenning\EloquentApiFilter
 */
trait FiltersEloquentApi {

    /**
     * @param Request $request
     * @param EloquentBuilder|QueryBuilder $query
     * @return Builder
     */
    protected function filterApiRequest(Request $request, EloquentBuilder|QueryBuilder $query)
    {
        $eaf = new EloquentApiFilter($request, $query);
        return $eaf->filter();
    }

}