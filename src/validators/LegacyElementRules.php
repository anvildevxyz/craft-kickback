<?php

declare(strict_types=1);

namespace anvildev\craftkickback\validators;

use Closure;
use CraftCms\Cms\Element\Validation\ElementRules;
use CraftCms\Yii2Adapter\Validation\LegacyYiiRules;
use ReflectionClass;

/**
 * Craft 6-only. Runs an element's Yii `beforeValidate()` and `defineRules()`
 * inside the native ruleset.
 *
 * Craft 6 never calls `beforeValidate()`, so title copies that used to live
 * there never happen and the native title rule fails the save. The adapter's
 * own LegacyElementRules also drops `required` (the merged closure is not an
 * implicit rule) and does not copy Yii errors back onto the element.
 *
 * @author anvildev <dev@anvil.xyz>
 * @since 1.2.0
 */
class LegacyElementRules extends ElementRules
{
    public function prepareForValidation(): void
    {
        $subject = $this->subject;
        if (method_exists($subject, 'beforeValidate')) {
            $subject->beforeValidate();
        }

        parent::prepareForValidation();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();

        $reflectionClass = new ReflectionClass($this->subject);
        if (!$reflectionClass->hasMethod('defineRules')) {
            return $rules;
        }

        $method = $reflectionClass->getMethod('defineRules');
        $yiiRules = $method->invoke($this->subject);
        $yiiRules = self::applyWhenConditions($yiiRules, $this->subject);

        $rules = LegacyYiiRules::mergeWildcardRules(
            rules: $rules,
            target: $this->subject,
            yiiRules: $yiiRules,
            copyErrors: true,
        );

        $target = $this->subject;
        $innerClosure = $rules['*'][0];
        $rules['*'][0] = new LegacyYiiRule(function(string $attribute, mixed $value, Closure $fail) use ($innerClosure, $target): void {
            $innerClosure($attribute, $value, $fail);
            $target->clearErrors($attribute);
        });

        return $rules;
    }

    /**
     * Yii only honors a rule's `when` callback from `validateAttributes()`.
     * The adapter closure calls `validateAttribute()` directly, so the
     * callback is evaluated here instead.
     *
     * @param array<int, mixed> $yiiRules
     * @return array<int, mixed>
     */
    private static function applyWhenConditions(array $yiiRules, object $subject): array
    {
        return array_values(array_filter($yiiRules, function($rule) use ($subject) {
            if (!is_array($rule) || !isset($rule['when']) || !is_callable($rule['when'])) {
                return true;
            }

            return (bool)($rule['when'])($subject);
        }));
    }
}
