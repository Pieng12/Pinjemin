<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('bookings:expire-quotes')->everyMinute()->withoutOverlapping();
Schedule::command('rentals:maintain-evidence')->everyMinute()->withoutOverlapping();
Schedule::command('rentals:maintain-evidence --prune')->daily()->withoutOverlapping();
