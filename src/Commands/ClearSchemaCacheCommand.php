<?php

namespace Rashidul\EasyQL\Commands;

use Illuminate\Console\Command;
use Rashidul\EasyQL\Util;
use Illuminate\Support\Facades\File;

class ClearSchemaCacheCommand extends Command
{
    public $signature = 'easyql:clear-cache';

    public $description = 'Clear schema cache from bootstrap/cache';

    public function handle()
    {
        $file  = base_path('bootstrap/cache/' . Util::CACHE_FILENAME);
        if (File::exists($file)) {
            File::delete($file);
            $this->info('Schema cache cleared!');
        } else {
            $this->error('Something went wrong');
        }
    }
}