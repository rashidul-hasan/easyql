<?php

namespace Rashidul\EasyQL\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ResourceCreatedEvent
{
    use Dispatchable, SerializesModels;

    public $modelName;

    public $model;
    /**
     * Create a new event instance.
     *
     * @param mixed $data
     * @return void
     */
    public function __construct($modelName, $model)
    {
        $this->modelName = $modelName;
        $this->model = $model;
    }
}
