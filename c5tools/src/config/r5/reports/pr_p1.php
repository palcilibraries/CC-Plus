<?php
return [
    'ID' => 'PR_P1',
    'Name' => 'Platform Usage',
    'MasterReport' => 'PR',
    'Elements' => [
        'Platform',
        'Metric_Type',
        'Reporting_Period_Total',
        'Performance'
    ],
    'Filters' => [
        'Access_Method' => [
            'Regular'
        ],
        'Begin_Date',
        'End_Date',
        'Metric_Type' => [
            'Searches_Platform',
            'Total_Item_Requests',
            'Unique_Item_Requests',
            'Unique_Title_Requests'
        ],
        'Platform'
    ]
];
