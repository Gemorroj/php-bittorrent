<?php

declare(strict_types=1);

namespace BitTorrent;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Torrent::class)]
final class TorrentTest extends TestCase
{
    private Torrent $torrent;

    /**
     * Directory with files to pack into a torrent, created on demand by createSourceDirectory().
     */
    private ?string $sourceDirectory = null;

    protected function setUp(): void
    {
        $this->torrent = new Torrent();
    }

    protected function tearDown(): void
    {
        if (null !== $this->sourceDirectory) {
            $this->removeDirectory($this->sourceDirectory);
            $this->sourceDirectory = null;
        }

        parent::tearDown();
    }

    /**
     * @param array<string, mixed> $dictionary
     */
    private static function encodeTorrent(array $dictionary): string
    {
        return new Encoder()->encodeDictionary($dictionary);
    }

    /**
     * Create a temporary directory with a file tree to build a torrent from.
     *
     * The files are larger than a single piece (256 KiB) and include an empty file, so the piece
     * hashing covers full pieces, pieces split across files and empty files: 400000 bytes in total.
     */
    private function createSourceDirectory(): string
    {
        $directory = \sys_get_temp_dir().\DIRECTORY_SEPARATOR.\uniqid('php-bittorrent-', true);
        \mkdir($directory.\DIRECTORY_SEPARATOR.'nested', 0o777, true);
        \file_put_contents($directory.\DIRECTORY_SEPARATOR.'empty.bin', '');
        \file_put_contents($directory.\DIRECTORY_SEPARATOR.'part.bin', \str_repeat('a', 200000));
        \file_put_contents($directory.\DIRECTORY_SEPARATOR.'nested'.\DIRECTORY_SEPARATOR.'rest.bin', \str_repeat('b', 200000));

        return $this->sourceDirectory = $directory;
    }

