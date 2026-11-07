<?php

declare(strict_types=1);

$finder = (new PhpCsFixer\Finder())
    ->in([__DIR__ . '/src', __DIR__ . '/tests'])
    ->exclude('var');

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony' => true,
        '@PSR12' => true,
        'declare_strict_types' => true,
        'strict_param' => true,
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'no_unused_imports' => true,
        'global_namespace_import' => ['import_classes' => true, 'import_functions' => false, 'import_constants' => false],
        'final_class' => true,
        'single_line_throw' => false,
        'yoda_style' => false,
    ])
    ->setFinder($finder);
