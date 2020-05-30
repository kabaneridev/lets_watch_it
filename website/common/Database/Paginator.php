<?php namespace Common\Database;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;

class Paginator
{
    /**
     * @var Builder
     */
    private $query;

    /**
     * @var Model
     */
    private $model;

    /**
     * @var string
     */
    private $defaultOrderColumn = 'updated_at';

    /**
     * @var string
     */
    private $defaultOrderDirection = 'desc';

    /**
     * @var string
     */
    public $searchColumn = 'name';

    /**
     * @var array
     */
    public $filterColumns = [];

    /**
     * @var Closure
     */
    public $searchCallback;

    /**
     * @var array
     */
    private $params;

    /**
     * @param Model $model
     * @param array $params
     */
    public function __construct(Model $model, array $params)
    {
        $this->model = $model;
        $this->params = $this->toCamelCase($params);
        $this->query = $model->newQuery();
    }

    /**
     * @return LengthAwarePaginator
     */
    public function paginate()
    {
        $with = array_filter(explode(',', $this->param('with', '')));
        $withCount = array_filter(explode(',', $this->param('withCount', '')));
        $searchTerm = $this->param('query');
        $order = $this->getOrder();
        $perPage = $this->param('perPage', 15);
        $page = (int) $this->param('page', 1);

        // load specified relations and counts
        if ( ! empty($with)) $this->query->with($with);
        if ( ! empty($withCount)) $this->query->withCount($withCount);

        // search
        if ($searchTerm) {
            if ($this->searchCallback) {
                call_user_func($this->searchCallback, $this->query, $searchTerm);
            } else {
                $this->query->where($this->searchColumn, 'like', "$searchTerm%");
            }
        }

        $this->applyFilters();

        // order
        $this->query->orderBy($order['col'], $order['dir']);

        // paginate
        return new LengthAwarePaginator(
            with(clone $this->query)->skip(($page - 1) * $perPage)->take($perPage)->get(),
            $this->query->count(),
            $perPage,
            $page
        );
    }

    /**
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    public function param($name, $default = null)
    {
        return Arr::get($this->params, camel_case($name)) ?: $default;
    }

    /**
     * @return Builder
     */
    public function query() {
        return $this->query;
    }

    /**
     * Load specified relation counts with paginator items.
     *
     * @param mixed $relations
     * @return $this
     */
    public function withCount($relations)
    {
        $this->query->withCount($relations);
        return $this;
    }

    /**
     * Load specified relations of paginated items.
     *
     * @param mixed $relations
     * @return $this
     */
    public function with($relations)
    {
        $this->query->with($relations);
        return $this;
    }

    /**
     * @param $column
     * @param null $operator
     * @param null $value
     * @param string $boolean
     * @return $this
     */
    public function where($column, $operator = null, $value = null, $boolean = 'and')
    {
        $this->query->where($column, $operator, $value, $boolean);
        return $this;
    }

    /**
     * Set default order column and direction for paginator.
     *
     * @param $column
     * @param string $direction
     * @return $this
     */
    public function setDefaultOrderColumns($column, $direction = 'desc')
    {
        $this->defaultOrderColumn = $column;
        $this->defaultOrderDirection = $direction;
        return $this;
    }

    /**
     * Extract order for paginator query from specified params.
     *
     * @return array
     */
    private function getOrder() {
        //order provided as single string: "column|direction"
        if ($specifiedOrder = $this->param('order')) {
            $parts = preg_split("(\||:)", $specifiedOrder);
            $orderCol = Arr::get($parts, 0, $this->defaultOrderColumn);
            $orderDir = Arr::get($parts, 1, $this->defaultOrderDirection);
        } else {
            $orderCol = $this->param('orderBy', $this->defaultOrderColumn);
            $orderDir = $this->param('orderDir', $this->defaultOrderDirection);
        }

        return ['dir' => $orderDir, 'col' => $orderCol];
    }

    private function toCamelCase($params)
    {
        return collect($params)->keyBy(function($value, $key) {
            return camel_case($key);
        })->toArray();
    }

    private function applyFilters()
    {
        foreach ($this->filterColumns as $column => $callback) {
            $column = is_int($column) ? $callback : $column;
            $column = camel_case($column);
            if (isset($this->params[$column])) {
                $value = $this->params[$column];
                $column = snake_case($column);

                // user specified callback
                if (is_callable($callback)) {
                    $callback($this->query);

                // boolean filter
                } else if ($value === 'false' || $value === 'true') {
                    $this->applyBooleanFilter($column, $value);

                // filter by between date
                } else if (str_contains($column, '_at') && str_contains($value, ':')) {
                    $this->query()->whereBetween($column, explode(':', $value));

                // filter by specified column value
                } else {
                    $this->query()->where($column, $value);
                }
            }
        }
    }

    /**
     * @param string $column
     * @param string $value
     */
    private function applyBooleanFilter($column, $value)
    {
        // cast "true" or "false" to boolean
        $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
        $casts = $this->model->getCasts();

        // column is a simple boolean type
        if (Arr::get($casts, $column) === 'boolean') {
            $this->query()->where($column, $value);

            // column has actual value, test whether it's null or not by default
        } else {
            if ($value) {
                $this->query()->whereNotNull($column);
            } else {
                $this->query()->whereNull($column);
            }
        }
    }
}
