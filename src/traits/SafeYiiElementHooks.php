<?php

declare(strict_types=1);

namespace anvildev\craftkickback\traits;

/**
 * Craft 6's element base has no Yii `defineRules()` or `beforeValidate()`.
 * Calling either one unconditionally is a fatal. Craft 5 still has both.
 *
 * @author anvildev <dev@anvil.xyz>
 * @since 1.2.0
 */
trait SafeYiiElementHooks
{
    /**
     * @return array<int, mixed>
     */
    protected function safeParentDefineRules(): array
    {
        if (!method_exists(parent::class, 'defineRules')) {
            return [];
        }

        return parent::defineRules();
    }

    protected function safeParentBeforeValidate(): bool
    {
        if (!method_exists(parent::class, 'beforeValidate')) {
            return true;
        }

        return parent::beforeValidate();
    }
}
