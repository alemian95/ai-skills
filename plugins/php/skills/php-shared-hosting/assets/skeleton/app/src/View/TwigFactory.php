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
            // Without SSH the cache cannot be cleared on every deploy: Twig recompiles modified templates
            // at the cost of one file date check per template.
            'auto_reload' => true,
            'autoescape' => 'html',
            'strict_variables' => $settings->debug,
            'debug' => $settings->debug,
        ]);
        $twig->getExtension(CoreExtension::class)->setTimezone($settings->timezone); // date filter: site time zone
        $twig->addExtension(new AppExtension($settings->publicDir));

        return $twig;
    }
}
