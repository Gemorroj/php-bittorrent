<?php

declare(strict_types=1);

namespace BitTorrent;

final class Torrent
{
    /**
     * Internal encoder.
     */
    private EncoderInterface $encoder;

    /**
     * The exponent to use when making the pieces.
     */
    private int $pieceLengthExp = 18;

    /**
     * The announce URL.
     */
    private ?string $announceUrl = null;

    /**
     * The list of announce URLs.
     *
     * @var array<int, string[]>|null
     */
    private ?array $announceList = null;

    /**
     * Optional comment.
     */
    private ?string $comment = null;

    /**
     * Optional string that informs clients who or what created this torrent.
     */
    private string $createdBy = 'PHP BitTorrent';

    /**
     * The unix timestamp of when the torrent was created.
     */
    private ?int $createdAt = null;

    /**
     * Info about the file(s) in the torrent.
     */
    private ?array $info = null;

    /**
     * Any non-standard fields in the torrent meta data.
     */
    private ?array $extraMeta = null;

    /**
     * Raw bencoded bytes of the info dictionary when the torrent was loaded from a file.
     *
     * Kept so that the info hash is computed from the original data instead of a re-encoded copy.
     */
    private ?string $rawInfo = null;

    /**
     * Class constructor.
     *
     * @param string|null           $announceUrl Initial announce URL
     * @param EncoderInterface|null $encoder     Internal encoder instance
     */
    public function __construct(?string $announceUrl = null, ?EncoderInterface $encoder = null)
    {
        if (null !== $announceUrl) {
            $this->announceUrl = $announceUrl;
        }

        if (null === $encoder) {
            $encoder = new Encoder();
        }

        $this->encoder = $encoder;
    }

    /**
     * Set piece length exponent.
     */
    public function withPieceLengthExp(int $pieceLengthExp): self
    {
        $new = clone $this;
        $new->pieceLengthExp = $pieceLengthExp;

        return $new;
    }

    /**
     * Get the piece length exponent.
     */
    public function getPieceLengthExp(): int
    {
        return $this->pieceLengthExp;
    }

    /**
     * Set the announce URL.
     */
    public function withAnnounceUrl(string $announceUrl): self
    {
        $new = clone $this;
        $new->announceUrl = $announceUrl;

        return $new;
    }

    /**
     * Get the announce URL.
     */
    public function getAnnounceUrl(): ?string
    {
        return $this->announceUrl;
    }

    /**
     * Set the internal encoder.
     */
    public function withEncoder(EncoderInterface $encoder): self
    {
        $new = clone $this;
        $new->encoder = $encoder;

        return $new;
    }

    /**
     * Get the encoder.
     */
    public function getEncoder(): EncoderInterface
    {
        return $this->encoder;
    }

    /**
     * Set announce URL list.
     *
     * @param array<int, string[]> $announceList
     */
    public function withAnnounceList(array $announceList): self
    {
        $new = clone $this;
        $new->announceList = $announceList;

        return $new;
    }

    /**
     * Get the announce list.
     *
     * @return array<int, string[]>|null
     */
    public function getAnnounceList(): ?array
    {
        return $this->announceList;
    }

    /**
     * Set a comment.
     */
    public function withComment(string $comment): self
    {
        $new = clone $this;
        $new->comment = $comment;

        return $new;
    }

    /**
     * Get the comment.
     */
    public function getComment(): ?string
    {
        return $this->comment;
    }

    /**
     * Set the created by field.
     */
    public function withCreatedBy(string $createdBy): self
    {
        $new = clone $this;
        $new->createdBy = $createdBy;

        return $new;
    }

    /**
     * Get the created by string.
     */
    public function getCreatedBy(): string
    {
        return $this->createdBy;
    }

    /**
     * Set the created at field.
     */
    public function withCreatedAt(int $createdAt): self
    {
        $new = clone $this;
        $new->createdAt = $createdAt;

        return $new;
    }

    /**
     * Get the created at field.
     */
    public function getCreatedAt(): ?int
    {
        return $this->createdAt;
    }

    /**
     * Set additional info.
     */
    public function withInfo(array $info): self
    {
        $new = clone $this;
        $new->info = $info;
        $new->rawInfo = null;

        return $new;
    }

    /**
     * Get additional info.
     */
    public function getInfo(): ?array
    {
        return $this->info;
    }

    /**
     * Set the raw bencoded bytes of the info dictionary.
     */
    private function withRawInfo(string $rawInfo): self
    {
        $new = clone $this;
        $new->rawInfo = $rawInfo;

        return $new;
    }

    /**
     * Set extra metadata.
     */
    public function withExtraMeta(array $extra): self
    {
        $new = clone $this;
        $new->extraMeta = $extra;

        return $new;
    }

    /**
     * Get extra metadata.
     */
    public function getExtraMeta(): ?array
    {
        return $this->extraMeta;
    }

