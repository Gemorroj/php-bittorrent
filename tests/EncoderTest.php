<?php

declare(strict_types=1);

namespace BitTorrent;

use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \BitTorrent\Encoder
 */
class EncoderTest extends TestCase
{
    private Encoder $encoder;

    protected function setUp(): void
    {
        $this->encoder = new Encoder();
    }

    /**
     * @return array[]
     */
    public function getEncodeIntegerData(): array
    {
        return [
            [-1, 'i-1e'],
            [0, 'i0e'],
            [1, 'i1e'],
        ];
    }

    /**
     * @dataProvider getEncodeIntegerData
     *
     * @covers ::encodeInteger
     */
    public function testEncodeInteger(int $value, string $encoded): void
    {
        $this->assertSame($encoded, $this->encoder->encodeInteger($value));
    }

    /**
     * @return array[]
     */
    public function getEncodeStringData(): array
    {
        return [
            ['spam', '4:spam'],
            ['foobar', '6:foobar'],
            ['foo:bar', '7:foo:bar'],
        ];
    }

    /**
     * @dataProvider getEncodeStringData
     *
     * @covers ::encodeString
     */
    public function testEncodeString(string $value, string $encoded): void
    {
        $this->assertSame($encoded, $this->encoder->encodeString($value));
    }

    /**
     * @return array[]
     */
    public function getEncodeListData(): array
    {
        return [
            [['spam', 1, [1]], 'l4:spami1eli1eee'],
        ];
    }

    /**
     * @dataProvider getEncodeListData
     *
     * @covers ::encodeList
     */
    public function testEncodeList(array $value, string $encoded): void
    {
        $this->assertSame($encoded, $this->encoder->encodeList($value));
    }

    /**
     * @return array[]
     */
    public function getEncodeDictionaryData(): array
    {
        return [
            [['1' => 'foo', 'foo' => 'bar', 'list' => [1, 2, 3]], 'd1:13:foo3:foo3:bar4:listli1ei2ei3eee'],
            [['foo' => 'bar', 'spam' => 'eggs'], 'd3:foo3:bar4:spam4:eggse'],
            [['spam' => 'eggs', 'foo' => 'bar'], 'd3:foo3:bar4:spam4:eggse'],
        ];
    }

    /**
     * @dataProvider getEncodeDictionaryData
     *
     * @covers ::encodeDictionary
     */
    public function testEncodeDictionary(array $value, string $encoded): void
    {
        $this->assertSame($encoded, $this->encoder->encodeDictionary($value));
    }

    /**
     * @return array[]
     */
    public function getEncodeData(): array
    {
        return [
            [1, 'i1e'],
            ['spam', '4:spam'],
            [[1, 2, 3], 'li1ei2ei3ee'],
            [['foo' => 'bar', 'spam' => 'sucks'], 'd3:foo3:bar4:spam5:suckse'],
        ];
    }

    /**
     * @dataProvider getEncodeData
     *
     * @covers ::encode
     */
    public function testEncodeUsingGenericMethod($value, string $encoded): void
    {
        $this->assertSame($encoded, $this->encoder->encode($value));
    }

    /**
     * @covers ::__construct
     * @covers ::encode
     */
    public function testCanEncodeEmptyArraysAsDictionaries(): void
    {
        $encoder = new Encoder();
        $this->assertSame('le', $encoder->encode([]));

        $encoder = new Encoder([
            'encodeEmptyArrayAsDictionary' => true,
        ]);
        $this->assertSame('de', $encoder->encode([]));
    }
}
