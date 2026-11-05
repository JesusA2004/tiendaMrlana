<?php

namespace App\Enums;

/**
 * Utilidades comunes para enums respaldados por string.
 */
trait EnumHelpers
{
    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }

    /** @return list<array{value: string, label: string, color: string}> */
    public static function options(): array
    {
        return array_map(fn (self $c) => ['value' => $c->value, 'label' => $c->label(), 'color' => $c->color()], self::cases());
    }

    public static function labelDe(?string $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        return self::tryFrom($valor)?->label() ?? $valor;
    }

    /** @return array{value: string, label: string, color: string} */
    public function toArray(): array
    {
        return ['value' => $this->value, 'label' => $this->label(), 'color' => $this->color()];
    }
}
