<?php

$finder = new PhpCsFixer\Finder()
    ->in(__DIR__)
    ->exclude("var")
    ->exclude("vendor")
    ->exclude("node_modules");

return new PhpCsFixer\Config()
    ->setRules([
        "@Symfony" => true,
        "yoda_style" => false,
    ])
    ->setParallelConfig(
        PhpCsFixer\Runner\Parallel\ParallelConfigFactory::detect(),
    )
    ->setFinder($finder);
