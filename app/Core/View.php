<?php

declare(strict_types=1);

namespace App\Core;

final class View
{
    public static function resolve(string $template): string
    {
        $base = BASE_PATH . '/app/Views';
        $name = $template;
        $file = $base . '/' . str_replace('.', '/', $name) . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("View not found: {$template}");
        }
        return $file;
    }

    /** Render a template (optionally inside a layout) and return HTML. */
    public static function render(string $template, array $data = [], ?string $layout = 'layouts.app'): string
    {
        $content = self::partial($template, $data);
        if ($layout === null) {
            return $content;
        }
        return self::partial($layout, array_merge($data, ['content' => $content]));
    }

    public static function partial(string $template, array $data = []): string
    {
        $__file = self::resolve($template);
        extract($data, EXTR_SKIP);
        ob_start();
        try {
            include $__file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }
}
