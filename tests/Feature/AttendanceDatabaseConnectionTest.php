<?php

use Illuminate\\Support\\Facades\\DB;

test('attendance tests use the dedicated test database', function () {
    $database = DB::selectOne('SELECT DATABASE() AS database_name');

    expect($database->database_name)->toBe('humexa_test');
});
