<?php

declare(strict_types=1);

namespace Aicl\Horizon;

/**
 * PhpBinary.
 */
class PhpBinary
{
    /**
     * Get the path to the PHP executable.
     *
     * @return string
     */
    public static function path()
    {
        $escape = '\\' === DIRECTORY_SEPARATOR ? '"' : '\'';

        return $escape.PHP_BINARY.$escape;
    }
}
