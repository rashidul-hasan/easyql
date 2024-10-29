<?php

namespace Rashidul\EasyQL\Http\Controllers;

use ErrorException;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;

class CrudController
{

    public function schema()
    {
        $models = [];
        $tables = [];

        // Get all PHP files in the "app" directory
        $files = glob(app_path('Models/*.php'));

//        dd($files);
        foreach ($files as $file) {
            $className = Str::ucfirst(Str::camel(basename($file, '.php')));
            $model = "App\\Models\\$className";

//            echo $model;

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

//        dd($models);

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

        // Fetch relationships for each model
        /*foreach ($models as $model => $info) {
//            $methods = get_class_methods($model);

//            dd($methods);
            $relationships = [];

            $relationships = [];

            foreach((new ReflectionClass($model))->getMethods(ReflectionMethod::IS_PUBLIC) as $method)
            {
                if ($method->class != get_class($model) ||
                    !empty($method->getParameters()) ||
                    $method->getName() == __FUNCTION__) {
                    continue;
                }

                try {
                    $return = $method->invoke($model);

                    if ($return instanceof Relation) {
                        $relationships[$method->getName()] = [
                            'type' => (new ReflectionClass($return))->getShortName(),
                            'model' => (new ReflectionClass($return->getRelated()))->getName()
                        ];
                    }
                } catch(ErrorException $e) {}
            }
            $models[$model]['relationships'] = $relationships;
            /*foreach ($methods as $method) {
                $reflector = new ReflectionMethod($model, $method);
                $parameters = $reflector->getParameters();
                foreach ($parameters as $parameter) {
                    $class = $parameter->getClass();
                    if ($class && is_subclass_of($class->getName(), 'Illuminate\Database\Eloquent\Relations\Relation')) {
                        $relationships[] = $method;
                    }
                }
            }
            $models[$model]['relationships'] = $relationships;*/
        /*}*/



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

    public function index(Request $request)
    {
        $model = $request->query('model');
        $perPage = $request->query('per_page', 15);
        $select = $request->query('select', null);
        $columnsToGet = $select ? explode(",", $select) : ['*'];

        try {
            $modelClass = 'App\\Models\\' . $model;
            $obj = new $modelClass;
            if ($request->has('page')) {
                $data = $obj->paginate($perPage, $columnsToGet);
            } else {
                $data = $obj->all($columnsToGet);
            }
        } catch (\Throwable $e) {
            dd($e->getMessage());
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
            $modelClass = 'App\\Models\\' . $model;
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
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
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

        $data = null;
        try {
            $modelClass = 'App\\Models\\' . $model;
            $obj = new $modelClass;
            $data = $obj->find($id);

        } catch (\Throwable $e) {
            dd($e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Success',
            'data' => $data
        ]);
    }

    public function store(Request $request)
    {
        //TODO validate request
        $model = $request->query('model');
        $payload = $request->get('data');
        $data = null;
        try {
            $modelClass = 'App\\Models\\' . $model;
            $obj = new $modelClass;

            //if $payload is an array, we will create multiple entry
            if(is_array($payload)) {
                $data = $obj->insert($payload);
            } else {
                $data = $obj->create($payload);
            }
        } catch (\Throwable $e) {
            dd($e->getMessage()); //TODO send proper error msg
        }


        return response()->json([
            'success' => true,
            'message' => 'Success',
            'data' => $data
        ]);
    }

    public function update(Request $request, $id)
    {
        $model = $request->query('model');
        $payload = $request->get('data');
        $data = null;
        try {
            $modelClass = 'App\\Models\\' . $model;
            $obj = new $modelClass;
            $data = $obj->whereId($id)->update($payload);

        } catch (\Throwable $e) {
            dd($e->getMessage());
        }


        return response()->json([
            'success' => true,
            'message' => 'Success',
            'data' => $data
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $model = $request->query('model');
        try {
            $modelClass = 'App\\Models\\' . $model;
            $obj = new $modelClass;
            $data = $obj->find($id);
            $data->delete();

        } catch (\Throwable $e) {
            dd($e->getMessage());
        }


        return response()->json([
            'success' => true,
            'message' => 'Success',
            'data' => $data
        ]);
    }


}
