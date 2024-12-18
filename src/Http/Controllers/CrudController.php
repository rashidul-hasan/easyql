<?php

namespace Rashidul\EasyQL\Http\Controllers;

use ErrorException;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Rashidul\EasyQL\Util;
use ReflectionClass;
use ReflectionMethod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Rashidul\EasyQL\Events\ResourceCreatedEvent;
use Rashidul\EasyQL\Events\ResourceDestroyedEvent;
use Rashidul\EasyQL\Events\ResourceUpdatedEvent;
use Rashidul\EasyQL\Http\Requests\CreateRequest;
use Rashidul\EasyQL\Http\Requests\ListParamsRequest;

class CrudController
{

    public function schema()
    {
        if(!config('app.debug')) {
            //disable this route on production environment
            abort(404, "Not found");
        }

        $models = [];
        $tables = [];

        $files = glob(config('easyql.model_path'));

        foreach ($files as $file) {
            $className = Str::ucfirst(Str::camel(basename($file, '.php')));
            $model = "{$this->getModelNamespace()}\\$className";

            if (class_exists($model)) {
                $modelObj = new $model;

                $relations = $this->getRelationsOfModel($modelObj);
                $models[$model] = [
                    'model' => $className,
                    'table' => $modelObj->getTable(),
                    'columns' => [],
                    'relationships' => $relations,
                ];
                $tables[] = $modelObj->getTable();
            }
        }

        // Fetch columns for each table
        foreach ($tables as $table) {
            $columns = Schema::getColumnListing($table);
            $models = array_map(function ($model) use ($table, $columns) {
                if ($model['table'] === $table) {
                    $model['columns'] = $columns;
                }
                return $model;
            }, $models);
        }

        return $models;
    }


    private function getRelationsOfModel(mixed $modelObj)
    {
        $relationships = [];

        foreach((new ReflectionClass($modelObj))->getMethods(ReflectionMethod::IS_PUBLIC) as $method)
        {
            if ($method->class != get_class($modelObj) ||
                !empty($method->getParameters()) ||
                $method->getName() == __FUNCTION__) {
                continue;
            }

            try {
                $return = $method->invoke($modelObj);

                if ($return instanceof Relation) {
                    $relationships[$method->getName()] = [
                        'type' => (new ReflectionClass($return))->getShortName(),
                        'model' => (new ReflectionClass($return->getRelated()))->getName()
                    ];
                }
            } catch(ErrorException $e) {}
        }

        return $relationships;
    }

    public function index(ListParamsRequest $request)
    {
        $model = $request->query('model');
        $this->checkGate($model, 'index');
        $perPage = $request->query('per_page', 15);
        $select = $request->query('select', null);
        $columnsToGet = $select ? explode(",", $select) : ['*'];

        //filter
        $filters = $request->query('filter', []);
        $filterType = $request->query('filter_type', 'and');


        try {
            $modelClass = "{$this->getModelNamespace()}\\$model";

            $obj = new $modelClass;
            $q = $obj->query();
            $q = $this->applyFilter($q, $filters, $filterType);

            if ($request->has('page')) {
                $data = $q->paginate($perPage, $columnsToGet);
            } else {
                $data = $q->get($columnsToGet);
            }
        } catch (\Throwable $e) {
            return $this->returnErrorResponse($e);
        }

        return response()->json([
            'success' => true,
            'message' => 'Success',
            'data' => $data
        ]);
    }

    public function findWhere(Request $request)
    {
        $model = $request->query('model');
        $perPage = $request->query('per_page', 15);

        $where = $request->get('where');
        $with = $request->get('with');
        $select = $request->get('select');

        $data = null;
        try {
            $modelClass = "{$this->getModelNamespace()}\\$model";

            $obj = new $modelClass;
            $q = $obj->query();
            if (is_array($where)) {
                foreach ($where as $whereClause) {
                    if (count($whereClause) === 2) {
                        $q->where($whereClause[0], $whereClause[1]);
                    }
                    if (count($whereClause) === 3) {
                        $q->where($whereClause[0], $whereClause[1], $whereClause[2]);
                    }
                }
            }

            //load relationships
            if (is_array($with)) {
                foreach ($with as $withStr) {
                    $q->with($withStr);
                }
            }

            $data = is_array($select) ? $q->get($select) : $q->get();
            if ($request->has('page')) {
                $data = is_array($select) ? $obj->paginate($perPage, $select) : $obj->paginate($perPage);
            } else {
                $data = is_array($select) ? $q->get($select) : $q->get();
            }
        } catch (\Throwable $e) {
            return $this->returnErrorResponse($e);
        }

        return response()->json([
            'success' => true,
            'message' => 'Success',
            'data' => $data
        ]);
    }

