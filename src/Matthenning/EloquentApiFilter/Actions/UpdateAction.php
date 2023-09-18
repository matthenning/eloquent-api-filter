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
        $action = new self($request, $model_name);
        $action->setModel($id);

        return $action;
    }

    /**
     * @return Model
     * @throws MissingRequestFieldException
     */
    public function invoke(): Model
    {
        $this->updateModel();

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
