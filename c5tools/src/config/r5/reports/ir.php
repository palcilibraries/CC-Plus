<?php
return [
    'ID' => 'IR',
    'Name' => 'Item Master Report',
    'Elements' => [
        'Item',
        'Item_ID',
        'Item_Contributors',
        'Item_Dates',
        'Item_Attributes',
        'Publisher',
        'Publisher_ID',
        'Platform',
        'Item_Parent',
        'Item_Component',
        'DOI',
        'Proprietary_ID',
        'ISBN',
        'Print_ISSN',
        'Online_ISSN',
        'URI',
        'Parent_Title',
        'Parent_Authors',
        'Parent_Publication_Date',
        'Parent_Article_Version',
        'Parent_Data_Type',
        'Parent_DOI',
        'Parent_Proprietary_ID',
        'Parent_ISBN',
        'Parent_Print_ISSN',
        'Parent_Online_ISSN',
        'Parent_URI',
        'Component_Title',
        'Component_Authors',
        'Component_Publication_Date',
        'Component_Data_Type',
        'Component_DOI',
        'Component_Proprietary_ID',
        'Component_ISBN',
        'Component_Print_ISSN',
        'Component_Online_ISSN',
        'Component_URI',
        'Metric_Type',
        'Reporting_Period_Total',
        'Performance'
    ],
    'Attributes' => [
        'Attributes_To_Show' => [
            'Institution_Name',
            'Customer_ID',
            'Authors',
            'Publication_Date',
            'Article_Version',
            'Data_Type',
            'YOP',
            'Access_Type',
            'Access_Method'
        ],
        'Exclude_Monthly_Details',
        'Granularity',
        'Include_Component_Details',
        'Include_Parent_Details'
    ],
    'Filters' => [
        'Access_Method' => [
            'Regular',
            'TDM'
        ],
        'Access_Type' => [
            'Controlled',
            'OA_Gold',
            'Other_Free_To_Read'
        ],
        'Begin_Date',
        'End_Date',
        'Data_Type' => [
            'Article',
            'Book',
            'Book_Segment',
            'Dataset',
            'Journal',
            'Multimedia',
            'Newspaper_or_Newsletter',
            'Other',
            'Report',
            'Repository_Item',
            'Thesis_or_Dissertation',
            'Unspecified'
        ],
        'Parent_Data_Type' => [
            // TOOD: Which Data_Types should be allowed for the Parent?
            'Article',
            'Book',
            'Book_Segment',
            'Dataset',
            'Journal',
            'Multimedia',
            'Newspaper_or_Newsletter',
            'Other',
            'Report',
            'Repository_Item',
            'Thesis_or_Dissertation'
        ],
        'Component_Data_Type' => [
            // TOOD: Which Data_Types should be allowed for the Component?
            'Article',
            'Book',
            'Book_Segment',
            'Dataset',
            'Journal',
            'Multimedia',
            'Newspaper_or_Newsletter',
            'Other',
            'Report',
            'Repository_Item',
            'Thesis_or_Dissertation'
        ],
        'Item_Contributor',
        'Item_ID',
        'Metric_Type' => [
            'Total_Item_Investigations',
            'Total_Item_Requests',
            'Unique_Item_Investigations',
            'Unique_Item_Requests',
            'Limit_Exceeded',
            'No_License'
        ],
        'Platform',
        'YOP'
    ]
];
