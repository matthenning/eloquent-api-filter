<?php

namespace Matthenning\EloquentApiFilter;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use ReflectionClass;

class Resource extends JsonResource
{

    public function toArray(Request $request): array
    {
        $data = [];

        foreach ($this->resource->getAttributes() as $key=>$attribute) {
            $data[$key] = $this->resource->$key;
        }

        return $this->enrich($data);
    }

    public function enrich($data): array
    {
        foreach ($this->getRelationNames() as $relation=>$relationClass) {
            if ($this->resource->relationLoaded($relation)) {
                if (isset(($this->resource->$relation)::$resourceName) && ($this->resource->$relation)::$resourceName != null) {
                    $data[$relation] = new (($this->resource->$relation)::$resourceName)($this->resource->$relation);
                } else {
                    $data[$relation] = $this->resource->$relation;
                }
            }
        }

        return $data;
    }

    /**
     * @throws \ReflectionException
     */
    private function getRelationNames(): array
    {
        $reflection = new ReflectionClass($this->resource);
        $relations = [];
        foreach ($reflection->getMethods() as $method) {
            $return_type = $method->getReturnType();
            if (is_null($return_type)) {
                continue;
            }

            $namespace_parts = explode('\\', $return_type->getName());
            $class_name = $namespace_parts[count($namespace_parts) - 1];
            unset($namespace_parts[count($namespace_parts) - 1]);
            $namespace = implode('\\', $namespace_parts);

            if ($namespace == 'Illuminate\\Database\\Eloquent\\Relations') {
                $relations[$method->getName()] = $class_name;
            }
        };

        return $relations;
    }

}
