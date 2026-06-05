<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Developer Tools
    |--------------------------------------------------------------------------
    |
    | Master switch for the in-app /dev tooling exposed to developer/super_admin
    | users. Always false in production unless explicitly enabled.
    |
    */

    'enabled' => (bool) env('DEV_TOOLS_ENABLED', false),

    'test_runner_enabled' => (bool) env('DEV_TEST_RUNNER_ENABLED', false),

];
