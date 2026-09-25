<?php

declare(strict_types=1);

return new PhpCsFixer\Config()
    ->setRiskyAllowed(true)
    ->setRules([
        '@PER-CS3x0' => true,
        '@PER-CS3x0:risky' => true,
        '@PHP8x4Migration' => true,
        'declare_strict_types' => true,
        'strict_comparison' => true,
        'strict_param' => true,
        'no_unused_imports' => true,
    ])
    ->setFinder(
        PhpCsFixer\Finder::create()
            ->in([__DIR__ . '/bin', __DIR__ . '/config', __DIR__ . '/migrations', __DIR__ . '/src', __DIR__ . '/tests'])
            ->append([__DIR__ . '/bootstrap.php', __DIR__ . '/web.php', __FILE__]),
    );
