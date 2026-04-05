<?php
$lines = file(__DIR__.'/storage/logs/laravel.log');
print_r(array_slice($lines, -50));
