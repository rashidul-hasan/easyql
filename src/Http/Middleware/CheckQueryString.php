<?php

namespace Rashidul\EasyQL\Http\Middleware;


use Closure;
use Illuminate\Http\Request;
use Rashidul\EasyQL\Services\SchemaService;

class CheckQueryString
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $model = $request->query('model');
        $schema = SchemaService::getSchema();

        if($model) {
            $models = $schema['models'];
            if(!in_array($model, $models)) {
                return response()->json([
                    'error' => 'Access denied due to invalid query parameter.'
                ], 403);
            }
        }
        
        return $next($request);
    }
}
