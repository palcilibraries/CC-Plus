<?php
return [
    'ID' => 'DR',
    'Name' => 'Database Master Report',
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
    'Attributes' => [
        'Attributes_To_Show' => [
            'Institution_Name',
            'Customer_ID',
            'Data_Type',
            'Access_Method'
        ],
        'Exclude_Monthly_Details',
        'Granularity'
    ],
    'Filters' => [
        'Access_Method' => [
            'Regular',
            'TDM'
        ],
        'Begin_Date',
        'End_Date',
        'Database',
        'Data_Type' => [
            'Book',
            'Database',
            'Journal',
            'Multimedia',
            'Newspaper_or_Newsletter',
            'Other',
            'Report',
            'Thesis_or_Dissertation',
            'Unspecified'
        ],
        'Metric_Type' => [
            'Searches_Automated',
            'Searches_Federated',
            'Searches_Regular',
            'Total_Item_Investigations',
            'Total_Item_Requests',
            'Unique_Item_Investigations',
            'Unique_Item_Requests',
            'Unique_Title_Investigations',
            'Unique_Title_Requests',
            'Limit_Exceeded',
            'No_License'
        ],
        'Platform'
    ]
];
