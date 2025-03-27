<?php

declare(strict_types=1);

namespace BitTorrent;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Decoder::class)]
final class DecoderTest extends TestCase
{
    private Decoder $decoder;

    protected function setUp(): void
    {
        $this->decoder = new Decoder();
    }

    public static function getDecodeIntegerData(): array
    {
        return [
            ['i1e', 1],
            ['i-1e', -1],
            ['i0e', 0],
        ];
    }

    #[DataProvider('getDecodeIntegerData')]
    public function testDecoderInteger(string $encoded, int $value): void
    {
        $this->assertEquals($value, $this->decoder->decodeInteger($encoded));
    }

    public static function getDecodeInvalidIntegerData(): array
    {
        return [
            ['i01e'],
            ['i-01e'],
            ['ifoobare'],
        ];
    }

    #[DataProvider('getDecodeInvalidIntegerData')]
    public function testDecodeInvalidInteger(string $value): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid integer value.');
        $this->decoder->decodeInteger($value);
    }

    public function testDecodeStringAsInteger(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid integer. Integers must start wth "i" and end with "e".');
        $this->decoder->decodeInteger('4:spam');
    }

    public function testDecodePartialInteger(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid integer. Integers must start wth "i" and end with "e".');
        $this->decoder->decodeInteger('i10');
    }

    /**
     * @return array[]
     */
    public static function getDecodeStringData(): array
    {
        return [
            ['4:spam', 'spam'],
            ['11:test string', 'test string'],
            ['3:foobar', 'foo'],
        ];
    }

    #[DataProvider('getDecodeStringData')]
    public function testDecodeString(string $encoded, string $value): void
    {
        $this->assertSame($value, $this->decoder->decodeString($encoded));
    }

    public function testDecodeInvalidString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid string. Strings consist of two parts separated by ":".');
        $this->decoder->decodeString('4spam');
    }

    public function testDecodeStringWithInvalidLength(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The length of the string does not match the prefix of the encoded data.');
        $this->decoder->decodeString('6:spam');
    }

    /**
     * @return array[]
     */
    public static function getDecodeListData(): array
    {
        return [
            ['li1ei2ei3ee', [1, 2, 3]],
        ];
    }

    #[DataProvider('getDecodeListData')]
    public function testDecodeList(string $encoded, array $value): void
    {
        $this->assertEquals($value, $this->decoder->decodeList($encoded));
    }

    public function testDecodeInvalidList(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Parameter is not an encoded list.');
        $this->decoder->decodeList('4:spam');
    }

    public static function getDecodeDictionaryData(): array
    {
        return [
            ['d3:foo3:bar4:spam4:eggse', ['foo' => 'bar', 'spam' => 'eggs']],
        ];
    }

    #[DataProvider('getDecodeDictionaryData')]
    public function testDecodeDictionary(string $encoded, array $value): void
    {
        $this->assertSame($value, $this->decoder->decodeDictionary($encoded));
    }

    public function testDecodeInvalidDictionary(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Parameter is not an encoded dictionary.');
        $this->decoder->decodeDictionary('4:spam');
    }

    /**
     * @return array[]
     */
    public static function getGenericDecodeData(): array
    {
        return [
            ['i1e', 1],
            ['4:spam', 'spam'],
            ['li1ei2ei3ee', [1, 2, 3]],
            ['d3:foo3:bare', ['foo' => 'bar']],
        ];
    }

    #[DataProvider('getGenericDecodeData')]
    public function testGenericDecode(string $encoded, $value): void
    {
        $this->assertEquals($value, $this->decoder->decode($encoded));
    }

    public function testGenericDecodeWithInvalidData(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Parameter is not correctly encoded.');
        $this->decoder->decode('foo');
    }

    public function testDecodeTorrentFileStrictWithMissingAnnounce(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing or empty "announce" key.');
        $this->decoder->decodeFile(__DIR__.'/_files/testMissingAnnounce.torrent', true);
    }

    public function testDecodeTorrentFileStrictWithMissingInfo(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing or empty "info" key.');
        $this->decoder->decodeFile(__DIR__.'/_files/testMissingInfo.torrent', true);
    }

    public function testDecodeNonReadableFile(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/^File .*nonExistingFile does not exist or can not be read.$/');
        $this->decoder->decodeFile(__DIR__.'/nonExistingFile');
    }

    public function testDecodeFileWithStrictChecksEnabled(): void
    {
        $list = $this->decoder->decodeFile(__DIR__.'/_files/valid.torrent', true);

        $this->assertIsArray($list);
        $this->assertArrayHasKey('announce', $list);
        $this->assertSame('http://trackerurl', $list['announce']);
        $this->assertArrayHasKey('comment', $list);
        $this->assertSame('This is a comment', $list['comment']);
        $this->assertArrayHasKey('creation date', $list);
        $this->assertEquals(1323713688, $list['creation date']);
        $this->assertArrayHasKey('info', $list);
        $this->assertIsArray($list['info']);
        $this->assertArrayHasKey('files', $list['info']);
        $this->assertCount(5, $list['info']['files']);
        $this->assertArrayHasKey('name', $list['info']);
        $this->assertSame('PHP', $list['info']['name']);
    }
}
