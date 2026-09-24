<?php

declare(strict_types=1);

namespace App\View;

use App\Config\Settings;
use Twig\Environment;
use Twig\Extension\CoreExtension;
use Twig\Loader\FilesystemLoader;

final class TwigFactory
{
    public static function create(Settings $settings): Environment
    {
        $twig = new Environment(new FilesystemLoader($settings->appDir . '/templates'), [
            'cache' => $settings->varDir . '/cache/twig',
            // Senza SSH non si può svuotare la cache a ogni deploy: Twig ricompila i template modificati
            // al costo di un controllo della data del file per template.
            'auto_reload' => true,
            'autoescape' => 'html',
            'strict_variables' => $settings->debug,
            'debug' => $settings->debug,
        ]);
        $twig->getExtension(CoreExtension::class)->setTimezone($settings->timezone); // filtro date: fuso del sito
        $twig->addExtension(new AppExtension($settings->publicDir));

        return $twig;
    }
}
