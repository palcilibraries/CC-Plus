<?php
return [
    'ID' => 'DR_D1',
    'Name' => 'Database Search and Item Usage',
    'MasterReport' => 'DR',
    'Elements' => [
        'Database',
        'Item_ID',
        'Publisher',
        'Publisher_ID',
        'Platform',
        'Proprietary_ID',
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
            'Searches_Automated',
            'Searches_Federated',
            'Searches_Regular',
            'Total_Item_Investigations',
            'Total_Item_Requests',
        ],
        'Platform'
    ]
];
