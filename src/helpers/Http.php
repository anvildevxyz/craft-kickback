<?php

declare(strict_types=1);

namespace anvildev\craftkickback\helpers;

/**
 * Throws the HTTP exception the running Craft version actually renders.
 *
 * Craft 6 dispatches site routes through a fallback that does not catch Yii
 * HTTP exceptions, so a Yii 404 becomes a Laravel 500. Craft 5 still expects
 * the Yii exception.
 *
 * @author anvildev <dev@anvil.xyz>
 * @since 1.2.0
 */
class Http
{
    public static function abortNotFound(string $message = ''): never
    {
        if (class_exists(\CraftCms\Cms\Element\Queries\ElementQuery::class)) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException($message);
        }

        throw new \yii\web\NotFoundHttpException($message);
    }

    public static function abort(int $status, string $message = ''): never
    {
        if ($status === 404) {
            self::abortNotFound($message);
        }

        if (class_exists(\CraftCms\Cms\Element\Queries\ElementQuery::class)) {
            throw new \Symfony\Component\HttpKernel\Exception\HttpException($status, $message);
        }

        throw new \yii\web\HttpException($status, $message);
    }
}
