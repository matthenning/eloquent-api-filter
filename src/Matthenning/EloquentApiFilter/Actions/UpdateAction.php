<?php

namespace Matthenning\EloquentApiFilter\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Matthenning\EloquentApiFilter\Exceptions\MissingRequestFieldException;
use Matthenning\EloquentApiFilter\Exceptions\ReflectionException;
use Matthenning\EloquentApiFilter\Exceptions\UnhandleableRelationshipException;

class UpdateAction extends ActionAbstract
{

    /**
     * @var int
     */
    protected int $id;

    /**
     * @param Request $request
     * @param string $model_name
     * @param int $id
     * @return static
     * @throws ModelNotFoundException
     */
    public static function prepare(Request $request, string $model_name, int $id): self
    {
        $action = new self($request, $model_name);
        $action->setModel($id);

        return $action;
    }

    /**
     * @return Model
     * @throws MissingRequestFieldException
     * @throws ReflectionException
     * @throws UnhandleableRelationshipException
     */
    public function invoke(): Model
    {
        $this->updateModel()->updateRelations();

        $this->model->save();

        return $this->model;
    }

    /**
     * @return Model
     * @throws MissingRequestFieldException
     * @throws ReflectionException
     * @throws UnhandleableRelationshipException
     */
    public function __invoke()
    {
        return $this->invoke();
    }

    /**
     * @return $this
     * @throws MissingRequestFieldException
     */
    protected function updateModel(): self
    {
        foreach ($this->fields as $field) {
            if ($this->strict && !$this->request->has($field)) {
                throw new MissingRequestFieldException($field);
            }

            $this->model->$field = $this->request->get($field);
        }

        if ($this->auto) {
            foreach ((new $this->model_name)->getFillable() as $fillable) {
                if ($this->fields->contains($fillable)) continue;

                $field = preg_replace('/_id$/', '', $fillable);
                if ($this->relations->has($field)) {
                    $this->model->$fillable = $this->relations->get($field);
                }
            }
        }

        return $this;
    }

    /**
     * @return $this
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
