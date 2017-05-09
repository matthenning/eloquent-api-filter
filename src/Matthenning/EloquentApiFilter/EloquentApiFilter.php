<?php

namespace Matthenning\EloquentApiFilter;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Class EloquentApiFilter
 * @package Matthenning\EloquentApiFilter
 */
class EloquentApiFilter {


    /**
     * @var Request
     */
    private $request;

    /**
     * @var Builder
     */
    private $query;

    /**
     * @param Request $request
     * @param Builder $query
     */
    public function __construct(Request $request, Builder $query)
    {
        $this->request = $request;
        $this->query = $query;
    }

    /**
     * @return Builder
     */
    public function filter()
    {
        if ($this->request->has('filter')) {
            foreach ($this->request->input('filter') as $field=>$value) {
                $this->query = $this->applyFieldFilter($this->query, $field, $value);
            }
        }

        if ($this->request->has('order')) {
            foreach ($this->request->input('order') as $field=>$value) {
                $this->query = $this->applyOrder($this->query, $field, $value);
            }
        }

        if ($this->request->has('limit')) {
            $this->query = $this->query->limit($this->request->input('limit'));
        }

        return $this->query;
    }

    /**
     * Resolves :and: and :or: links and applies each filter
     *
     * @param Builder $query
     * @param $field
     * @param $value
     * @return Builder
     */
    private function applyFieldFilter(Builder $query, $field, $value)
    {
        $query = $this->resolveOrLinks($query, $field, $value);

        return $query;
    }

    private function resolveOrLinks(Builder $query, $field, $value)
    {
        $filters = explode(':or:', $value);
        if (count($filters) > 1) {

            $that = $this;
            $query->where(function ($query) use ($filters, $field, $that) {
                $first = true;
                foreach ($filters as $filter) {
                    $verb = $first ? 'where' : 'orWhere';
                    $query->$verb(function ($query) use ($field, $filter, $that) {
                        $query = $that->resolveAndLinks($query, $field, $filter);
                    });
                    $first = false;
                }
            });
        }
        else {

            $query = $this->resolveAndLinks($query, $field, $value);

        }

        return $query;
    }

    private function resolveAndLinks(Builder $query, $field, $value)
    {
        $filters = explode(':and:', $value);
        foreach ($filters as $filter) {
            $query = $this->applyFilter($query, $field, $filter);
        }

        return $query;
    }

    /**
     * Applies a single filter
     *
     * @param Builder $query
     * @param $field
     * @param $filter
     * @param $or = false
     * @return Builder
     */
    private function applyFilter(Builder $query, $field, $filter, $or = false)
    {
        $filter = explode(':', $filter);
        if (count($filter) > 1) {
            $operator = $this->getFilterOperator($filter[0]);
            $value = $this->replaceWildcards($filter[1]);
        }
        else {
            $operator = '=';
            $value = $this->replaceWildcards($filter[0]);
        }

        $fields = explode('.', $field);
        if (count($fields) > 1) {
            return $this->applyNestedFilter($query, $fields, $operator, $value, $or);
        }
        else {
            return $this->applyWhereClause($query, $field, $operator, $value, $or);
        }
    }

    /**
     * Applies a nested filter.
     * Meaning a filter on a related field
     *
     * @param Builder $query
     * @param array $fields
     * @param $operator
     * @param $value
     * @param $or = false
     * @return Builder
     */
    private function applyNestedFilter(Builder $query, array $fields, $operator, $value, $or = false)
    {
        $relation_name = implode('.', array_slice($fields, 0, count($fields) - 1));
        $relation_field = $fields[count($fields) - 1];

        $that = $this;

        return $query->whereHas($relation_name, function ($query) use ($relation_field, $operator, $value, $that) {
            $query = $that->applyWhereClause($query, $relation_field, $operator, $value, $or);
        });
    }

    /**
     * Applies a where clause.
     * Is used by applyFilter and applyNestedFilter
     * to apply the clause to the query.
     *
     * @param Builder $query
     * @param $field
     * @param $operator
     * @param $value
     * @param $or = false
     * @return Builder
     */
    private function applyWhereClause(Builder $query, $field, $operator, $value, $or = false) {
        $verb = $or ? 'orWhere' : 'where';
        $null_verb = $or ? 'orWhereNull' : 'whereNull';
        $not_null_verb = $or ? 'orWhereNotNull' : 'whereNotNull';

        switch ($value) {
            case 'today':
                return $query->$verb($field, 'like', Carbon::now()->format('Y-m-d') . '%');
            case 'nottoday':
                return $query->$verb(function ($q) use ($field) {
                    $q->where($field, 'not like', Carbon::now()->format('Y-m-d') . '%')
                        ->orWhereNull($field);
                });
            case 'null':
                return $query->$null_verb($field);
            case 'notnull':
                return $query->$not_null_verb($field);
            default:
                return $query->$verb($field, $operator, $value);
        }
    }

    /**
     * @param Builder $query
     * @param $field
     * @param $value
     * @return mixed
     */
    private function applyOrder(Builder $query, $field, $value)
    {
        $fields = explode('.', $field);
        if (count($fields) > 1) {
            return $this->applyNestedOrder($fields[0], $query, $fields[1], $value);
        }
        else {
            return $this->applyOrderByClause($query, $field, $value);
        }
    }

    /**
     * @TODO: This does not work yet. Order by doesn't seem to support this the same way whereHas does
     *
     * @param $relation_name
     * @param Builder $query
     * @param $relation_field
     * @param $value
     * @return mixed
     */
    private function applyNestedOrder($relation_name, Builder $query, $relation_field, $value)
    {
        $that = $this;
        return $query->orderBy($relation_name, function ($query) use ($relation_field, $value, $that) {
            $query = $that->applyOrderByClause($query, $relation_field, $value);
        });
    }

    /**
     * @param Builder $query
     * @param $field
     * @param $value
     * @return mixed
     */
    private function applyOrderByClause(Builder $query, $field, $value)
    {
        return $query->orderBy($field, $value);
    }

    /**
     * Replaces * wildcards with %
     * for usage in SQL
     *
     * @param $value
     * @return mixed
     */
    private function replaceWildcards($value)
    {
        return str_replace('*', '%', $value);
    }

    /**
     * Translated operators to SQL
     *
     * @param $filter
     * @return mixed
     */
    private function getFilterOperator($filter)
    {
        $operator = str_replace('notlike', 'not like', $filter);
        $operator = str_replace('gt', '>', $operator);
        $operator = str_replace('ge', '>=', $operator);
        $operator = str_replace('lt', '<', $operator);
        $operator = str_replace('le', '<=', $operator);
        $operator = str_replace('eq', '=', $operator);

        return $operator;
    }
}