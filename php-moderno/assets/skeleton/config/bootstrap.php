<?php

declare(strict_types=1);

// Composition root: costruisce il container. Nessun effetto collaterale globale qui,
// così i test possono usarlo senza alterare lo stato di PHPUnit.

use DI\ContainerBuilder;

$builder = new ContainerBuilder();
$builder->useAutowiring(true);   // PHP-DI autocabla solo classi concrete: le interfacce si legano in container.php
$builder->useAttributes(false);
$builder->addDefinitions(__DIR__ . '/container.php');

return $builder->build();