    public function show(Request $request, $id)
    {
        $model = $request->query('model');
        $this->checkGate($model, 'show');
        $data = null;
        try {
            $modelClass = "{$this->getModelNamespace()}\\$model";

            $obj = new $modelClass;
            $data = $obj->find($id);

        } catch (\Throwable $e) {
            return $this->returnErrorResponse($e);
        }

        return response()->json([
            'success' => true,
            'message' => 'Success',
            'data' => $data
        ]);
    }

    public function store(CreateRequest $request)
    {
        //TODO validate request
        $model = $request->query('model');
        $this->checkGate($model, 'store');
        $payload = $request->get('data');
        $data = null;
        try {
            $modelClass = "{$this->getModelNamespace()}\\$model";

            $obj = new $modelClass;

            //if $payload has nested array, we will create multiple entry
            if(Util::isBulkInsert($payload)) {
                foreach ($payload as $item) {
                    $obj = new $modelClass;
                    $obj->fill($item);
                    $obj->save();

                    //sync many to many relations
                    if(array_key_exists('_sync', $payload)) {
                        foreach($payload['_sync'] as $relation => $values) {
                            $obj->{$relation}()->sync($values);
                        }
                    }
                }
            } else {
                $data = $obj->fill($payload);
                $obj->save();

                //sync many to many relations
                if(array_key_exists('_sync', $payload)) {
                    foreach($payload['_sync'] as $relation => $values) {
                        $obj->{$relation}()->sync($values);
                    }
                }
            }

            
        } catch (\Throwable $e) {
            return $this->returnErrorResponse($e);
        }

        Event::dispatch(new ResourceCreatedEvent($model, $data));

        return response()->json([
            'success' => true,
            'message' => 'Success',
            'data' => $data
        ]);
    }

    public function update(Request $request, $id)
    {
        $model = $request->query('model');
        $this->checkGate($model, 'update');
        $payload = $request->get('data');
        $data = null;
        $keysToExclude = ['_sync'];
        $dataToInsert = array_diff_key($payload, array_flip($keysToExclude));
        try {
            $modelClass = "{$this->getModelNamespace()}\\$model";

            $obj = $modelClass::findOrFail($id);
            $data = $obj->fill($dataToInsert);
            $obj->save();

            //sync many to many relations
            if(array_key_exists('_sync', $payload)) {
                foreach($payload['_sync'] as $relation => $values) {
                    $obj->{$relation}()->sync($values);
                }
            }
        } catch (\Throwable $e) {
            return $this->returnErrorResponse($e);
        }

        Event::dispatch(new ResourceUpdatedEvent($model, $data));

        return response()->json([
            'success' => true,
            'message' => 'Success',
            'data' => $data
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $model = $request->query('model');
        $this->checkGate($model, 'destroy');
        try {
            $modelClass = "{$this->getModelNamespace()}\\$model";
            
            $obj = new $modelClass;
            $data = $obj->findOrFail($id);
            $data->delete();

        } catch (\Throwable $e) {
            return $this->returnErrorResponse($e);
        }

        Event::dispatch(new ResourceDestroyedEvent($model, $data));

        return response()->json([
            'success' => true,
            'message' => 'Success',
            'data' => $data
        ]);
    }

    private function applyFilter(Builder $query, array $filters, $filterType = 'and')
    {
        // AND (default behavior):
        // /customers?filter[name][like]=john&filter[email][like]=john

        // OR: /customers?filter[name][like]=john&filter[email][like]=john&filter_type=or          
        $operatorMapping = [
            'eq' => '=',
            'ne' => '!=',
            'gt' => '>',
            'lt' => '<',
            'gte' => '>=',
            'lte' => '<=',
            'like' => 'LIKE',
        ];

        $method = strtolower($filterType) === 'or' ? 'orWhere' : 'where';

        foreach ($filters as $column => $filter) {
            foreach ($filter as $operator => $value) {
                // Ignore the filter if the value is null or an empty string
                if ($value === null || $value === '') {
                    continue;
                }

                $sqlOperator = $operatorMapping[$operator] ?? '=';

                if ($sqlOperator === 'LIKE') {
                    $value = "%{$value}%";
                }

                $query->{$method}($column, $sqlOperator, $value);
            }
        }

        return $query;
    }

    private function returnErrorResponse($e)
    {
        if(config('app.debug')) {
            //if debug mode then dump the full stack trace to make debugging easier
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'stack_trace' => $e->getTraceAsString(), 
            ], 500);
        }

        return response()->json([
            'success' => false,
            'message' => 'Something went wrong'
        ], 500);
    }

    private function getModelNamespace()
    {
        return config('easyql.model_namespace');
    }
    
    private function checkGate($model, $action)
    {
        $gate = "{$model}.{$action}";
        if(Gate::has($gate) && Gate::denies($gate)) {
            abort(403);
        }
    }
}
