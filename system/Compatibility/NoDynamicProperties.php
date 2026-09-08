<?php

declare(strict_types=1);

namespace CodeIgniter\Compatibility;

/**
 * Port of Duqm PHP 8.1 compatibility for final readonly framework classes.
 * Every declared property remains readonly; reject additional properties too.
 * See documentation/PHP81_COMPATIBILITY.md before replacing the framework.
 */
trait NoDynamicProperties
{
    public function __set(string $name, mixed $value): void
    {
        throw new \Error('Cannot create dynamic property ' . static::class . '::$' . $name);
    }
}
