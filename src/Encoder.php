<?php

declare(strict_types=1);

namespace BitTorrent;

final class Encoder implements EncoderInterface
{
    /**
     * Set to true to encode empty arrays as dictionaries ("de") instead of lists ("le").
     */
    private bool $encodeEmptyArrayAsDictionary;

    /**
     * Class constructor.
     *
     * @param bool $encodeEmptyArrayAsDictionary Encode empty arrays as dictionaries instead of lists
     */
    public function __construct(bool $encodeEmptyArrayAsDictionary = false)
    {
        $this->encodeEmptyArrayAsDictionary = $encodeEmptyArrayAsDictionary;
    }

    public function encode(int|string|array $var): string
    {
        if (\is_int($var)) {
            return $this->encodeInteger($var);
        }

        if (\is_string($var)) {
            return $this->encodeString($var);
        }

        if (!$var && $this->encodeEmptyArrayAsDictionary) {
            return $this->encodeDictionary($var);
        }

        return \array_is_list($var) ? $this->encodeList($var) : $this->encodeDictionary($var);
    }

    public function encodeInteger(int $integer): string
    {
        return \sprintf('i%de', $integer);
    }

    public function encodeString(string $string): string
    {
        return \sprintf('%d:%s', \strlen($string), $string);
    }

    public function encodeList(array $list): string
    {
        $encodedList = \array_map(function ($value): string {
            return $this->encode($value);
        }, $list);

        return \sprintf('l%se', \implode('', $encodedList));
    }

    public function encodeDictionary(array $dictionary): string
    {
        \ksort($dictionary, \SORT_STRING);

        $encodedDictionary = '';

        foreach ($dictionary as $key => $value) {
            $encodedDictionary .= $this->encodeString((string) $key).$this->encode($value);
        }

        return \sprintf('d%se', $encodedDictionary);
    }
}
