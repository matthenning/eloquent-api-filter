<?php

namespace Matthenning\EloquentApiFilter;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;

/**
 * Class EloquentApiFilter
 * @package Matthenning\EloquentApiFilter
 */
class EloquentApiFilter {


    /**
     * @var Request
     */
    private Request $request;

    /**
     * @var EloquentBuilder|QueryBuilder|Relation
     */
    private EloquentBuilder|QueryBuilder|Relation $query;

    /**
     * @param Request $request
     * @param EloquentBuilder|QueryBuilder|Relation $query
     */
    public function __construct(
        Request $request,
        EloquentBuilder|QueryBuilder|Relation $query
    )
    {
        $this->request = $request;
        $this->query = $query;
    }

    /**
     * @return EloquentBuilder|QueryBuilder|Relation
     */
    public function filter(): EloquentBuilder|QueryBuilder|Relation
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

        if ($this->request->has('select')) {
            $this->query = $this->query->select(explode(',', $this->request->input('select')));
        }

        return $this->query;
    }

    /**
     * Adds relations from the $relations array
     * Each field's value needs to be a valid name of a relation
     *
     * @param EloquentBuilder|QueryBuilder|Relation $query
     * @param array $relations
     * @return EloquentBuilder|QueryBuilder|Relation
     */
    private function joinRelations(
        EloquentBuilder|QueryBuilder|Relation $query,
        array $relations
    ): EloquentBuilder|QueryBuilder|Relation
    {
        $query = $query->with(...$relations);

        $this->meta['relations'] = $relations;

        return $query;
    }

    /**
     * Resolves :or: and then :and: links
     *
     * @param EloquentBuilder|QueryBuilder|Relation $query
     * @param string $field
     * @param string $value
     * @return EloquentBuilder|QueryBuilder|Relation
     */
    private function applyFieldFilter(
        EloquentBuilder|QueryBuilder|Relation $query,
        string $field,
        string $value
    ): EloquentBuilder|QueryBuilder|Relation
    {
        $query = $this->resolveOrLinks($query, $field, $value);

        return $query;
    }

    /**
     * Resolves :or: links and then resolves the :and: links in the resulting sections
     *
     * @param EloquentBuilder|QueryBuilder|Relation $query
     * @param string $field
     * @param string $value
     * @return EloquentBuilder|QueryBuilder|Relation
     */
    private function resolveOrLinks(
        EloquentBuilder|QueryBuilder|Relation $query,
        string $field,
        string $value
    ): EloquentBuilder|QueryBuilder|Relation
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
     * @param EloquentBuilder|QueryBuilder|Relation $query
     * @param string $field
     * @param string $value
     * @return EloquentBuilder|QueryBuilder|Relation
     */
    private function resolveAndLinks(
        EloquentBuilder|QueryBuilder|Relation $query,
        string $field,
        string $value
    ): EloquentBuilder|QueryBuilder|Relation
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
     * @param EloquentBuilder|QueryBuilder|Relation $query
     * @param string $field
     * @param string $filter
     * @param bool $or = false
     * @return EloquentBuilder|QueryBuilder|Relation
     */
    private function applyFilter(
        EloquentBuilder|QueryBuilder|Relation $query,
        string $field,
        string $filter,
        bool $or = false
    ): EloquentBuilder|QueryBuilder|Relation
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
     * @param EloquentBuilder|QueryBuilder|Relation $query
     * @param array $fields
     * @param string $operator
     * @param string $value
     * @param bool $or = false
     * @return EloquentBuilder|QueryBuilder|Relation
     */
    private function applyNestedFilter(
        EloquentBuilder|QueryBuilder|Relation $query,
        array $fields,
        string $operator,
        string $value,
        bool $or = false
    ): EloquentBuilder|QueryBuilder|Relation
    {
        $relation_name = implode('.', array_slice($fields, 0, count($fields) - 1));
        $relation_field = end($fields);
        if ($relation_name[0] == '!') {
            $relation_name = substr($relation_name, 1, strlen($relation_name));

            $that = $this;

            return $query->whereHas($relation_name, function ($query) use ($relation_field, $operator, $value, $that, $or) {
                $query = $that->applyWhereClause($query, $relation_field, $operator, $value, $or);
            }, '=', 0);
        }

        $that = $this;

        return $query->whereHas($relation_name, function ($query) use ($relation_field, $operator, $value, $that, $or) {
            $query = $that->applyWhereClause($query, $relation_field, $operator, $value, $or);
        });
    }

    /**
     * Applies a where clause.
     * Is used by applyFilter and applyNestedFilter to apply the clause to the query.
     *
     * @param EloquentBuilder|QueryBuilder|Relation $query
     * @param $field
     * @param $operator
     * @param $value
     * @param bool $or = false
     * @return EloquentBuilder|QueryBuilder|Relation
     */
    private function applyWhereClause(
        EloquentBuilder|QueryBuilder|Relation $query,
        string $field,
        string $operator,
        string $value,
        bool $or = false
    ): EloquentBuilder|QueryBuilder|Relation
    {
        $verb = $or ? 'orWhere' : 'where';
        $in_verb = $or ? 'orWhereIn' : 'whereIn';
        $not_in_verb = $or ? 'orWhereNotIn' : 'whereNotIn';
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
                if ($operator == 'in') {
                    return $query->$in_verb($field, explode(',', $value));
                }
                if ($operator == 'notin') {
                    return $query->$not_in_verb($field, explode(',', $value));
                }

                return $query->$verb($field, $operator, $value);
        }
    }

    /**
     * @param EloquentBuilder|QueryBuilder|Relation $query
     * @param string $field
     * @param string $value
     * @return EloquentBuilder|QueryBuilder|Relation
     */
    private function applyOrder(
        EloquentBuilder|QueryBuilder|Relation $query,
        string $field,
        string $value
    ): EloquentBuilder|QueryBuilder|Relation
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
     * @param string $relation_name
     * @param EloquentBuilder|QueryBuilder|Relation $query
     * @param string $relation_field
     * @param string $value
     * @return EloquentBuilder|QueryBuilder|Relation
     */
    private function applyNestedOrder(
        string $relation_name,
        EloquentBuilder|QueryBuilder|Relation $query,
        string $relation_field,
        string $value
    ): EloquentBuilder|QueryBuilder|Relation
    {
        $that = $this;
        return $query->orderBy($relation_name, function ($query) use ($relation_field, $value, $that) {
            $query = $that->applyOrderByClause($query, $relation_field, $value);
        });
    }

    /**
     * @param EloquentBuilder|QueryBuilder|Relation $query
     * @param string $field
     * @param string $value
     * @return EloquentBuilder|QueryBuilder|Relation
     */
    private function applyOrderByClause(
        EloquentBuilder|QueryBuilder|Relation $query,
        string $field,
        string $value
    ): EloquentBuilder|QueryBuilder|Relation
    {
        $value = $this->base64decodeIfNecessary($value);
        return $query->orderBy($field, $value);
    }

    /**
     * Replaces * wildcards with %
     * for usage in SQL
     *
     * @param string $value
     * @return string
     */
    private function replaceWildcards(string $value): string
    {
        return str_replace('*', '%', $value);
    }

    /**
     * Translates operators to SQL
     *
     * @param string $filter
     * @return string
     */
    private function getFilterOperator(string $filter): string
    {
        $operator = str_replace('notlike', 'not like', $filter);
        $operator = str_replace('gt', '>', $operator);
        $operator = str_replace('ge', '>=', $operator);
        $operator = str_replace('lt', '<', $operator);
        $operator = str_replace('le', '<=', $operator);
        $operator = str_replace('eq', '=', $operator);
        $operator = str_replace('ne', '!=', $operator);

        return $operator;
    }

    /**
     * Searches for {{b64(some based 64 encoded string)}}
     * If found, returns the decoded content
     * If not, returns the original value
     *
     * @param string $value
     * @return string
     */
    private function base64decodeIfNecessary(string $value): string
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
