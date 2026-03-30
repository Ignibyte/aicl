<?php

declare(strict_types=1);

namespace Aicl\Console\Support;

use InvalidArgumentException;

/**
 * Shared parsing logic for comma-separated definition strings.
 *
 * Used by FieldParser and RelationshipParser to avoid duplicating the
 * segment-splitting, error-collecting, and duplicate-name-checking loop.
 */
trait ParsesCommaSeparatedDefinitions
{
    /**
     * Parse a comma-separated definition string into definition objects.
     *
     * @throws InvalidArgumentException
     *
     * @return array<int, mixed>
     */
    public function parse(string $definitionString): array
    {
        $definitionString = trim($definitionString);

        if ($definitionString === '') {
            return [];
        }

        $segments = array_map('trim', explode(',', $definitionString));
        $definitions = [];
        $errors = [];
        $seenNames = [];

        foreach ($segments as $segment) {
            if ($segment === '') {
                continue;
            }

            try {
                $definition = $this->parseSegment($segment, $seenNames);
                $seenNames[] = $definition->name;
                $definitions[] = $definition;
            } catch (InvalidArgumentException $e) {
                $errors[] = $e->getMessage();
            }
        }

        if (! empty($errors)) {
            throw new InvalidArgumentException(implode("\n", $errors));
        }

        return $definitions;
    }

    /**
     * Parse a single segment into a definition object.
     *
     * @param array<int, string> $seenNames Previously seen names for duplicate detection
     *
     * @throws InvalidArgumentException
     *
     * @return object A definition object with a public $name property
     */
    abstract protected function parseSegment(string $segment, array $seenNames): object;
}
