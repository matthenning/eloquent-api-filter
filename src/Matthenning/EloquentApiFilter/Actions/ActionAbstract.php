<?php

namespace Matthenning\EloquentApiFilter\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

abstract class ActionAbstract
{

    public const INDEX = 'index';
    public const SHOW = 'show';
    public const STORE = 'store';
    public const UPDATE = 'update';
    public const DESTROY = 'destroy';

    /**
     * @var Collection
     */
    protected Collection $request;

    /**
     * @var Collection
     */
    protected Collection $fields;

    /**
     * @var Collection|mixed
     */
    protected Collection $relations;

    /**
     * @var string
     */
    protected string $model_name;

    /**
     * @var Model
     */
    protected Model $model;

    /**
     * @var bool
     */
    protected bool $strict = false;

    /**
     * @var bool
     */
    protected bool $auto = false;

    /**
     * StoreAction constructor.
     * @param Request $request
     * @param string $model_name
     */
    public function __construct(Request $request, string $model_name)
    {
        $this->request = $request->valid();
        $this->model_name = $model_name;

        $this->fields = $this->request->keys()->filter(fn($k) => $k != 'relations');
        $this->relations = collect($this->request->get('relations'));
    }

    /**
     * Override automatically generated field list.
     *
     * @param Collection $fields
     * @return $this
     */
    public function fields(Collection $fields): self
    {
        $this->fields = $fields;

        return $this;
    }

    /**
     * Throw Exception if a field from the fields list is not contained in the request.
     *
     * @param bool $strict
     * @return $this
     */
    public function strict(bool $strict = true): self
    {
        $this->strict = $strict;

        return $this;
    }

    /**
     * Try to automatically extract fillable fields from relations.
     * Useful e.g. for creating models with a not-null belongsTo relations.
     *
     * @param bool $auto
     * @return $this
     */
    public function auto(bool $auto = true): self
    {
        $this->auto = $auto;

        return $this;
    }

    /**
     * Exclude a field from the request
     *
     * @param string $except
     * @return $this
     */
    public function except(array $except): self
    {
        $this->fields = $this->fields->except($except);
        $this->relations = $this->relations->except($except);

        return $this;
    }

}
