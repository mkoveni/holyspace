<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
    ->exclude(['vendor', 'var'])
    ->append([__DIR__.'/bin/console', __DIR__.'/bin/phpunit']);

return (new PhpCsFixer\Config())
    ->setCacheFile(__DIR__.'/.php-cs-fixer.cache')
    ->setRules(['@Symfony' => true, 'global_namespace_import' => ['import_classes' => true, 'import_constants' => false, 'import_functions' => false]])
    ->setFinder($finder);
