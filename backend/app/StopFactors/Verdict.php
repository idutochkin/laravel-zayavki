<?php

namespace App\StopFactors;

/**
 * Результат одной проверки — простой неизменяемый объект-значение (DTO).
 * Чистый PHP 8.2 (readonly class), к Laravel отношения не имеет.
 */
final readonly class Verdict
{
    private function __construct(
        public bool $passed,
        public ?string $message = null,
    ) {}

    public static function pass(): self
    {
        return new self(true);
    }

    public static function fail(string $message): self
    {
        return new self(false, $message);
    }
}
