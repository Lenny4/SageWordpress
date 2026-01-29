<?php

namespace App\services;

use App\Sage;
use Twig\Environment;
use Twig\Extension\DebugExtension;
use Twig\Loader\FilesystemLoader;

class TwigService
{
    private static ?TwigService $instance = null;
    public Environment $twig;
    public string $dir;
    public bool $register = false;

    private function __construct()
    {
        $templatesDir = __DIR__ . '/../../templates';
        $filesystemLoader = new FilesystemLoader($templatesDir);
        $twigOptions = [
            'debug' => WP_DEBUG,
        ];
        if (!WP_DEBUG) {
            $twigOptions['cache'] = $templatesDir . '/cache';
        }

        $this->twig = new Environment($filesystemLoader, $twigOptions);
        if (WP_DEBUG) {
            // https://twig.symfony.com/doc/3.x/functions/dump.html
            $this->twig->addExtension(new DebugExtension());
        }
        $this->dir = dirname((string) Sage::getInstance()->file);
//        $this->twig->addExtension(new IntlExtension());
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function render(string $name, array $context = []): string
    {
        return $this->twig->render($name, $context);
    }
}
