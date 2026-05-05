<?php

return [
    'upload_cutoff' => [
        'day' => env('PLAN_VISIT_UPLOAD_CUTOFF_DAY', 'wednesday'),
        'time' => env('PLAN_VISIT_UPLOAD_CUTOFF_TIME', '17:00'),
    ],
];
