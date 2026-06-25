<?php

namespace Pterodactyl\Services\DGEN\Helpers;

class NbtWriter
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

    private string $buffer = '';

    public function __construct()
    {
    }

    /**
     * Write NBT data from an array to a binary string.
     */
    public function write(array $data, string $rootName = '', bool $compress = true): string
    {
        $this->buffer = '';

        $root = $data['root'] ?? $data;
        $name = $root['name'] ?? $rootName;
        $value = $root['value'] ?? $root;
        $type = $root['type'] ?? self::TAG_COMPOUND;

        $this->writeByte($type);
        $this->writeString($name);
        $this->writeTagPayload($type, $value);

        $result = $this->buffer;

        if ($compress) {
            $compressed = @gzcompress($result);
            if ($compressed !== false) {
                return $compressed;
            }
        }

        return $result;
    }

    /**
     * Write NBT data to a file.
     */
    public function writeToFile(array $data, string $filePath, string $rootName = '', bool $compress = true): bool
    {
        $nbtData = $this->write($data, $rootName, $compress);
        $directory = dirname($filePath);

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        return file_put_contents($filePath, $nbtData) !== false;
    }

    /**
     * Write the payload for a specific tag type.
     */
    private function writeTagPayload(int $tagType, mixed $value): void
    {
        match ($tagType) {
            self::TAG_END => null,
            self::TAG_BYTE => $this->writeByte((int) $value),
            self::TAG_SHORT => $this->writeShort((int) $value),
            self::TAG_INT => $this->writeInt((int) $value),
            self::TAG_LONG => $this->writeLong((int) $value),
            self::TAG_FLOAT => $this->writeFloat((float) $value),
            self::TAG_DOUBLE => $this->writeDouble((float) $value),
            self::TAG_BYTE_ARRAY => $this->writeByteArray($value),
            self::TAG_STRING => $this->writeString($value),
            self::TAG_LIST => $this->writeList($value),
            self::TAG_COMPOUND => $this->writeCompound($value),
            self::TAG_INT_ARRAY => $this->writeIntArray($value),
            self::TAG_LONG_ARRAY => $this->writeLongArray($value),
            default => throw new \RuntimeException("Unknown NBT tag type: $tagType"),
        };
    }

    /**
     * Write a single byte.
     */
    private function writeByte(int $value): void
    {
        $this->buffer .= pack('C', $value & 0xFF);
    }

    /**
     * Write a short (2 bytes, big-endian, signed).
     */
    private function writeShort(int $value): void
    {
        $this->buffer .= pack('n', $value & 0xFFFF);
    }

    /**
     * Write an int (4 bytes, big-endian, signed).
     */
    private function writeInt(int $value): void
    {
        $this->buffer .= pack('N', $value);
    }

    /**
     * Write a long (8 bytes, big-endian, signed).
     */
    private function writeLong(int $value): void
    {
        $this->writeInt(($value >> 32) & 0xFFFFFFFF);
        $this->writeInt($value & 0xFFFFFFFF);
    }

    /**
     * Write a float (4 bytes, big-endian).
     */
    private function writeFloat(float $value): void
    {
        $this->buffer .= pack('G', $value);
    }

    /**
     * Write a double (8 bytes, big-endian).
     */
    private function writeDouble(float $value): void
    {
        $this->buffer .= pack('E', $value);
    }

    /**
     * Write a byte array.
     */
    private function writeByteArray(array $bytes): void
    {
        $this->writeInt(count($bytes));

        foreach ($bytes as $byte) {
            $this->writeByte($byte);
        }
    }

    /**
     * Write a string (2-byte length prefix + UTF-8 data).
     */
    private function writeString(string $string): void
    {
        $bytes = strlen($string);
        $this->writeShort($bytes);
        $this->buffer .= $string;
    }

    /**
     * Write a list (tag type + count + payloads).
     */
    private function writeList(array $list): void
    {
        $items = $list['items'] ?? $list;
        $type = $list['type'] ?? self::TAG_BYTE;

        $this->writeByte($type);
        $this->writeInt(count($items));

        foreach ($items as $item) {
            $this->writeTagPayload($type, $item);
        }
    }

    /**
     * Write a compound (series of named tags terminated by TAG_END).
     */
    private function writeCompound(array $compound): void
    {
        foreach ($compound as $name => $entry) {
            $type = $entry['type'] ?? $this->detectType($entry);
            $value = $entry['value'] ?? $entry;

            $this->writeByte($type);
            $this->writeString($name);
            $this->writeTagPayload($type, $value);
        }

        $this->writeByte(self::TAG_END);
    }

    /**
     * Write an int array.
     */
    private function writeIntArray(array $ints): void
    {
        $this->writeInt(count($ints));

        foreach ($ints as $int) {
            $this->writeInt($int);
        }
    }

    /**
     * Write a long array.
     */
    private function writeLongArray(array $longs): void
    {
        $this->writeInt(count($longs));

        foreach ($longs as $long) {
            $this->writeLong($long);
        }
    }

    /**
     * Detect the NBT tag type from a value.
     */
    private function detectType(mixed $value): int
    {
        if (is_int($value)) {
            return self::TAG_INT;
        }
        if (is_float($value)) {
            return self::TAG_DOUBLE;
        }
        if (is_string($value)) {
            return self::TAG_STRING;
        }
        if (is_array($value)) {
            if (!empty($value) && isset($value['type'])) {
                return $value['type'];
            }

            return self::TAG_COMPOUND;
        }

        return self::TAG_BYTE;
    }

    /**
     * Get the current buffer contents.
     */
    public function getBuffer(): string
    {
        return $this->buffer;
    }

    /**
     * Reset the buffer.
     */
    public function reset(): void
    {
        $this->buffer = '';
    }
}
