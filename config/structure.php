<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Structure Configuration
    |--------------------------------------------------------------------------
    | teams_root — root path prefix used when generating team_group_path values.
    | Should match the prefix already stored in users.team_group_path.
    */
    'teams_root' => env('STRUCTURE_TEAMS_ROOT', '/teams'),
];
