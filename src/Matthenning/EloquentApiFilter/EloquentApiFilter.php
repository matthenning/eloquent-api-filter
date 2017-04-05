<?php

namespace Matthenning\EloquentApiFilter;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class EloquentApiFilter {


    private $request;

    private $query;

    /**
     * Filters an Eloquent Builder using request parameters
     *
     * .../model?filter[field]=operator:comparison
     * .../model?filter[field]=operator
     *
     * Example queries:
     * .../users?filter[name]=like:Rob*&filter[deceased]=null:
     * will match all entities where name starts with Rob and deceased is null
     *
     * Multiple filters on one field can be chained:
     * .../users?filter[created_at]=lt:2016-12-10:and:gt:2016-12-08
     * will match all entities where created_at is between 2016-12-10 and 2016-12-08
     *
     * Filter by related models' fields by using the dot-notaion:
     * .../users?filter[users.posts.name]=like:*API*
     * will match all Posts of Users where Post name contains "API"
     *
     * Filter timestamps
     * .../users?filter[birthday]=today
     * will match all users whos' birthdays are today
     *
     * Limit and sorting:
     * .../users?filter[age]=ge:21&order[name]=asc&limit=10
     * will match the top 10 users with age of 21 or older sorted by name in ascending order
     *
     * Operators:
     * like, notlike, today (for timestamps), nottoday (for timestamps), null, notnull,
     * ge (greater or equal), gt (greater), le (lower or equal), lt (lower), eq (equal)
     *
     * @param Request $request
     * @param $query
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
     * Resolves :and: links and applies each filter
     *
     * @param Builder $query
     * @param $field
     * @param $value
     * @return Builder
     */
    private function applyFieldFilter(Builder $query, $field, $value)
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
     * @return Builder
     */
    private function applyFilter(Builder $query, $field, $filter)
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
            return $this->applyNestedFilter($query, $fields, $operator, $value);
        }
        else {
            return $this->applyWhereClause($query, $field, $operator, $value);
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
     * @return Builder
     */
    private function applyNestedFilter(Builder $query, array $fields, $operator, $value)
    {
        $relation_name = implode('.', array_slice($fields, 0, count($fields) - 1));
        $relation_field = $fields[count($fields) - 1];

        $that = $this;

        return $query->whereHas($relation_name, function ($query) use ($relation_field, $operator, $value, $that) {
            $query = $that->applyWhereClause($query, $relation_field, $operator, $value);
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
     * @return Builder
     */
    private function applyWhereClause(Builder $query, $field, $operator, $value) {
        switch ($value) {
            case 'today':
                return $query->where($field, 'like', Carbon::now()->format('Y-m-d') . '%');
            case 'nottoday':
                return $query->where(function ($q) use ($field) {
                    $q->where($field, 'not like', Carbon::now()->format('Y-m-d') . '%')
                        ->orWhereNull($field);
                });
            case 'null':
                return $query->whereNull($field);
            case 'notnull':
                return $query->whereNotNull($field);
            default:
                return $query->where($field, $operator, $value);
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