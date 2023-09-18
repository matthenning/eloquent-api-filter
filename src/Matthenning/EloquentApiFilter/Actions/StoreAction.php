<?php

namespace Matthenning\EloquentApiFilter\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Matthenning\EloquentApiFilter\Exceptions\MissingRequestFieldException;
use Matthenning\EloquentApiFilter\Exceptions\ReflectionException;
use Matthenning\EloquentApiFilter\Exceptions\UnhandleableRelationshipException;

class StoreAction extends ActionAbstract
{

    /**
     * @param Request $request
     * @param string $model_name
     * @return static
     */
    public static function prepare(Request $request, string $model_name): self
    {
        return new self($request, $model_name);
    }

    /**
     * @return Model
     * @throws MissingRequestFieldException
     * @throws ReflectionException
     * @throws UnhandleableRelationshipException
     */
    public function invoke(): Model
    {
        $this->createModel()->attachRelations();

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
    protected function createModel(): self
    {
        $payload = [];

        foreach ($this->fields as $field) {
            if ($this->strict && !$this->request->has($field)) {
                throw new MissingRequestFieldException($field);
            }

            $payload[$field] = $this->request->get($field);
        }

        if ($this->auto) {
            foreach ((new $this->model_name)->getFillable() as $fillable) {
                if ($this->fields->contains($fillable)) continue;

                $field = preg_replace('/_id$/', '', $fillable);
                if ($this->relations->has($field)) {
                    $payload[$fillable] = $this->relations->get($field);
                }
            }
        }

        $this->model = $this->model_name::create($payload);

        return $this;
    }

    /**
     * @return $this
     * @throws ReflectionException
     * @throws UnhandleableRelationshipException
     */
    protected function attachRelations(): self
    {
        if (!$this->relations) return $this;

        foreach ($this->relations as $name => $ids) {
            $relation = $this->model::getRelationNames()[$name];
            $model_name = $this->model->$name()->getRelated();
            $models = $model_name::findMany($ids);

            switch ($relation) {
                case 'BelongsTo':
                case 'MorphTo':
                    $this->model->$name()->associate($models);
                    break;
                case 'BelongsToMany':
                case 'MorphToMany':
                case 'MorphedByMany':
                    $this->model->$name()->attach($models);
                    break;
                case 'HasOne':
                case 'HasMany':
                case 'MorphOne':
                case 'MorphMany':
                    $this->model->$name()->saveMany($models);
                    break;
                default:
                    throw new UnhandleableRelationshipException($relation);
            }
        }

        return $this;
    }

}
