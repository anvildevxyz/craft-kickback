<?php

declare(strict_types=1);

namespace anvildev\craftkickback\services;

use Craft;
use craft\base\Component;
use craft\web\View;

/**
 * Renders email templates with user-override support.
 *
 * Resolution order:
 * 1. templates/_kickback/emails/{template}.twig (user override)
 * 2. Plugin's src/templates/emails/{template}.twig (default)
 */
class EmailRenderService extends Component
{
    /**
     * Render an email template with the given variables.
     *
     * @param string $template Template name without extension (e.g. 'approval')
     * @param array<string, mixed> $variables Template variables
     */
    public function render(string $template, array $variables = []): string
    {
        $variables['siteName'] = $variables['siteName'] ?? Craft::$app->getSystemName();

        $view = Craft::$app->getView();
        $oldMode = $view->getTemplateMode();

        try {
            $view->setTemplateMode(View::TEMPLATE_MODE_SITE);
            $exists = fn(string $path) => $view->doesTemplateExist($path);
            $resolved = $this->resolveTemplatePath($template, $exists);

            if ($resolved === "kickback/emails/{$template}") {
                $view->setTemplateMode(View::TEMPLATE_MODE_CP);
            }

            return $view->renderTemplate($resolved, $variables);
        } finally {
            $view->setTemplateMode($oldMode);
        }
    }

    /**
     * Resolve which template path to use.
     *
     * @param string $template Template name (e.g. 'approval')
     * @param callable(string): bool $exists Callable that checks if a template path exists
     */
    protected function resolveTemplatePath(string $template, callable $exists): string
    {
        $override = "_kickback/emails/{$template}";
        if ($exists($override)) {
            return $override;
        }

        return "kickback/emails/{$template}";
    }
}
