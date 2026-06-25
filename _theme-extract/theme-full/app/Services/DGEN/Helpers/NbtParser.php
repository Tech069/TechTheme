<?php

namespace Pterodactyl\Services\DGEN\Helpers;

class NbtParser
{
    private const TAG_END = 0;
    private const TAG_BYTE = 1;
    private const TAG_SHORT = 2;
    private const TAG_INT = 3;
    private const TAG_LONG = 4;
    private const TAG_FLOAT = 5;
    private const TAG_DOUBLE = 6;
    private const TAG_BYTE_ARRAY = 7;
    private const TAG_STRING = 8;
    private const TAG_LIST = 9;
    private const TAG_COMPOUND = 10;
    private const TAG_INT_ARRAY = 11;
    private const TAG_LONG_ARRAY = 12;

    private string $data;
    private int $offset = 0;
    private int $length;

    public function __construct()
    {
    }

    /**
     * Parse NBT data from a binary string.
     */
    public function parse(string $data): array
    {
        $this->data = $data;
        $this->offset = 0;
        $this->length = strlen($data);

        if ($this->length === 0) {
            return ['root' => []];
        }

        // Check for GZip magic number
        if (ord($data[0]) === 0x1f && ord($data[1]) === 0x8b) {
            $decompressed = @gzuncompress($data);
            if ($decompressed === false) {
                $decompressed = @gzdecode($data);
            }
            if ($decompressed !== false) {
                $this->data = $decompressed;
                $this->length = strlen($this->data);
            }
        }

        $tagType = $this->readByte();
        $name = $this->readString();
        $value = $this->readTagPayload($tagType);

        return [
            'root' => [
                'name' => $name,
                'type' => $tagType,
                'value' => $value,
            ],
        ];
    }

    /**
     * Parse NBT data from a file.
     */
    public function parseFile(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new \FileNotFoundException("NBT file not found: $filePath");
        }

        $data = file_get_contents($filePath);

        if ($data === false) {
            throw new \RuntimeException("Failed to read NBT file: $filePath");
        }

        return $this->parse($data);
    }

    /**
     * Read the payload for a specific tag type.
     */
    private function readTagPayload(int $tagType): mixed
    {
        return match ($tagType) {
            self::TAG_END => null,
            self::TAG_BYTE => $this->readByte(),
            self::TAG_SHORT => $this->readShort(),
            self::TAG_INT => $this->readInt(),
            self::TAG_LONG => $this->readLong(),
            self::TAG_FLOAT => $this->readFloat(),
            self::TAG_DOUBLE => $this->readDouble(),
            self::TAG_BYTE_ARRAY => $this->readByteArray(),
            self::TAG_STRING => $this->readString(),
            self::TAG_LIST => $this->readList(),
            self::TAG_COMPOUND => $this->readCompound(),
            self::TAG_INT_ARRAY => $this->readIntArray(),
            self::TAG_LONG_ARRAY => $this->readLongArray(),
            default => throw new \RuntimeException("Unknown NBT tag type: $tagType"),
        };
    }

    /**
     * Read a single byte.
     */
    private function readByte(): int
    {
        if ($this->offset >= $this->length) {
            throw new \RuntimeException('Unexpected end of NBT data');
        }

        $byte = ord($this->data[$this->offset]);
        $this->offset += 1;

        // Signed byte
        return $byte > 127 ? $byte - 256 : $byte;
    }

    /**
     * Read a short (2 bytes, big-endian, signed).
     */
    private function readShort(): int
    {
        $this->ensureAvailable(2);
        $value = unpack('n', substr($this->data, $this->offset, 2))[1];
        $this->offset += 2;

        return $value > 32767 ? $value - 65536 : $value;
    }

    /**
     * Read an int (4 bytes, big-endian, signed).
     */
    private function readInt(): int
    {
        $this->ensureAvailable(4);
        $value = unpack('N', substr($this->data, $this->offset, 4))[1];
        $this->offset += 4;

        return $value > 2147483647 ? $value - 4294967296 : $value;
    }

    /**
     * Read a long (8 bytes, big-endian, signed).
     */
    private function readLong(): int
    {
        $this->ensureAvailable(8);
        $high = $this->readInt();
        $low = unpack('N', substr($this->data, $this->offset, 4))[1];
        $this->offset += 4;

        return ($high << 32) | $low;
    }

    /**
     * Read a float (4 bytes, big-endian).
     */
    private function readFloat(): float
    {
        $this->ensureAvailable(4);
        $value = unpack('G', substr($this->data, $this->offset, 4))[1];
        $this->offset += 4;

        return $value;
    }

    /**
     * Read a double (8 bytes, big-endian).
     */
    private function readDouble(): float
    {
        $this->ensureAvailable(8);
        $value = unpack('E', substr($this->data, $this->offset, 8))[1];
        $this->offset += 8;

        return $value;
    }

    /**
     * Read a byte array.
     */
    private function readByteArray(): array
    {
        $length = $this->readInt();
        $this->ensureAvailable($length);
        $bytes = [];

        for ($i = 0; $i < $length; $i++) {
            $bytes[] = $this->readByte();
        }

        return $bytes;
    }

    /**
     * Read a string (2-byte length prefix + UTF-8 data).
     */
    private function readString(): string
    {
        $length = $this->readShort();

        if ($length < 0) {
            return '';
        }

        $this->ensureAvailable($length);
        $string = substr($this->data, $this->offset, $length);
        $this->offset += $length;

        return $string;
    }

    /**
     * Read a list (tag type + count + payloads).
     */
    private function readList(): array
    {
        $tagType = $this->readByte();
        $count = $this->readInt();
        $items = [];

        for ($i = 0; $i < $count; $i++) {
            $items[] = $this->readTagPayload($tagType);
        }

        return [
            'type' => $tagType,
            'items' => $items,
        ];
    }

    /**
     * Read a compound (series of named tags until TAG_END).
     */
    private function readCompound(): array
    {
        $compound = [];

        while (true) {
            $tagType = $this->readByte();

            if ($tagType === self::TAG_END) {
                break;
            }

            $name = $this->readString();
            $compound[$name] = [
                'type' => $tagType,
                'value' => $this->readTagPayload($tagType),
            ];
        }

        return $compound;
    }

    /**
     * Read an int array.
     */
    private function readIntArray(): array
    {
        $length = $this->readInt();
        $ints = [];

        for ($i = 0; $i < $length; $i++) {
            $ints[] = $this->readInt();
        }

        return $ints;
    }

    /**
     * Read a long array.
     */
    private function readLongArray(): array
    {
        $length = $this->readInt();
        $longs = [];

        for ($i = 0; $i < $length; $i++) {
            $longs[] = $this->readLong();
        }

        return $longs;
    }

    /**
     * Ensure that enough bytes are available.
     */
    private function ensureAvailable(int $count): void
    {
        if ($this->offset + $count > $this->length) {
            throw new \RuntimeException(sprintf(
                'Unexpected end of NBT data at offset %d (need %d bytes, have %d)',
                $this->offset,
                $count,
                $this->length - $this->offset
            ));
        }
    }
}
