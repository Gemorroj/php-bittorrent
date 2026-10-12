<?php

declare(strict_types=1);

namespace BitTorrent;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Decoder::class)]
#[CoversClass(DecodedDictionary::class)]
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
        $this->expectExceptionMessageIs('Invalid integer value.');
        $this->decoder->decodeInteger($value);
    }

    public function testDecodeStringAsInteger(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageIs('Invalid integer. Integers must start wth "i" and end with "e".');
        $this->decoder->decodeInteger('4:spam');
    }

    public function testDecodePartialInteger(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageIs('Invalid integer. Integers must start wth "i" and end with "e".');
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
            ['3:foo', 'foo'],
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
        $this->expectExceptionMessageIs('Invalid string. Strings consist of two parts separated by ":".');
        $this->decoder->decodeString('4spam');
    }

    public function testDecodeStringWithInvalidLength(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageIs('The length of the string does not match the prefix of the encoded data.');
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
        $this->expectExceptionMessageIs('Parameter is not an encoded list.');
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
        $this->assertSame($value, $this->decoder->decodeDictionary($encoded)->getValues());
    }

    public function testDecodeInvalidDictionary(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageIs('Parameter is not an encoded dictionary.');
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
    public function testGenericDecode(string $encoded, int|string|array $value): void
    {
        $this->assertEquals($value, $this->decoder->decode($encoded));
    }

    public function testGenericDecodeWithInvalidData(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageIs('Parameter is not correctly encoded.');
        $this->decoder->decode('foo');
    }

    public function testGenericDecodeWithEmptyString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageIs('Parameter is not correctly encoded.');
        $this->decoder->decode('');
    }

    public function testDecodeRejectsTrailingData(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageIs('Parameter is not correctly encoded.');
        $this->decoder->decode('i1ejunk');
    }

    public function testDecodeIgnoresTrailingWhitespace(): void
    {
        $this->assertSame(1, $this->decoder->decode("i1e\n"));
        $this->assertSame('foo', $this->decoder->decodeString("3:foo\r\n"));
    }

    public function testDecodeStringRejectsTrailingData(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageIs('Parameter is not correctly encoded.');
        $this->decoder->decodeString('3:foobar');
    }

    public function testDecodeListRejectsTrailingData(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageIs('Parameter is not correctly encoded.');
        $this->decoder->decodeList('li1eeXYZ');
    }

    public function testDecodeDictionaryRejectsTrailingData(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageIs('Parameter is not correctly encoded.');
        $this->decoder->decodeDictionary('d3:foo3:bareXYZ');
    }

    public function testDecodeStringWithLeadingZeroLength(): void
    {
        $this->assertSame('hello', $this->decoder->decodeString('05:hello'));
    }

    public function testDecodeDictionaryWithNonCanonicalKeyOrder(): void
    {
        $this->assertSame(
            ['spam' => 'eggs', 'foo' => 'bar'],
            $this->decoder->decodeDictionary('d4:spam4:eggs3:foo3:bare')->getValues()
        );
    }

    public function testDecodeContentsStrictWithEmptyAnnounce(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageIs('Missing or empty "announce" key.');
        $this->decoder->decodeFileContents('d8:announce0:4:infodee', true);
    }

    public function testDecodeContentsStrictWithEmptyInfo(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageIs('Missing or empty "info" key.');
        $this->decoder->decodeFileContents('d8:announce3:url4:infodee', true);
    }

    public function testDecodeFileContentsKeepsSource(): void
    {
        $decoded = $this->decoder->decodeFileContents('d8:announce3:url4:infod4:name4:testee');

        $this->assertSame('url', $decoded->getValues()['announce']);
        $this->assertSame('d4:name4:teste', $decoded->getEncodedValue('info'));
        $this->assertSame(
            ['announce' => '3:url', 'info' => 'd4:name4:teste'],
            $decoded->getEncodedValues()
        );
        $this->assertNull($decoded->getEncodedValue('missing'));
    }

    public function testDecodeStringWithInvalidLengthPrefix(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageIs('Invalid string. Strings consist of two parts separated by ":".');
        $this->decoder->decodeString('a:b');
    }

    public function testDecodeListWithoutTerminator(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageIs('Invalid list. Missing the terminating "e".');
        $this->decoder->decodeList('li1e');
    }

    public function testDecodeDictionaryWithoutTerminator(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageIs('Invalid dictionary. Missing the terminating "e".');
        $this->decoder->decodeDictionary('d3:foo3:bar');
    }

    public function testDecodeEmptyListAndDictionary(): void
    {
        $this->assertSame([], $this->decoder->decodeList('le'));
        $this->assertSame([], $this->decoder->decodeDictionary('de')->getValues());
    }

    public function testDecodeContentsStrictWithMissingAnnounce(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageIs('Missing or empty "announce" key.');
        $this->decoder->decodeFileContents('d3:foo3:bare', true);
    }

    public function testDecodeContentsStrictWithMissingInfo(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageIs('Missing or empty "info" key.');
        $this->decoder->decodeFileContents('d8:announce15:http://tracker/e', true);
    }

    public function testDecodeContentsStrictWithNonStringAnnounce(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageIs('Missing or empty "announce" key.');
        $this->decoder->decodeFileContents('d8:announceli1ee4:infodee', true);
    }

    public function testDecodeContentsStrictWithNonArrayInfo(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageIs('Missing or empty "info" key.');
        $this->decoder->decodeFileContents('d8:announce3:url4:info3:abce', true);
    }

    public function testDecodeNonReadableFile(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/^File .*nonExistingFile does not exist or can not be read.$/');
        $this->decoder->decodeFile(__DIR__.'/nonExistingFile');
    }

    public function testDecodeFileWithStrictChecksEnabled(): void
    {
        $decoded = $this->decoder->decodeFile(__DIR__.'/fixtures/multi-file.torrent', true);
        $list = $decoded->getValues();

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
