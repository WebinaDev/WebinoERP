<?php

namespace Modules\Hrm\Support;

/**
 * Minimal dBase III (0x03) writer, pure PHP. Character (C) and numeric (N) fields only, no memo file.
 * Text is converted to a single-byte code page (default Windows-1256 / Arabic, LDID 0x57)
 * because DBF has no UTF-8 field type.
 */
class DbfWriter
{
    /** dBase language driver ids */
    private const LDID = ['CP1256' => 0x57, 'CP1252' => 0x03];

    /**
     * @param  list<array{0: string, 1: string, 2: int, 3?: int}>  $fields  [name, type C|N, length, decimals]
     * @param  list<array<string, mixed>>  $records
     */
    public static function build(array $fields, array $records, string $encoding = 'CP1256'): string
    {
        $encoding = strtoupper($encoding);
        $recordLength = 1;
        foreach ($fields as $f) {
            $recordLength += (int) $f[2];
        }
        $headerLength = 32 + 32 * count($fields) + 1;
        $now = getdate();

        $header = pack(
            'CCCCVvv',
            0x03,
            $now['year'] - 1900,
            $now['mon'],
            $now['mday'],
            count($records),
            $headerLength,
            $recordLength
        );
        $header .= str_repeat("\0", 17);
        $header .= chr(self::LDID[$encoding] ?? 0x00);
        $header .= str_repeat("\0", 2);

        $descriptors = '';
        foreach ($fields as $f) {
            [$name, $type, $len] = $f;
            $dec = (int) ($f[3] ?? 0);
            $descriptors .= str_pad(substr(strtoupper($name), 0, 10), 11, "\0");
            $descriptors .= $type;
            $descriptors .= str_repeat("\0", 4);
            $descriptors .= chr((int) $len).chr($dec);
            $descriptors .= str_repeat("\0", 14);
        }

        $body = '';
        foreach ($records as $record) {
            $body .= ' ';
            foreach ($fields as $f) {
                [$name, $type, $len] = $f;
                $dec = (int) ($f[3] ?? 0);
                $value = $record[$name] ?? null;
                $body .= $type === 'N'
                    ? self::numeric($value, (int) $len, $dec)
                    : self::character($value, (int) $len, $encoding);
            }
        }

        return $header.$descriptors."\r".$body."\x1A";
    }

    private static function numeric(mixed $value, int $len, int $dec): string
    {
        if ($value === null || $value === '') {
            return str_repeat(' ', $len);
        }
        $text = $dec > 0
            ? number_format((float) $value, $dec, '.', '')
            : (string) (int) round((float) $value);
        if (strlen($text) > $len) {
            $text = str_repeat('*', $len);
        }

        return str_pad($text, $len, ' ', STR_PAD_LEFT);
    }

    private static function character(mixed $value, int $len, string $encoding): string
    {
        $text = (string) ($value ?? '');
        if ($encoding !== 'UTF-8' && $text !== '') {
            // Persian yeh (U+06CC) is not in cp1256; use Arabic yeh (U+064A), as Iranian DBF tools do.
            $text = strtr($text, ['ی' => 'ي']);
            $converted = @iconv('UTF-8', $encoding.'//TRANSLIT//IGNORE', $text);
            $text = $converted === false ? '' : $converted;
        }
        if (strlen($text) > $len) {
            $text = substr($text, 0, $len);
        }

        return str_pad($text, $len, ' ', STR_PAD_RIGHT);
    }

    /**
     * Read back records (used by tests and for sanity checks).
     *
     * @return array{fields: list<array{name: string, type: string, length: int}>, records: list<array<string, string>>}
     */
    public static function read(string $bytes, string $encoding = 'CP1256'): array
    {
        $count = unpack('V', substr($bytes, 4, 4))[1];
        $headerLength = unpack('v', substr($bytes, 8, 2))[1];
        $recordLength = unpack('v', substr($bytes, 10, 2))[1];
        $fields = [];
        for ($off = 32; $off < $headerLength - 1; $off += 32) {
            $chunk = substr($bytes, $off, 32);
            if ($chunk === '' || $chunk[0] === "\r") {
                break;
            }
            $fields[] = [
                'name' => rtrim(substr($chunk, 0, 11), "\0"),
                'type' => $chunk[11],
                'length' => ord($chunk[16]),
            ];
        }
        $records = [];
        for ($i = 0; $i < $count; $i++) {
            $row = substr($bytes, $headerLength + $i * $recordLength, $recordLength);
            $pos = 1;
            $rec = [];
            foreach ($fields as $f) {
                $raw = substr($row, $pos, $f['length']);
                $pos += $f['length'];
                $value = trim($raw);
                if ($f['type'] === 'C' && $encoding !== 'UTF-8' && $value !== '') {
                    $value = (string) @iconv($encoding, 'UTF-8//IGNORE', $value);
                }
                $rec[$f['name']] = $value;
            }
            $records[] = $rec;
        }

        return ['fields' => $fields, 'records' => $records];
    }
}