    /**
     * Save the current torrent object to the specified filename.
     *
     * This method will save the current object to a file. If the file specified exists it will be
     * overwritten.
     *
     * @param string $path The path to save the file to
     *
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     *
     * @return bool Returns true on success or false on failure
     */
    public function save(string $path): bool
    {
        if (!\is_writable($path) && !\is_writable(\dirname($path))) {
            throw new \InvalidArgumentException(\sprintf('Could not open file "%s" for writing.', $path));
        }

        if (null === $announceUrl = $this->getAnnounceUrl()) {
            throw new \RuntimeException('Announce URL is missing');
        }

        $torrent = [
            'announce' => $announceUrl,
            'creation date' => $this->getCreatedAt() ?? \time(),
            'info' => $this->getInfoPart(),
            'created by' => $this->getCreatedBy(),
        ];

        if (null !== $announceList = $this->getAnnounceList()) {
            $torrent['announce-list'] = $announceList;
        }

        if (null !== $comment = $this->getComment()) {
            $torrent['comment'] = $comment;
        }

        if (null !== ($extra = $this->getExtraMeta())) {
            foreach ($extra as $extraKey => $extraValue) {
                if (\array_key_exists($extraKey, $torrent)) {
                    throw new \RuntimeException(\sprintf('Duplicate key in extra meta info. "%s" already exists.', $extraKey));
                }

                $torrent[$extraKey] = $extraValue;
            }
        }

        $encodedData = $this->encoder->encodeDictionary($torrent);

        return false !== \file_put_contents($path, $encodedData, \LOCK_EX);
    }

    /**
     * Get the list of files in the torrent.
     *
     * Every entry contains the file size ("length") and its "path" as an array of path components.
     * Single file torrents are represented as a list with a single entry as well.
     *
     * @return array<int, array{length: int, path: array<int, string>}>
     */
    public function getFileList(): array
    {
        $info = $this->getInfoPart();

        if (isset($info['length'])) {
            return [
                [
                    'length' => (int) $info['length'],
                    'path' => [(string) ($info['name'] ?? '')],
                ],
            ];
        }

        $files = [];

        foreach ((array) ($info['files'] ?? []) as $file) {
            $files[] = [
                'length' => (int) $file['length'],
                'path' => (array) $file['path'],
            ];
        }

        return $files;
    }

    /**
     * Get file size.
     */
    public function getSize(): int
    {
        $info = $this->getInfoPart();

        if (isset($info['length'])) {
            return (int) $info['length'];
        }

        $size = 0;

        foreach ($this->getFileList() as $file) {
            $size += $file['length'];
        }

        return $size;
    }

    /**
     * Get the name from the info part.
     */
    public function getName(): string
    {
        return (string) ($this->getInfoPart()['name'] ?? '');
    }

    /**
     * Get sha1 hash from the encoded info part.
     *
     * @param bool $raw Whether or not to return the hash in raw format
     */
    public function getHash(bool $raw = false): string
    {
        $info = $this->rawInfo ?? $this->encoder->encodeDictionary($this->getInfoPart());

        return \sha1($info, $raw);
    }

    /**
     * Get the encoded hash.
     */
    public function getEncodedHash(): string
    {
        return \rawurlencode($this->getHash(true));
    }

    /**
     * Get the info part of the torrent.
     */
    private function getInfoPart(): array
    {
        $info = $this->getInfo();

        if (null === $info) {
            throw new \RuntimeException('The info part of the torrent is not set.');
        }

        return $info;
    }

    /**
     * Check if the torrent is private.
     */
    public function isPrivate(): bool
    {
        $info = $this->getInfoPart();

        return isset($info['private']) && 1 === (int) $info['private'];
    }

    /**
     * Create torrent instance from a string.
     *
     * @param bool $strict When true, the contents must contain a non-empty "announce" and "info"
     *                     (see DecoderInterface::decodeFileContents()); otherwise decoding fails
     */
    public static function createFromString(string $contents, DecoderInterface $decoder, bool $strict = false): self
    {
        return static::createFromDecoded($decoder->decodeFileContents($contents, $strict));
    }

    /**
     * Create torrent instance from a torrent file.
     *
     * @param bool $strict When true, the file must contain a non-empty "announce" and "info"
     *                     (see DecoderInterface::decodeFile()); otherwise decoding fails
     */
    public static function createFromTorrentFile(string $path, DecoderInterface $decoder, bool $strict = false): self
    {
        return static::createFromDecoded($decoder->decodeFile($path, $strict));
    }

    /**
     * Create torrent instance from a decoded dictionary.
     */
    private static function createFromDecoded(DecodedDictionary $decoded): self
    {
        return static::createFromDictionary($decoded->getValues(), $decoded->getEncodedValue('info'));
    }

