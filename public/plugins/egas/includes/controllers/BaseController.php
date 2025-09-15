<?php

namespace App\controllers;

abstract class BaseController
{
    protected function render(string $template, array $data = []): void
    {
        extract($data);
        $templatePath = SYMFONY_WP_PLUGIN_PATH . "templates/$template.php";

        if (file_exists($templatePath)) {
            include $templatePath;
        } else {
            echo "Template not found: $template";
        }
    }

    protected function redirectWithMessage(string $url, string $message, string $type = 'success'): void
    {
        $url = add_query_arg(['message' => urlencode($message), 'type' => $type], $url);
        wp_redirect($url);
        exit;
    }
}
