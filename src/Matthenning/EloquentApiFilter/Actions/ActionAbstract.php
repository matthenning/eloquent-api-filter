<?php

namespace Matthenning\EloquentApiFilter\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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
    public function __construct(Request $request, string $model_name, int $model_id = null)
    {
        $this->request = collect($request->all());
        $this->model_name = $model_name;
        $this->fields = $this->request->keys()->filter(fn($k) => $k != 'relations');
        $this->relations = new Collection();

        if (!is_null($model_id)) {
            $this->setModel($model_id);
            $this->separateRelations();
        }
    }

    /**
     * @return self
     */
    protected function separateRelations(): self
    {
        $relations = $this->model->getRelationNames();

        foreach ($relations as $name=>$value) {
            if ($this->fields->contains($name)) {
                $related = $this->request->get($name);
                $this->parseRelation($related, $name);
                $this->fields->forget($this->fields->search($name));
            }
        }

        return $this;
    }

    /**
     * @param mixed $related
     * @param string $name
     * @return self
     */
    protected function parseRelation(mixed $related, string $name): self
    {
        if (is_object($related))
            $this->relations[$name] = $related->id;
        else if (is_array($related)) {
            if (isset($related['id']))
                $this->relations[$name] = $related['id'];
            else {
                $this->relations[$name] = array_map(function ($r) {
                    if (is_object($r)) {
                        return $r->id;
                    } else if (is_array($r)) {
                        return $r['id'];
                    }
                    return $r;
                }, $related);
            }
        }

        return $this;
    }



    /**
     * @return self
     * @throws ReflectionException
     * @throws UnhandleableRelationshipException
     */
    protected function updateRelations(): self
    {
        // Remove related models which were not present in the update query at all
        foreach ($this->model::getRelationNames() as $name => $relation) {
            if (!$this->relations->contains($name)) {
                switch ($relation) {
                    case 'BelongsTo':
                    case 'MorphTo':
                        $this->model->$name()->dissociate();
                        break;

                    case 'BelongsToMany':
                    case 'MorphedByMany':
                    case 'MorphToMany':
                        $this->model->$name()->detach();
                        break;
                }
            }
        }

        if (!$this->relations) return $this;

        // Sync relations from query
        foreach ($this->relations as $name => $ids) {
            $relation = $this->model::getRelationNames()[$name];
            $model_name = $this->model->$name()->getRelated();
            $models = $model_name::findMany($ids);

            switch ($relation) {
                case 'BelongsTo':
                case 'MorphTo':
                    $this->model->$name()->associate($ids);
                    break;

                case 'BelongsToMany':
                case 'MorphedByMany':
                case 'MorphToMany':
                    $this->model->$name()->sync($ids);
                    break;

                case 'HasOne':
                case 'MorphOne':
                    $this->model->$name()->save($ids);
                    break;

                case 'HasMany':
                    // Emulating a sync method by first removing and then adding related models
                    $fk = $this->model->$name()->getForeignKeyName();
                    $this->model->$name->filter(function (Model $m) use ($ids) {
                        return !in_array($m->id, $ids);
                    })->each(function (Model $m) use ($fk) {
                        $m->$fk = null;
                        $m->save();
                    });

                    $this->model->$name()->saveMany($models);
                    break;

                case 'MorphMany':
                    // Emulating a sync method by first removing and then adding related models
                    $fk = $this->model->$name()->getForeignKeyName();
                    $ft = $this->model->$name()->getMorphType();
                    $this->model->$name->filter(function (Model $m) use ($ids) {
                        return !in_array($m->id, $ids);
                    })->each(function (Model $m) use ($fk, $ft) {
                        $m->$fk = null;
                        $m->$ft = null;
                        $m->save();
                    });

                    $this->model->$name()->saveMany($models);
                    break;

                default:
                    throw new UnhandleableRelationshipException($relation);
            }
        }

        return $this;
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

    /**
     * @param int $id
     * @return $this
     * @throws ModelNotFoundException
     */
    public function setModel(int $id): self
    {
        $this->id = $id;
        $this->model = $this->model_name::findOrFail($this->id);

        return $this;
    }

}
