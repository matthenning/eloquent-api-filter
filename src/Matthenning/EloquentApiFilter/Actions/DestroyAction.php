<?php

namespace Matthenning\EloquentApiFilter\Actions;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Matthenning\EloquentApiFilter\Exceptions\Exception;

class DestroyAction extends ActionAbstract
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
     */
    public static function prepare(Request $request, string $model_name, int $id): self
    {
        $action = new self($request, $model_name);
        $action->setModel($id);

        return $action;
    }

    /**
     * @return void
     * @throws Exception
     */
    public function invoke(): void
    {
        $this->destroyModel();
    }

    /**
     * @throws Exception
     */
    public function __invoke(): void
    {
        $this->invoke();
    }

    /**
     * @return $this
     * @throws Exception
     */
    protected function destroyModel(): self
    {
        $this->model->delete();

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
