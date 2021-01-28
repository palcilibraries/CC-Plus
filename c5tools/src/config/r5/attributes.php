<?php
return [
    'Attributes_To_Show' => [
        'multi' => true
    ],
    'Exclude_Monthly_Details' => [
        'json' => false,
        'values' => [
            'False',
            'True'
        ],
        'default' => 'False'
    ],
    'Granularity' => [
        'tabular' => false,
        'values' => [
            'Month',
            'Totals'
        ],
        'default' => 'Month'
    ],
    'Include_Component_Details' => [
        'values' => [
            'False',
            'True'
        ],
        'default' => 'False'
    ],
    'Include_Parent_Details' => [
        'values' => [
            'False',
            'True'
        ],
        'default' => 'False'
    ]
];
