<?php

declare(strict_types=1);

namespace anvildev\craftkickback\validators;

use Closure;
use Illuminate\Contracts\Validation\ImplicitRule;

/**
 * Craft 6-only. Wraps a closure-style validation callback as an implicit
 * Laravel rule so it still runs when the attribute is empty.
 *
 * A bare Closure is wrapped by ClosureValidationRule, which Laravel skips
 * entirely for empty values. A bridged Yii `required` rule never gets the
 * chance to fail. ImplicitRule is the contract Laravel still checks.
 *
 * @author anvildev <dev@anvil.xyz>
 * @since 1.2.0
 */
class LegacyYiiRule implements ImplicitRule
{
    private bool $failed = false;

    /** @var array<int, string> */
    private array $messages = [];

    /**
     * @param Closure(string, mixed, Closure): void $callback
     */
    public function __construct(private readonly Closure $callback)
    {
    }

    /**
     * @param string $attribute
     * @param mixed $value
     */
    public function passes($attribute, $value): bool
    {
        $this->failed = false;
        $this->messages = [];

        ($this->callback)($attribute, $value, function(string $message): void {
            $this->failed = true;
            $this->messages[] = $message;
        });

        return !$this->failed;
    }

    /**
     * @return array<int, string>
     */
    public function message(): array
    {
        return $this->messages;
    }
}
