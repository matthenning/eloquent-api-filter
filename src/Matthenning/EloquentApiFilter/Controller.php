<?php

namespace Matthenning\EloquentApiFilter;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Collection;
use Matthenning\EloquentApiFilter\Traits\FiltersEloquentApi;

abstract class Controller extends BaseController
{

    use FiltersEloquentApi;

    /**
     * If the controller name does not match
     * the schema {Model}Controller or the model
     * is in another namespace, set this
     * property to the model's name.
     *
     * @var string|null
     */
    protected ?string $modelName = null;

    /**
     * Stores metadata to be included in the
     * response alongside the actual data.
     *
     * @var array
     */
    protected array $meta = [];

    public function __construct(
        protected Request $request,
        protected Defaults $defaults
    ) {
        $this->modelName = $this->getModelName();
    }

    /**
     * Use the UsesDefaultIndexMethod trait to use
     * the default index method with your controller.
     *
     * @return JsonResponse
     */
    protected function _index(): JsonResponse
    {
        $query = $this->modelName::query();

        if ($this->request->has('all')) {
            return $this->respondFiltered($query);
        }

        return $this->respondFilteredAndPaginated($query);
    }

    /**
     * Use the UsesDefaultShowMethod trait to use
     * the default show method with your controller.
     *
     *
     * @param mixed $id
     * @return JsonResponse
     */
    protected function _show(mixed $id): JsonResponse
    {
        $query = $this->modelName::where('id', $id);

        return $this->respondFiltered($query, solo: true);
    }

    /**
     * Use the UsesDefaultShowMethod trait to use
     * the default show method with your controller.
     *
     * Use $pre and $post parameters to call function
     * before and after deletion for additional cleanup.
     *
     * @param Request $request
     * @param mixed $id
     * @param callable|null $pre Function to execute before model deletion. Parameters: Request $request, Model $model
     * @param callable|null $post Function to execute after model deletion. Parameters: Request $request, mixed $result_of_pre_function
     * @return JsonResponse
     */
    protected function _destroy(
        Request $request,
        mixed $id,
        callable $pre = null,
        callable $post = null
    ): JsonResponse
    {
        $model = $this->modelName::findOrFail($id);
        $pre_result = $pre ? $pre($request, $model) : null;
        $model->delete();
        if ($post) $post($request, $pre_result);

        return $this->respond();
    }

    /**
     * Returns the final JsonResponse to be sent
     * to the client.
     *
     * @param array $data
     * @param HttpStatusEnum|null $status
     * @return JsonResponse
     */
    protected function respond(
        array $data = [],
        HttpStatusEnum $status = null
    ): JsonResponse
    {
        $status = $status ?? HttpStatusEnum::OK;
        return new JsonResponse($data, $status->value);
    }

    /**
     * Respond with multiple errors.
     *
     * @param array $errors
     * @param HttpStatusEnum $status
     * @return JsonResponse
     */
    protected function respondWithErrors(
        array $errors,
        HttpStatusEnum $status
    ): JsonResponse
    {
        return $this->respond(['errors' => $errors], $status);
    }

    /**
     * Respond with a single error.
     *
     * @param string $error
     * @param HttpStatusEnum $status
     * @return JsonResponse
     */
    protected function respondWithError(
        string $error,
        HttpStatusEnum $status
    ): JsonResponse
    {
        return $this->respondWithErrors([$error], $status);
    }


    /**
     * Respond with 404 not found.
     *
     * @param string $message
     * @return JsonResponse
     */
    protected function respondNotFound(
        string $message = 'Model not found'
    ): JsonResponse
    {
        return $this->respondWithError($message, HttpStatusEnum::NotFound);
    }

    /**
     * Filters the API requests and responds
     * with the returned models.
     *
     * @param EloquentBuilder|QueryBuilder|Relation $query
     * @param bool $solo
     * @return JsonResponse
     */
    protected function respondFiltered(EloquentBuilder|QueryBuilder|Relation $query, bool $solo = false): JsonResponse
    {
        $results = $this->filterApiRequest($this->request, $query)->get();

        return $this->respondWithModels($results, $solo);
    }


    /**
     * Respond with an object containing both
     * the data and the meta data.
     *
     * @param array $data
     * @return JsonResponse
     */
    protected function respondWithData(
        array|JsonResource $data
    ): JsonResponse
    {
        return $this->respond([
            'meta' => $this->meta,
            'data' => $data
        ]);
    }

    /**
     * Respond with the filtered models
     * and pagination.
     *
     * @param EloquentBuilder|QueryBuilder|Relation $query
     * @return JsonResponse
     */
    protected function respondFilteredAndPaginated(
        EloquentBuilder|QueryBuilder|Relation $query
    ): JsonResponse
    {
        $query = $this->filterApiRequest($this->request, $query);
        $results = $this->paginateModels($query);

        return $this->respondWithPaginatedModels($results);
    }

    /**
     * Extracts meta data from the paginator
     * and responds with the paginated models.
     *
     * @param LengthAwarePaginator $paginator
     * @return JsonResponse
     */
    protected function respondWithPaginatedModels(
        LengthAwarePaginator $paginator
    ): JsonResponse
    {
        $items = new Collection($paginator->items());

        $this->meta['pagination'] = [
            'items' => $items->count(),
            'total_items' => $paginator->total(),
            'total_pages' => $paginator->lastPage(),
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage()
        ];

        if ($this->request->has('pagination')) {
            return $this->respondWithData([]);
        }

        return $this->respondWithModels($items);
    }


    /**
     * Enriches and transform a collection of models.
     *
     * @param Collection $models
     * @param bool $solo
     * @return JsonResponse
     */
    public function respondWithModels(
        Collection $models,
        bool $solo = false
    ): JsonResponse
    {
        $resource = $this->modelName::$resourceName ?? Resource::class;
        $transformed = $models->map(fn ($m) => new $resource($m));

        if ($solo) {
            return $this->respondWithData($transformed->first());
        }

        return $this->respondWithData($transformed->toArray());
    }

    /**
     * Applies pagination to models and returns a paginator.
     *
     * @param EloquentBuilder|QueryBuilder|Relation $query
     * @return LengthAwarePaginator
     */
    public function paginateModels(
        EloquentBuilder|QueryBuilder|Relation $query
    ): LengthAwarePaginator
    {
        if ($this->request->has('per_page')) {
            $perPage = ($pp = (int)$this->request->get('per_page')) == -1 ? $query->count() : $pp;
        } else {
            $perPage = $this->defaults->pagination_per_page;
        }

        return $query->paginate($perPage);
    }

    /**
     * Derives the model name from the controller name
     * and returns it. If $overrideModelName property
     * is set, it will be returned instead.
     *
     * @return string
     */
    protected function getModelName(): string
    {
        return $this->modelName ?? preg_replace('/Controller$/', '', get_class($this));
    }

}
