<?php

return [
    'target_php_version' => '8.4',
    'directory_list' => [
        'src',
        'vendor/filp/whoops/src',
        'vendor/guzzlehttp/psr7/src',
        'vendor/psr/http-message/src',
        'vendor/psr/http-server-handler/src',
        'vendor/twig/twig/src',
    ],
    'exclude_analysis_directory_list' => [
        'vendor',
    ],
    'exclude_file_regex' => '@^vendor/.*/(?:tests?|Tests?)/@',
    'suppress_issue_types' => [
        'PhanUnreferencedUseNormal',
    ],
];
