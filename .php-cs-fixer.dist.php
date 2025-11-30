<?php

$finder = PhpCsFixer\Finder::create()
    ->in([
        __DIR__.'/src',
        __DIR__.'/tests',
    ]);

return new PhpCsFixer\Config()
    ->setRules([
        '@Symfony' => true,
        '@PSR12' => true,
        'global_namespace_import' => [
            'import_classes' => true,
            'import_constants' => false,
            'import_functions' => false,
        ],
        'header_comment' => [
            'header' => <<<EOF
This file is part of the StixxOpenApiCommandBundle package.

(c) Stixx

For the full copyright and license information, please view the LICENSE
file that was distributed with this source code.
EOF
        ],
        'no_superfluous_phpdoc_tags' => [
            'allow_mixed' => true,
            'remove_inheritdoc' => false,
        ],
        'ordered_imports' => true,
        'phpdoc_align' => [
            'align' => 'left'
        ],
        'yoda_style' => false,
    ])
    ->setFinder($finder);
