<?php

declare(strict_types=1);

namespace BitTorrent;

final class Decoder implements DecoderInterface
{
    /**
     * Decode a torrent file.
     *
     * @param bool $strict When true, the file must contain a non-empty "announce" and "info";
     *                     see {@see decodeFileContents()}
     */
    public function decodeFile(string $path, bool $strict = false): DecodedDictionary
    {
        $contents = @\file_get_contents($path);
        if (false === $contents) {
            throw new \InvalidArgumentException(\sprintf('File %s does not exist or can not be read.', $path));
        }

        return $this->decodeFileContents($contents, $strict);
    }

    /**
     * Decode the contents of a torrent file.
     *
     * @param bool $strict When true, the decoded dictionary must contain a non-empty "announce"
     *                     string and a non-empty "info" dictionary (the two keys every torrent
     *                     must have); otherwise an \InvalidArgumentException is thrown. When false,
     *                     the dictionary is returned as is without any validation
     */
    public function decodeFileContents(string $contents, bool $strict = false): DecodedDictionary
    {
        $offset = 0;
        $ranges = [];
        $dictionary = $this->parseDictionary($contents, $offset, $ranges);
        $this->assertFullyDecoded($contents, $offset);

        if ($strict) {
            // A torrent is only valid when it points to a tracker and carries the "info" dictionary
            if (!isset($dictionary['announce']) || !\is_string($dictionary['announce']) || '' === $dictionary['announce']) {
                throw new \InvalidArgumentException('Missing or empty "announce" key.');
            }

            if (!isset($dictionary['info']) || !\is_array($dictionary['info']) || [] === $dictionary['info']) {
                throw new \InvalidArgumentException('Missing or empty "info" key.');
            }
        }

        return new DecodedDictionary($dictionary, $contents, $ranges);
    }

    public function decode(string $string): int|string|array
    {
        $offset = 0;
        $value = $this->decodeAt($string, $offset);
        $this->assertFullyDecoded($string, $offset);

        return $value;
    }

    public function decodeInteger(string $integer): int
    {
        if ('i' !== ($integer[0] ?? '')) {
            throw new \InvalidArgumentException('Invalid integer. Integers must start wth "i" and end with "e".');
        }

        $offset = 0;
        $value = $this->parseInteger($integer, $offset);
        $this->assertFullyDecoded($integer, $offset);

        return $value;
    }

    public function decodeString(string $string): string
    {
        $offset = 0;
        $value = $this->parseString($string, $offset);
        $this->assertFullyDecoded($string, $offset);

        return $value;
    }

    public function decodeList(string $list): array
    {
        $offset = 0;
        $value = $this->parseList($list, $offset);
        $this->assertFullyDecoded($list, $offset);

        return $value;
    }

    public function decodeDictionary(string $dictionary): DecodedDictionary
    {
        $offset = 0;
        $ranges = [];
        $values = $this->parseDictionary($dictionary, $offset, $ranges);
        $this->assertFullyDecoded($dictionary, $offset);

        return new DecodedDictionary($values, $dictionary, $ranges);
    }

    private function assertFullyDecoded(string $string, int $offset): void
    {
        // Trailing whitespace (a common newline at the end of a file) is tolerated, anything else is not
        if ('' !== \trim(\substr($string, $offset))) {
            throw new \InvalidArgumentException('Parameter is not correctly encoded.');
        }
    }

    private function decodeAt(string $string, int &$offset): int|string|array
    {
        $character = $string[$offset] ?? '';

        if ('i' === $character) {
            return $this->parseInteger($string, $offset);
        }

        if ('l' === $character) {
            return $this->parseList($string, $offset);
        }

        if ('d' === $character) {
            return $this->parseDictionary($string, $offset);
        }

        if ('' !== $character && $character >= '0' && $character <= '9') {
            return $this->parseString($string, $offset);
        }

        throw new \InvalidArgumentException('Parameter is not correctly encoded.');
    }

    private function parseInteger(string $string, int &$offset): int
    {
        $end = \strpos($string, 'e', $offset + 1);

        if (false === $end) {
            throw new \InvalidArgumentException('Invalid integer. Integers must start wth "i" and end with "e".');
        }

        $value = \substr($string, $offset + 1, $end - $offset - 1);

        if (1 !== \preg_match('/^(0|-?[1-9]\d*)$/', $value)) {
            throw new \InvalidArgumentException('Invalid integer value.');
        }

        $offset = $end + 1;

        return (int) $value;
    }

    private function parseString(string $string, int &$offset): string
    {
        $colon = \strpos($string, ':', $offset);

        if (false === $colon) {
            throw new \InvalidArgumentException('Invalid string. Strings consist of two parts separated by ":".');
        }

        $lengthPart = \substr($string, $offset, $colon - $offset);

        if ('' === $lengthPart || \strspn($lengthPart, '0123456789') !== \strlen($lengthPart)) {
            throw new \InvalidArgumentException('Invalid string. Strings consist of two parts separated by ":".');
        }

        $length = (int) $lengthPart;
        $dataOffset = $colon + 1;

        if ($dataOffset + $length > \strlen($string)) {
            throw new \InvalidArgumentException('The length of the string does not match the prefix of the encoded data.');
        }

        $offset = $dataOffset + $length;

        return \substr($string, $dataOffset, $length);
    }

    private function parseList(string $string, int &$offset): array
    {
        if ('l' !== ($string[$offset] ?? '')) {
            throw new \InvalidArgumentException('Parameter is not an encoded list.');
        }

        ++$offset;
        $list = [];

        while (true) {
            if (!isset($string[$offset])) {
                throw new \InvalidArgumentException('Invalid list. Missing the terminating "e".');
            }

            if ('e' === $string[$offset]) {
                ++$offset;

                return $list;
            }

            $list[] = $this->decodeAt($string, $offset);
        }
    }

    /**
     * @param array<string, array{int, int}>|null $ranges When provided, the offset and length of every value is collected here
     */
    private function parseDictionary(string $string, int &$offset, ?array &$ranges = null): array
    {
        if ('d' !== ($string[$offset] ?? '')) {
            throw new \InvalidArgumentException('Parameter is not an encoded dictionary.');
        }

        ++$offset;
        $dictionary = [];

        while (true) {
            if (!isset($string[$offset])) {
                throw new \InvalidArgumentException('Invalid dictionary. Missing the terminating "e".');
            }

            if ('e' === $string[$offset]) {
                ++$offset;

                return $dictionary;
            }

            $key = $this->parseString($string, $offset);
            $valueOffset = $offset;
            $dictionary[$key] = $this->decodeAt($string, $offset);

            if (null !== $ranges) {
                $ranges[$key] = [$valueOffset, $offset - $valueOffset];
            }
        }
    }
}