    private function removeDirectory(string $directory): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $entry) {
            $entry->isDir() ? \rmdir($entry->getPathname()) : \unlink($entry->getPathname());
        }

        \rmdir($directory);
    }

    public function testConstructor(): void
    {
        $torrent = new Torrent('http://sometracker');
        $this->assertSame('http://sometracker', $torrent->getAnnounceUrl());
        $this->assertInstanceOf(EncoderInterface::class, $torrent->getEncoder());
    }

    public function testSetAndGetEncoder(): void
    {
        $encoder = new Encoder();
        $torrent = new Torrent('http://sometracker')->withEncoder($encoder);
        $this->assertInstanceOf(EncoderInterface::class, $torrent->getEncoder());
    }

    /**
     * @return array<string, array>
     */
    public static function getDataForSettersAndGetters(): array
    {
        return [
            'Announce URL' => [
                'getter' => 'getAnnounceUrl',
                'mutator' => 'withAnnounceUrl',
                'value' => 'http://tracker',
                'initial' => null,
            ],
            'Piece length exponent' => [
                'getter' => 'getPieceLengthExp',
                'mutator' => 'withPieceLengthExp',
                'value' => 6,
                'initial' => 18,
            ],
            'Comment' => [
                'getter' => 'getComment',
                'mutator' => 'withComment',
                'value' => 'some comment',
                'initial' => null,
            ],
            'Announce list' => [
                'getter' => 'getAnnounceList',
                'mutator' => 'withAnnounceList',
                'value' => ['http://sometracker'],
                'initial' => null,
            ],
            'Created by' => [
                'getter' => 'getCreatedBy',
                'mutator' => 'withCreatedBy',
                'value' => 'Some creator',
                'initial' => 'PHP BitTorrent',
            ],
            'Created at' => [
                'getter' => 'getCreatedAt',
                'mutator' => 'withCreatedAt',
                'value' => 1567451302,
                'initial' => null,
            ],
            'Info' => [
                'getter' => 'getInfo',
                'mutator' => 'withInfo',
                'value' => ['length' => 123, 'name' => 'some name'],
                'initial' => null,
            ],
            'Extra meta' => [
                'getter' => 'getExtraMeta',
                'mutator' => 'withExtraMeta',
                'value' => ['foo' => 'bar'],
                'initial' => null,
            ],
        ];
    }

    #[DataProvider('getDataForSettersAndGetters')]
    public function testSettersAndGetters(string $getter, string $mutator, mixed $value, mixed $initial): void
    {
        $this->assertSame($initial, $this->torrent->$getter(), 'Incorrect initial value');
        $torrent = $this->torrent->$mutator($value);
        $this->assertSame($initial, $this->torrent->$getter(), 'Incorrect value after setting');
        $this->assertSame($value, $torrent->$getter(), 'Incorrect value in mutation');
    }

    public function testGetNameWithNoInfoBlockAdded(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The info part of the torrent is not set.');
        $this->torrent->getName();
    }

    public function testGetSizeWithNoInfoBlockAdded(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The info part of the torrent is not set.');
        $this->torrent->getSize();
    }

    public function testGetFileListWithNoInfoBlockAdded(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The info part of the torrent is not set.');
        $this->torrent->getFileList();
    }

    public function testGetName(): void
    {
        $this->assertSame('Some name', $this->torrent->withInfo($info = ['name' => 'Some name'])->getName());
    }

    public function testGetSizeWhenLengthIsPresentInTheInfoBlock(): void
    {
        $this->assertSame(123, $this->torrent->withInfo(['length' => 123])->getSize());
    }

    public function testGetFileListWhenInfoBlockOnlyContainsOneFile(): void
    {
        $fileList = $this->torrent->withInfo(['length' => 123, 'name' => 'some_filename'])->getFileList();
        $this->assertCount(1, $fileList);
        $this->assertSame([
            ['length' => 123, 'path' => ['some_filename']],
        ], $fileList);
    }

    public function testGetFileList(): void
    {
        $files = [
            ['length' => 12, 'path' => ['path', 'file.php']],
            ['length' => 32, 'path' => ['path2', 'file2.php']],
            ['length' => 123, 'path' => ['file.php']],
        ];
        $this->assertSame($files, $this->torrent->withInfo(['files' => $files])->getFileList());
    }

    public function testGetFileListAndSizeWhenInfoHasNoFiles(): void
    {
        $torrent = $this->torrent->withInfo([]);

        $this->assertSame([], $torrent->getFileList());
        $this->assertSame(0, $torrent->getSize());
    }

    public function testGetNameWhenNameIsMissing(): void
    {
        $this->assertSame('', $this->torrent->withInfo(['length' => 1])->getName());
    }

    public function testGetSizeWhenInfoBlockHasSeveralFiles(): void
    {
        $files = [
            ['length' => 12, 'path' => ['path', 'file.php']],
            ['length' => 32, 'path' => ['path2', 'file2.php']],
            ['length' => 123, 'path' => ['file.php']],
        ];
        $this->assertEquals(167, $this->torrent->withInfo(['files' => $files])->getSize());
    }

    public function testCreateFromTorrentFile(): void
    {
        $torrent = Torrent::createFromTorrentFile(__DIR__.'/fixtures/multi-file.torrent', new Decoder());

        $this->assertSame('http://trackerurl', $torrent->getAnnounceUrl());
        $this->assertSame('This is a comment', $torrent->getComment());
        $this->assertSame('PHP BitTorrent', $torrent->getCreatedBy());
        $this->assertSame(1323713688, $torrent->getCreatedAt());
        $this->assertEquals(30243, $torrent->getSize());
        $this->assertCount(5, $torrent->getFileList());
    }

    public function testCreateFromTorrentFileString(): void
    {
        $contents = \file_get_contents(__DIR__.'/fixtures/multi-file.torrent');
        $this->assertIsString($contents);
        $torrent = Torrent::createFromString($contents, new Decoder());

        $this->assertSame('http://trackerurl', $torrent->getAnnounceUrl());
        $this->assertSame('This is a comment', $torrent->getComment());
        $this->assertSame('PHP BitTorrent', $torrent->getCreatedBy());
        $this->assertSame(1323713688, $torrent->getCreatedAt());
        $this->assertEquals(30243, $torrent->getSize());
        $this->assertCount(5, $torrent->getFileList());
    }

    public function testCreateFromTorrentFileWithLists(): void
    {
        $announceList = [
            [
                'http://tracker/',
                'http://tracker2/',
                'http://tracker3/',
            ],
        ];
        $torrent = Torrent::createFromString(self::encodeTorrent([
            'announce' => 'http://tracker/',
            'announce-list' => $announceList,
            'info' => ['name' => 'extra', 'length' => 440, 'piece length' => 262144, 'pieces' => \str_repeat("\0", 20)],
        ]), new Decoder());

        $this->assertSame('http://tracker/', $torrent->getAnnounceUrl());
        $this->assertEquals($announceList, $torrent->getAnnounceList());
        $this->assertCount(1, $torrent->getFileList());
    }

    public function testCreateFromTorrentFileWithExtra(): void
    {
        $webSeeds = [
            'url-list' => [
                'http://seed/',
                'http://seed2/',
                'http://seed3/',
            ],
        ];
        $torrent = Torrent::createFromString(self::encodeTorrent([
            'announce' => 'http://tracker/',
            'info' => ['name' => 'extra', 'length' => 440, 'piece length' => 262144, 'pieces' => \str_repeat("\0", 20)],
            'url-list' => $webSeeds['url-list'],
        ]), new Decoder());

        $this->assertEquals($webSeeds, $torrent->getExtraMeta());
    }

    public function testCreateFromPathHashesPieces(): void
    {
        $path = $this->createSourceDirectory();
        $torrent = Torrent::createFromPath($path, 'http://tracker/');

        $info = $torrent->getInfo();
        $this->assertIsArray($info);
        $pieceLength = (int) $info['piece length'];

        // Independently rebuild the byte stream from the listed files and hash it piece by piece
        $content = '';
        foreach ($torrent->getFileList() as $file) {
            $chunk = \file_get_contents($path.\DIRECTORY_SEPARATOR.\implode(\DIRECTORY_SEPARATOR, $file['path']));
            $this->assertIsString($chunk);
            $content .= $chunk;
        }

        $expectedPieces = '';
        for ($offset = 0; $offset < \strlen($content); $offset += $pieceLength) {
            $expectedPieces .= \sha1(\substr($content, $offset, $pieceLength), true);
        }

        $this->assertSame($expectedPieces, $info['pieces']);
        $this->assertGreaterThan(1, \intdiv(\strlen($expectedPieces), 20), 'Expected more than one piece');
    }

    public function testCreateFromDictionaryWithAnnounceAsArray(): void
    {
        $torrent = Torrent::createFromDictionary([
            'announce' => ['http://tracker1', 'http://tracker2'],
        ]);

        $this->assertSame('http://tracker1', $torrent->getAnnounceUrl());
    }

    public function testCreateFromPathWhenUsingADirectoryAsArgument(): void
    {
        $path = $this->createSourceDirectory();
        $trackerUrl = 'http://trackerurl';
        $torrent = Torrent::createFromPath($path, $trackerUrl);

        $this->assertSame($trackerUrl, $torrent->getAnnounceUrl());
        $this->assertSame(\basename($path), $torrent->getName());
        $this->assertEquals(400000, $torrent->getSize());
        $this->assertCount(3, $torrent->getFileList());
    }

    public function testCreateFromPathWhenUsingAFileAsArgument(): void
    {
        $path = __DIR__.'/fixtures/multi-file.torrent';
        $trackerUrl = 'http://trackerurl';
        $torrent = Torrent::createFromPath($path, $trackerUrl);

        $this->assertSame($trackerUrl, $torrent->getAnnounceUrl());
        $this->assertSame('multi-file.torrent', $torrent->getName());
        $this->assertSame(440, $torrent->getSize());
        $this->assertCount(1, $torrent->getFileList());
    }

    public function testSaveTorrent(): void
    {
        $path = $this->createSourceDirectory();
        $name = \basename($path);
        $announce = 'http://tracker/';
        $announceList = [['http://tracker2'], ['http://tracker3']];
        $comment = 'Some comment';
        $createdBy = 'PHPUnit';
        $target = \tempnam(\sys_get_temp_dir(), 'PHP\BitTorrent');

        if (!$target) {
            $this->fail('Could not create file: '.$target);
        }

        $torrent = Torrent::createFromPath($path, $announce)
            ->withComment($comment)
            ->withCreatedBy($createdBy)
            ->withAnnounceList($announceList);

        $this->assertTrue($torrent->save($target));

        $torrent = Torrent::createFromTorrentFile($target, new Decoder());

        $this->assertSame($announce, $torrent->getAnnounceUrl());
        $this->assertSame($comment, $torrent->getComment());
        $this->assertSame($createdBy, $torrent->getCreatedBy());
        $this->assertSame($name, $torrent->getName());
        $this->assertEquals(400000, $torrent->getSize());
        $this->assertCount(3, $torrent->getFileList());
        $this->assertSame($announceList, $torrent->getAnnounceList());

        \unlink($target);
    }

    public function testSaveKeepsZeroCreationDate(): void
    {
        $target = \tempnam(\sys_get_temp_dir(), 'PHP\BitTorrent');

        if (!$target) {
            $this->fail('Could not create file: '.$target);
        }

        Torrent::createFromPath(__DIR__.'/fixtures/multi-file.torrent', 'http://tracker/')
            ->withCreatedAt(0)
            ->save($target);

        $torrent = Torrent::createFromTorrentFile($target, new Decoder());

        $this->assertSame(0, $torrent->getCreatedAt());

        \unlink($target);
    }

    public function testSaveWithExtra(): void
    {
        $path = $this->createSourceDirectory();
        $announce = 'http://tracker/';
        $target = \tempnam(\sys_get_temp_dir(), 'PHP\BitTorrent');

        if (!$target) {
            $this->fail('Could not create file: '.$target);
        }

        $extra = [
            'url-list' => [
                'http://seed/',
                'http://seed2/',
                'http://seed3/',
            ],
        ];

        $torrent = Torrent::createFromPath($path, $announce)->withExtraMeta($extra);
        $torrent->save($target);

        $torrent = Torrent::createFromTorrentFile($target, new Decoder());

        $this->assertEquals($extra, $torrent->getExtraMeta());

        \unlink($target);
    }

    public function testSaveWithInvalidExtra(): void
    {
        $path = $this->createSourceDirectory();
        $announce = 'http://tracker/';
        $target = \tempnam(\sys_get_temp_dir(), 'PHP\BitTorrent');

        if (!$target) {
            $this->fail('Could not create file: '.$target);
        }

        $extra = ['announce' => 'http://extratracker'];

        $torrent = Torrent::createFromPath($path, $announce)
            ->withExtraMeta($extra);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Duplicate key in extra meta info');
        $torrent->save($target);
    }

    public function testSaveWithNoAnnounce(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Announce URL is missing');
        $this->torrent->save(\tempnam(\sys_get_temp_dir(), 'PHP\BitTorrent'));
    }

    public function testSaveWithNoInfoBlock(): void
    {
        $torrent = $this->torrent->withAnnounceUrl('http://tracker');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The info part of the torrent is not set.');
        $torrent->save(\tempnam(\sys_get_temp_dir(), 'PHP\BitTorrent'));
    }

    public function testSaveToUnwritableFile(): void
    {
        $torrent = Torrent::createFromPath(__FILE__, 'http://tracker/');
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Could not open file');
        $torrent->save(\uniqid('', true).\DIRECTORY_SEPARATOR.\uniqid('', true));
    }

    public function testCreateFromTorrentFileWithUnexistingTorrentFile(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('foobar does not exist');
        Torrent::createFromTorrentFile('foobar', new Decoder());
    }

    public function testCreateFromPathWithInvalidPath(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid path: foobar');
        Torrent::createFromPath('foobar', 'http://trackerurl');
    }

    public function testThrowsExceptionWhenTryingToGenerateHashWithEmptyTorrentFile(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The info part of the torrent is not set.');
        $this->torrent->getHash();
    }

    public function testThrowsExceptionWhenTryingToGenerateEncodedHashWithEmptyTorrentFile(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The info part of the torrent is not set.');
        $this->torrent->getEncodedHash();
    }

    public function testGetHash(): void
    {
        $this->assertSame(
            'c717bfd30211a5e36c94cabaab3b3ca0dc89f91a',
            Torrent::createFromTorrentFile(__DIR__.'/fixtures/multi-file.torrent', new Decoder())->getHash()
        );
    }

    public function testGetHashWhenBuiltProgrammatically(): void
    {
        // No original bytes are available here, so the hash is computed from a re-encoded info
        $torrent = $this->torrent->withInfo(['name' => 'some', 'length' => 1]);

        $this->assertSame(\sha1(new Encoder()->encodeDictionary($torrent->getInfo())), $torrent->getHash());
    }

    public function testGetHashUsesOriginalInfoBytes(): void
    {
        // The info dictionary below has non-canonical (unsorted) keys, so a re-encoded copy would
        // produce a different hash than the bytes actually present in the torrent.
        $contents = 'd8:announce3:url4:infod4:name4:test6:lengthi1eee';

        $torrent = Torrent::createFromString($contents, new Decoder());

        $this->assertSame(\sha1('d4:name4:test6:lengthi1ee'), $torrent->getHash());
    }

    public function testGetEncodedHash(): void
    {
        $this->assertSame(
            '%C7%17%BF%D3%02%11%A5%E3l%94%CA%BA%AB%3B%3C%A0%DC%89%F9%1A',
            Torrent::createFromTorrentFile(__DIR__.'/fixtures/multi-file.torrent', new Decoder())->getEncodedHash()
        );
    }

    public function testGetEncodedHashUsesRawUrlEncoding(): void
    {
        // This info hash contains 0x20 and 0x7E bytes: urlencode() would encode them as "+" and "%7E"
        // while rawurlencode() produces "%20" and "~".
        $torrent = Torrent::createFromDictionary(['info' => ['length' => 0, 'name' => 'a']]);
        $hash = $torrent->getHash(true);

        $this->assertSame(\rawurlencode($hash), $torrent->getEncodedHash());
        $this->assertStringContainsString('%20', $torrent->getEncodedHash());
        $this->assertNotSame(\urlencode($hash), $torrent->getEncodedHash());
    }

    public function testGetSizeWithLargeValues(): void
    {
        $decoder = new Decoder();
        $multiFile = Torrent::createFromString(self::encodeTorrent([
            'announce' => 'http://tracker/',
            'info' => [
                'name' => 'large_files',
                'piece length' => 262144,
                'pieces' => \str_repeat("\0", 20),
                'files' => [
                    ['length' => 2147483648, 'path' => ['file1.img']],
                    ['length' => 2147483648, 'path' => ['file2.img']],
                    ['length' => 2147483648, 'path' => ['file3.img']],
                ],
            ],
        ]), $decoder);
        $singleFile = Torrent::createFromString(self::encodeTorrent([
            'announce' => 'http://tracker/',
            'info' => [
                'name' => 'large_file.img',
                'piece length' => 262144,
                'pieces' => \str_repeat("\0", 20),
                'length' => 5368709120,
            ],
        ]), $decoder);

        $this->assertSame(6442450944, $multiFile->getSize());
        $this->assertSame(5368709120, $singleFile->getSize());
    }

    public function testIsPrivateWhenFlagDoesNotExist(): void
    {
        $torrent = Torrent::createFromString(self::encodeTorrent([
            'announce' => 'http://tracker/',
            'info' => ['name' => 'some', 'length' => 1],
        ]), new Decoder());

        $this->assertFalse($torrent->isPrivate());
    }

    public function testIsPrivateWhenItExistsAndIs1(): void
    {
        $torrent = Torrent::createFromString(self::encodeTorrent([
            'announce' => 'http://tracker/',
            'info' => ['name' => 'some', 'length' => 1, 'private' => 1],
        ]), new Decoder());

        $this->assertTrue($torrent->isPrivate());
    }

    public function testIsPrivateWhenItExistsAndIsNot1(): void
    {
        $torrent = Torrent::createFromString(self::encodeTorrent([
            'announce' => 'http://tracker/',
            'info' => ['name' => 'some', 'length' => 1, 'private' => 0],
        ]), new Decoder());

        $this->assertFalse($torrent->isPrivate());
    }
}
