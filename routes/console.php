<?php

use Illuminate\Support\Facades\Schedule;


Schedule::command('pr:fetch')->everyFifteenMinutes();