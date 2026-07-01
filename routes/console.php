<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('feed:about', function (): void {
    $this->info('Guised Up Real Connections Feed API');
})->purpose('Print feed service information');
