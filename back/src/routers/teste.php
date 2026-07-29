<?php

use App\Controllers\ControllerExample;
use Lumynus\Framework\Route;

Route::get(['teste/{mensage}[string]?[string va]', 'test?[string a]'], ControllerExample::class, 'index');
