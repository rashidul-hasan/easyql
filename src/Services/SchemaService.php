<?php

namespace Rashidul\EasyQL\Services;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use ReflectionClass;
use ReflectionMethod;
use Illuminate\Support\Facades\Schema;

class SchemaService
{
    const CACHE_FILENAME = 'easyql.php';

    public static function cacheSchema() {
        $files = glob(config('easyql.model_path'));
        $namespace = config('easyql.model_namespace');
        $restricted_models = config('easyql.restricted');

        $models = [];
        $fillables = [];
        $tables = [];
        // [
        //     'Model' => [
        //         'one',
        //         'two'
        //     ]
        // ]

        foreach ($files as $file) {
            $className = Str::ucfirst(Str::camel(basename($file, '.php')));
            $model = "{$namespace}\\$className";

            if (class_exists($model)) {
                $modelObj = new $model;
                $table = $modelObj->getTable();
                // $relations = $this->getRelationsOfModel($modelObj);
                // $models[$model] = [
                //     'model' => $className,
                //     'table' => $modelObj->getTable(),
                //     'columns' => [],
                //     'relationships' => $relations,
                // ];
                $models[] = $className;
                // $tables[$className] = $modelObj->getGuarded();
                $columns = $modelObj->getFillable();
                if(empty($columns)) {
                    //if there is not fillable property on the model, get columns list from database
                    $columns = Schema::getColumnListing($table);

                    //exclude id, created_at, updated_at from the list
                    $columns = array_diff($columns, ['id', 'created_at', 'updated_at']);

                }

                $tables[$className] = $columns;
            }
        }

        // Fetch columns for each table
        // foreach ($tables as $table) {
        //     $columns = Schema::getColumnListing($table);
        //     $models = array_map(function ($model) use ($table, $columns) {
        //         if ($model['table'] === $table) {
        //             $model['columns'] = $columns;
        //         }
        //         return $model;
        //     }, $models);
        // }

        // dd($tables);
        $models = array_diff($models, $restricted_models);

        self::createFileInCache(self::CACHE_FILENAME, [
            'models' => $models,
            'schema' => $tables
        ]);

        return [
            'models' => $models,
            'schema' => $tables
        ];
    }

    // private function getRelationsOfModel(mixed $modelObj)
    // {
    //     $relationships = [];

    //     foreach((new ReflectionClass($modelObj))->getMethods(ReflectionMethod::IS_PUBLIC) as $method)
    //     {
    //         if ($method->class != get_class($modelObj) ||
    //             !empty($method->getParameters()) ||
    //             $method->getName() == __FUNCTION__) {
    //             continue;
    //         }

    //         try {
    //             $return = $method->invoke($modelObj);

    //             if ($return instanceof Relation) {
    //                 $relationships[$method->getName()] = [
    //                     'type' => (new ReflectionClass($return))->getShortName(),
    //                     'model' => (new ReflectionClass($return->getRelated()))->getName()
    //                 ];
    //             }
    //         } catch(ErrorException $e) {}
    //     }

    //     return $relationships;
    // }

    public static function getSchema() {
        // Define the path to the file inside the bootstrap/cache folder
        $filePath = base_path('bootstrap/cache/' . self::CACHE_FILENAME);

        // Check if the file exists
        if (file_exists($filePath)) {
            // Include the file and return the array stored in the file
            return include($filePath);
        }

        return self::cacheSchema();
    }

    private static function createFileInCache($filename, $data)
    {
        // Define the path to the file inside the bootstrap/cache folder
        $filePath = base_path('bootstrap/cache/' . $filename);

        $content = '<?php return ' . var_export($data, true) . ';';

        // Write the content to the file
        File::put($filePath, $content);
    }
}
