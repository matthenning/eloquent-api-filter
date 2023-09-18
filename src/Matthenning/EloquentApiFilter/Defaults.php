<?php

namespace Matthenning\EloquentApiFilter;

readonly class Defaults
{

    public function __construct(
        public int $pagination_per_page = 10
    ) {}

}