<?php

declare(strict_types=1);

namespace BitTorrent;

interface DecoderInterface
{
    /**
     * Decode a file.
     *
     * @param string $path   Path to the torrent file we want to decode
     * @param bool   $strict When true, the decoded dictionary must contain a non-empty "announce"
     *                       string and a non-empty "info" dictionary; otherwise an
     *                       \InvalidArgumentException is thrown. When false, no such validation is
     *                       performed and any decoded dictionary is returned as is.
     *
     * @throws \InvalidArgumentException
     */
    public function decodeFile(string $path, bool $strict = false): DecodedDictionary;

    /**
     * Decode the contents of a file as a string.
     *
     * The returned object exposes both the decoded values and the original encoded bytes of every
     * top level value, so that, for instance, the "info" dictionary can be hashed exactly as it was
     * found in the file instead of hashing a re-encoded copy.
     *
     * @param string $contents The contents of a torrent file
     * @param bool   $strict   When true, the decoded dictionary must contain a non-empty "announce"
     *                         string and a non-empty "info" dictionary; otherwise an
     *                         \InvalidArgumentException is thrown. When false, no such validation is
     *                         performed and any decoded dictionary is returned as is.
     *
     * @throws \InvalidArgumentException
     */
    public function decodeFileContents(string $contents, bool $strict = false): DecodedDictionary;

    /**
     * Decode any bittorrent encoded string.
     *
     * @param string $string The string to decode
     *
     * @throws \InvalidArgumentException
     *
     * @return int|string|array Returns the native PHP counterpart of the encoded string
     */
    public function decode(string $string): int|string|array;

    /**
     * Decode an encoded PHP integer.
     *
     * @param string $integer The integer to decode
     *
     * @throws \InvalidArgumentException
     *
     * @return int Returns the decoded integer
     */
    public function decodeInteger(string $integer): int;

    /**
     * Decode an encoded PHP string.
     *
     * @param string $string The string to decode
     *
     * @throws \InvalidArgumentException
     *
     * @return string Returns the decoded string value
     */
    public function decodeString(string $string): string;

    /**
     * Decode an encoded PHP array.
     *
     * @param string $list Encoded list
     *
     * @throws \InvalidArgumentException
     *
     * @return array Returns a numerical array
     */
    public function decodeList(string $list): array;

    /**
     * Decode an encoded PHP associative array.
     *
     * @param string $dictionary Encoded dictionary
     *
     * @throws \InvalidArgumentException
     */
    public function decodeDictionary(string $dictionary): DecodedDictionary;
}
