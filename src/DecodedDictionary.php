<?php

declare(strict_types=1);

namespace BitTorrent;

/**
 * A decoded bencoded dictionary together with the position of each value in the source string.
 *
 * The source and the value ranges are kept so that a value can be re-hashed exactly as it was
 * found in the source, without being affected by re-encoding (key order, integer formatting and
 * so on). The encoded bytes are extracted lazily, so nothing is copied unless actually requested.
 */
final readonly class DecodedDictionary
{
    /**
     * @param array<string, mixed>           $values Decoded values indexed by key
     * @param array<string, array{int, int}> $ranges Offset and length of every value indexed by key
     */
    public function __construct(
        private array $values,
        private string $source = '',
        private array $ranges = [],
    ) {
    }

    /**
     * Get all decoded values.
     *
     * @return array<string, mixed>
     */
    public function getValues(): array
    {
        return $this->values;
    }

    /**
     * Get the original bencoded bytes of a single value.
     */
    public function getEncodedValue(string $key): ?string
    {
        if (!isset($this->ranges[$key])) {
            return null;
        }

        [$offset, $length] = $this->ranges[$key];

        return \substr($this->source, $offset, $length);
    }

    /**
     * Get the original bencoded bytes of every value.
     *
     * @return array<string, string>
     */
    public function getEncodedValues(): array
    {
        $encodedValues = [];

        foreach ($this->ranges as $key => [$offset, $length]) {
            $encodedValues[$key] = \substr($this->source, $offset, $length);
        }

        return $encodedValues;
    }
}
