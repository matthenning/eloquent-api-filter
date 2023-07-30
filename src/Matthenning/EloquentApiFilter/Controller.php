<?php

namespace Matthenning\EloquentApiFilter;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Collection;
use Matthenning\EloquentApiFilter\Traits\FiltersEloquentApi;

abstract class Controller extends BaseController
{

    use FiltersEloquentApi;

    /**
     * If the controller name does not match
     * the schema {Model}Controller, set this
     * property to the model's name.
     *
     * @var string|null
     */
    protected ?string $overrideModelName = null;

    /**
     * Before transformation, you have the option
     * to call another method on the model to enrich
     * its data.
     *
     * @var string|null
     */
    protected ?string $overrideEnrichMethod = null;

    /**
     * Per default, we expect a transform() method
     * to be present on the model. If it's not you
     * can change the name of the method here. It
     * should return and associative array representing
     * the model as it should be retrievable through
     * the API.
     *
     * @var string|null
     */
    protected ?string $overrideTransformMethod = 'transform';

    /**
     * Stores meta data to be included in the
     * response along side the actual data.
     *
     * @var array
     */
    protected array $meta = [];

    public function __construct(
        protected Request $request,
        protected Defaults $defaults
    ) {}

    /**
     * Use the UsesDefaultIndexMethod trait to use
     * the default index method with your controller
     *
     * @return JsonResponse
     */
    protected function _index(): JsonResponse
    {
        $model_name = $this->getModelName();
        $query = $model_name::query();

        if ($this->request->has('all')) {
            return $this->respondFiltered($query);
        }

        return $this->respondFilteredAndPaginated($query);
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
        return new JsonResponse(json_encode($data), $status);
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
     * @return JsonResponse
     */
    protected function respondFiltered(EloquentBuilder|QueryBuilder|Relation $query): JsonResponse
    {
        $results = $this->filterApiRequest($this->request, $query)->get();

        return $this->respondWithModels($results);
    }


    /**
     * Respond with an object containing both
     * the data and the meta data.
     *
     * @param array $data
     * @return JsonResponse
     */
    protected function respondWithData(
        array $data
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
     * @return JsonResponse
     */
    public function respondWithModels(
        Collection $models
    ): JsonResponse
    {
        $transformed = $models->map(function (Model $model) {
            if ($this->overrideEnrichMethod) {
                $model = $model->{$this->overrideEnrichMethod}();
            }

            return $model->{$this->overrideEnrichMethod}();
        });

        return $this->respondWithData(array_values($transformed->toArray()));
    }


    /**
     * Retrieve models from a query, paginated
     * and responds with them.
     *
     * @param EloquentBuilder|QueryBuilder|Relation $query
     * @return JsonResponse
     */
    protected function respondPaginated(
        EloquentBuilder|QueryBuilder|Relation $query
    ): JsonResponse
    {
        $results = $this->paginateModels($query);

        return $this->respondWithPaginatedModels($results);
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
        return $this->overrideModelName ?? preg_replace('/Controller$/', '', get_class($this));
    }

}