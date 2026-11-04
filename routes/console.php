<?php

use Illuminate\Support\Facades\Schedule;

$timezone = (string) config('bicho.timezone');

Schedule::command('draws:fetch')->dailyAt('06:30')->timezone($timezone)->withoutOverlapping();
Schedule::command('draws:fetch')->dailyAt('22:30')->timezone($timezone)->withoutOverlapping();
Schedule::command('rag:train')->dailyAt('06:50')->timezone($timezone)->withoutOverlapping();
Schedule::command('posts:generate')->dailyAt('07:00')->timezone($timezone)->withoutOverlapping(30);
