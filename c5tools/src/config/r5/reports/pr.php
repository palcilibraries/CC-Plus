<?php
return [
    'ID' => 'PR',
    'Name' => 'Platform Master Report',
    'Elements' => [
        'Platform',
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
        'Data_Type' => [
            'Article',
            'Book',
            'Book_Segment',
            'Database',
            'Dataset',
            'Journal',
            'Multimedia',
            'Newspaper_or_Newsletter',
            'Other',
            'Platform',
            'Report',
            'Repository_Item',
            'Thesis_or_Dissertation',
            'Unspecified'
        ],
        'Metric_Type' => [
            'Searches_Platform',
            'Total_Item_Investigations',
            'Total_Item_Requests',
            'Unique_Item_Investigations',
            'Unique_Item_Requests',
            'Unique_Title_Investigations',
            'Unique_Title_Requests'
        ],
        'Platform'
    ]
];
