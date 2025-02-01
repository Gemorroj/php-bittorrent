<?php

declare(strict_types=1);

namespace BitTorrent;

interface EncoderInterface
{
    /**
     * Encode any encodable variable.
     *
     * @param int|string|array $var The variable to encode. Supports: int, string and array
     *
     * @throws \InvalidArgumentException
     *
     * @return string Returns the encoded string
     */
    public function encode(int|string|array $var): string;

    /**
     * Encode an integer.
     *
     * @param int $integer The integer to encode
     *
     * @return string Returns the encoded string
     */
    public function encodeInteger(int $integer): string;

    /**
     * Encode a string.
     *
     * @param string $string The string to encode
     *
     * @return string Returns the encoded string
     */
    public function encodeString(string $string): string;

    /**
     * Encode a list (numerically indexed array).
     *
     * @param array $list The array to encode
     *
     * @return string Returns the encoded string
     */
    public function encodeList(array $list): string;

    /**
     * Encode a dictionary (associative PHP array).
     *
     * @param array $dictionary The array to encode
     *
     * @return string Returns the encoded string
     */
    public function encodeDictionary(array $dictionary): string;
}