    /**
     * Create torrent instance from a dictionary.
     *
     * @param string|null $rawInfo Raw bencoded bytes of the info dictionary, when available
     */
    public static function createFromDictionary(array $dictionary, ?string $rawInfo = null): self
    {
        $torrent = new self();

        if (isset($dictionary['announce'])) {
            $torrent = $torrent->withAnnounceUrl(\is_array($dictionary['announce']) ? $dictionary['announce'][0] : $dictionary['announce']);
            unset($dictionary['announce']);
        }

        if (isset($dictionary['announce-list'])) {
            $torrent = $torrent->withAnnounceList($dictionary['announce-list']);
            unset($dictionary['announce-list']);
        }

        if (isset($dictionary['comment'])) {
            $torrent = $torrent->withComment($dictionary['comment']);
            unset($dictionary['comment']);
        }

        if (isset($dictionary['created by'])) {
            $torrent = $torrent->withCreatedBy($dictionary['created by']);
            unset($dictionary['created by']);
        }

        if (isset($dictionary['creation date'])) {
            $torrent = $torrent->withCreatedAt((int) $dictionary['creation date']);
            unset($dictionary['creation date']);
        }

        if (isset($dictionary['info'])) {
            $torrent = $torrent->withInfo($dictionary['info']);
            unset($dictionary['info']);
        }

        if (\count($dictionary) > 0) {
            $torrent = $torrent->withExtraMeta($dictionary);
        }

        if (null !== $rawInfo) {
            $torrent = $torrent->withRawInfo($rawInfo);
        }

        return $torrent;
    }

    /**
     * Create torrent instance from local path.
     *
     * @throws \RuntimeException
     */
    public static function createFromPath(string $path, ?string $announceUrl = null): self
    {
        $torrent = new self($announceUrl);
        $files = [];

        $absolutePath = \realpath($path);
        $pathIsFile = false;

        if (false !== $absolutePath && \is_file($absolutePath)) {
            $pathIsFile = true;
            $files[] = [
                'filename' => \basename($absolutePath),
                'filesize' => \filesize($absolutePath),
            ];
        } elseif (false !== $absolutePath && \is_dir($absolutePath)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($absolutePath, \FilesystemIterator::SKIP_DOTS)
            );
            /** @var \SplFileInfo $entry */
            foreach ($iterator as $entry) {
                $files[] = [
                    'filename' => \str_replace($absolutePath.\DIRECTORY_SEPARATOR, '', $entry->getPathname()),
                    'filesize' => $entry->getSize(),
                ];
            }
        } else {
            throw new \InvalidArgumentException(\sprintf('Invalid path: %s', $path));
        }

        $info = [
            'piece length' => 2 ** $torrent->getPieceLengthExp(),
        ];

        $pieces = [];
        $pieceLength = (int) $info['piece length'];

        if ($pathIsFile) {
            $info['name'] = $files[0]['filename'];
            $info['length'] = $files[0]['filesize'];

            $part = '';
            self::appendPieces($absolutePath, $pieceLength, 0, $part, $pieces);

            if ('' !== $part) {
                $pieces[] = \sha1($part, true);
            }
        } else {
            $info['name'] = \basename($absolutePath);

            \usort($files, static function (array $a, array $b): int {
                return \strcmp($a['filename'], $b['filename']);
            });

            $part = '';
            $done = 0;

            // A piece may span multiple files, so pieces are computed over a continuous byte stream
            foreach ($files as $file) {
                $filename = $file['filename'];

                $info['files'][] = [
                    'length' => $file['filesize'],
                    'path' => \explode(\DIRECTORY_SEPARATOR, $filename),
                ];

                $done = self::appendPieces($absolutePath.\DIRECTORY_SEPARATOR.$filename, $pieceLength, $done, $part, $pieces);
            }

            if ('' !== $part) {
                $pieces[] = \sha1($part, true);
            }
        }

        $info['pieces'] = \implode('', $pieces);
        \ksort($info);

        return $torrent->withInfo($info);
    }

    /**
     * Read a file and append all complete pieces to the given list.
     *
     * @param array<int, string> $pieces List of raw sha1 hashes
     * @param string             $part   Buffer of a partially read piece (passed by reference)
     *
     * @return int The number of buffered bytes in the partial piece after reading
     */
    private static function appendPieces(string $path, int $pieceLength, int $done, string &$part, array &$pieces): int
    {
        $fp = \fopen($path, 'r');

        if (false === $fp) {
            throw new \RuntimeException(\sprintf('Failed to open file: %s', $path));
        }

        while (!\feof($fp)) {
            $chunk = \fread($fp, \max(1, $pieceLength - $done));

            if (false === $chunk || '' === $chunk) {
                break;
            }

            $part .= $chunk;
            $done += \strlen($chunk);

            if ($done === $pieceLength) {
                $pieces[] = \sha1($part, true);
                $part = '';
                $done = 0;
            }
        }

        \fclose($fp);

        return $done;
    }
}
