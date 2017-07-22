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
        if ($this->request->has('with')) {
            $this->query = $this->joinRelations($this->query, $this->request->input('with'));
        }

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
     * Adds relations from the $relations array
     * Each field's value needs to be a valid name of a relation
     *
     * @param Builder $query
     * @param array $relations
     * @return Builder
     */
    private function joinRelations(Builder $query, array $relations)
    {
        $query = $query->with(...$relations);

        return $query;
    }

    /**
     * Resolves :or: and then :and: links
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

    /**
     * Resolves :or: links and then resolves the :and: links in the resulting sections
     *
     * @param Builder $query
     * @param $field
     * @param $value
     * @return Builder
     */
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

    /**
     * @param Builder $query
     * @param $field
     * @param $value
     * @return Builder
     */
    private function resolveAndLinks(Builder $query, $field, $value)
    {
        $filters = explode(':and:', $value);
        foreach ($filters as $filter) {
            $query = $this->applyFilter($query, $field, $filter);
        }

        return $query;
    }

    /**
     * Applies a single filter to the query
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
     * Nested filters are filters on field on related models.
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

        return $query->whereHas($relation_name, function ($query) use ($relation_field, $operator, $value, $that, $or) {
            $query = $that->applyWhereClause($query, $relation_field, $operator, $value, $or);
        });
    }

    /**
     * Applies a where clause.
     * Is used by applyFilter and applyNestedFilter to apply the clause to the query.
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

        $value = $this->base64decodeIfNecessary($value);

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
        $value = $this->base64decodeIfNecessary($value);
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
     * Translates operators to SQL
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

    /**
     * Searches for {{b64(some based 64 encoded string)}}
     * If found, returns the decoded content
     * If not, returns the original value
     *
     * @param $value
     * @return bool|string
     */
    private function base64decodeIfNecessary($value)
    {
        preg_match("/\{\{b64\((.*)\)\}\}/", $value, $matches);
        if ($matches) {
            return base64_decode($matches[1]);
        }
        else {
            return $value;
        }
    }
}