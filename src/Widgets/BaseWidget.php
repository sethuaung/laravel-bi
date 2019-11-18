<?php

namespace LaravelBi\LaravelBi\Widgets;

use Closure;
use Illuminate\Http\Request;
use LaravelBi\LaravelBi\Dashboard;
use Illuminate\Database\Eloquent\Builder;

abstract class BaseWidget implements \JsonSerializable, Widget
{
    use Traits\HasAttributes;

    public $width;
    public $key;
    public $name;
    public $scope;

    public function __construct($key, $name)
    {
        $this->key   = $key;
        $this->name  = $name;
        $this->scope = function (Builder $builder) {
            return $builder;
        };
    }

    public static function create($key, $name): Widget
    {
        return new static($key, $name);
    }

    public function width($width)
    {
        $this->width = $width;

        return $this;
    }

    public function scope(Closure $scope): Widget
    {
        $this->scope = $scope;

        return $this;
    }

    protected function extra()
    {
        return new \stdClass();
    }

    public function data(Dashboard $dashboard, Request $request)
    {
        $builder = $this->getBaseBuilder($dashboard);
        $builder = $this->applyAttributes($builder);
        $builder = $this->applyFilters($builder, $dashboard, $request);

        $rawModels = $builder->get();

        return $rawModels->map(function ($rawModel) {
            return $this->displayModel($rawModel)->toArray();
        });
    }

    public function jsonSerialize()
    {
        return [
            'width'      => $this->width,
            'key'        => $this->key,
            'name'       => $this->name,
            'component'  => $this->component,
            'metrics'    => $this->metrics,
            'dimensions' => $this->dimensions
        ];
    }

    protected function getBaseBuilder(Dashboard $dashboard): Builder
    {
        $builder = $dashboard->model::query();
        $builder = $this->scope->call($this, $builder);

        return $builder;
    }

    protected function applyAttributes(Builder $builder): Builder
    {
        $this->getAttributes()->reduce(function ($builder, $attribute) {
            return $attribute->apply($builder, $this);
        }, $builder);

        return $builder;
    }

    protected function applyFilters(Builder $builder, Dashboard $dashboard, Request $request)
    {
        $requestedFilters = $request->input('filters');

        return collect($dashboard->filters())->reduce(function (Builder $builder, $filter) use ($request, $requestedFilters) {
            if (isset($requestedFilters[$filter->key])) {
                return $filter->apply($builder, $requestedFilters[$filter->key], $request);
            }

            return $builder;
        }, $builder);
    }

    protected function displayModel($rawRow)
    {
        return $this->getAttributes()->reduce(function ($rawRow, $attribute) {
            $rawRow->{$attribute->key} = $attribute->display($rawRow);

            return $rawRow;
        }, $rawRow);
    }
}
