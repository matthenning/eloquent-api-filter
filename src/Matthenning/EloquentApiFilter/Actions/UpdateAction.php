<?php

namespace Matthenning\EloquentApiFilter\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Matthenning\EloquentApiFilter\Exceptions\MissingRequestFieldException;

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
        $action = new self($request, $model_name, $id);

        return $action;
    }

    /**
     * @return Model
     * @throws MissingRequestFieldException
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

        return $this;
    }

}
