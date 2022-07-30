<?php

$header = <<<EOF
This file is part of the Divergence package.

(c) Henry Paradiz <henry.paradiz@gmail.com>

For the full copyright and license information, please view the LICENSE
file that was distributed with this source code.
EOF;

$finder = (new PhpCsFixer\Finder())
    //->exclude('somedir')
    ->in(__DIR__)
;

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR1' => true,
        '@PSR2' => true,
        'array_syntax' => ['syntax' => 'short'],
        'trailing_comma_in_multiline_array' => true,
        'no_trailing_comma_in_singleline_array' => true,
        'ternary_operator_spaces' => true,
        'trim_array_spaces' => true,
        'ordered_imports' => [
            'sortAlgorithm' => 'length'
        ],
        'ordered_class_elements' => true,
        'indentation_type' => true,
        'header_comment' => [
            'header' => $header,
            'comment_type' => 'PHPDoc',
            'location' => 'after_open',
            'separate' => 'none',
        ]
    ])
    ->setFinder($finder)   
;