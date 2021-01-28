<?php
return [
    'Institution' => [
        'ISNI' => [
            'multi' => true,
            'check' => 'checkedIsniIdentifier'
        ],
        'ISIL' => [
            'multi' => true,
            'check' => 'checkedIsilIdentifier'
        ],
        'OCLC' => [
            'multi' => true,
            'check' => 'checkedOclcIdentifier'
        ],
        'Proprietary' => [
            'multi' => true,
            'check' => 'checkedProprietaryIdentifier'
        ]
    ],
    'Publisher' => [
        'ISNI' => [
            'multi' => true,
            'check' => 'checkedIsniIdentifier'
        ],
        'Proprietary' => [
            'multi' => true,
            'check' => 'checkedProprietaryIdentifier'
        ]
    ],
    'Item' => [
        'DOI' => [
            'check' => 'checkedDoiIdentifier'
        ],
        'Proprietary' => [
            'tabular' => false,
            'check' => 'checkedProprietaryIdentifier'
        ],
        'Proprietary_ID' => [
            'json' => false,
            'check' => 'checkedProprietaryIdentifier'
        ],
        'ISBN' => [
            'check' => 'checkedIsbnIdentifier'
        ],
        'Print_ISSN' => [
            'check' => 'checkedIssnIdentifier'
        ],
        'Online_ISSN' => [
            'check' => 'checkedIssnIdentifier'
        ],
        'Linking_ISSN' => [
            'check' => 'checkedIssnIdentifider'
        ],
        'URI' => [
            'check' => 'checkedUriIdentifier'
        ]
    ],
    'Author' => [
        'ISNI' => [
            'check' => 'checkedIsniIdentifier'
        ],
        'ORCID' => [
            'check' => 'checkedOrcidIdentifier'
        ]
    ]
];
