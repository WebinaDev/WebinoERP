<?php

namespace Modules\Integrations\Services;

/**
 * Normalizes IPPanel Edge list payloads (outbox, inbox, lines) into the
 * shape the site dashboard and reports tables already render.
 */
class ModirPayamakReportRows
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function messages(mixed $data): array
    {
        $out = [];
        foreach (self::extract($data) as $row) {
            $out[] = self::message($row);
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function lines(mixed $data): array
    {
        $out = [];
        foreach (self::extract($data) as $row) {
            $line = self::line($row);
            if ($line['number'] !== '') {
                $out[] = $line;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public static function message(array $row): array
    {
        $id = self::scalar($row, ['messages_outbox_id', 'outbox_id', 'bulk_id', 'id']);
        $sender = self::scalar($row, ['number', 'from_number', 'sender', 'line']);
        $body = self::messageText($row);
        $status = self::scalar($row, ['status', 'state']);
        $type = self::scalar($row, ['sending_type', 'type']);
        $recipients = self::recipients($row);
        $time = $row['time'] ?? $row['time_send'] ?? $row['send_time'] ?? $row['created_at'] ?? $row['date'] ?? null;
        $created = self::scalar($row, ['created_at', 'sent_at', 'date']);
        if ($created === '' && is_numeric($time)) {
            $ts = (int) $time;
            if ($ts > 1_000_000_000_000) {
                $ts = (int) floor($ts / 1000);
            }
            $created = $ts > 0 ? gmdate('c', $ts) : '';
        }
        $cost = self::scalar($row, ['cost', 'price', 'amount']);

        return [
            'id' => $id !== '' ? $id : null,
            'messages_outbox_id' => $id !== '' ? $id : null,
            'from_number' => $sender,
            'number' => $sender,
            'message' => $body,
            'recipients' => $recipients,
            'recipient' => $recipients[0] ?? '',
            'sending_type' => $type !== '' ? $type : 'webservice',
            'type' => $type !== '' ? $type : 'webservice',
            'status' => $status !== '' ? $status : 'sent',
            'state' => self::scalar($row, ['state', 'status']),
            'state_id' => is_numeric($row['state_id'] ?? null) ? (int) $row['state_id'] : null,
            'cost' => is_numeric($cost) ? (float) $cost : 0,
            'rcpts_count' => self::scalar($row, ['rcpts_count', 'recipients_count']) ?: (string) count($recipients),
            'exit_count' => self::scalar($row, ['exit_count', 'sent_count']),
            'pattern_code' => self::scalar($row, ['pattern_code', 'code']),
            'pattern_message' => self::messageText($row),
            'time' => $time,
            'created_at' => $created !== '' ? $created : $time,
            'source' => 'edge',
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{id: mixed, number: string, role: string, label: string}
     */
    public static function line(array $row): array
    {
        $number = self::scalar($row, ['number', 'line', 'from_number', 'sender', 'phone']);
        $roleRaw = strtolower(self::scalar($row, ['role', 'type', 'line_type', 'title', 'name']));
        $role = 'service';
        if (preg_match('/personal|marketing|dedicated|webservice|advert|اختصاص/u', $roleRaw)) {
            $role = 'personal';
        } elseif (preg_match('/pattern|shared|خدماتی|پترن|service/u', $roleRaw)) {
            $role = 'service';
        } elseif ($roleRaw !== '') {
            $role = $roleRaw;
        }

        return [
            'id' => $row['id'] ?? null,
            'number' => $number,
            'role' => $role,
            'label' => self::scalar($row, ['label', 'title', 'name']),
        ];
    }

    /**
     * Provider pattern rows with the body always on `message` and `pattern_message`.
     *
     * @return list<array<string, mixed>>
     */
    public static function patterns(mixed $data): array
    {
        $out = [];
        foreach (self::extract($data) as $row) {
            $flat = $row;
            foreach (['pattern', 'data'] as $nest) {
                if (isset($row[$nest]) && is_array($row[$nest]) && ! array_is_list($row[$nest])) {
                    $flat = array_merge($row[$nest], $flat);
                }
            }
            $body = self::messageText($flat);
            $title = self::scalar($flat, ['title', 'pattern_title', 'name', 'description']);
            $code = self::scalar($flat, ['pattern_code', 'code', 'pattern_id']);
            $flat['pattern_code'] = $code;
            $flat['code'] = $code !== '' ? $code : ($flat['code'] ?? null);
            $flat['title'] = $title;
            $flat['message'] = $body;
            $flat['pattern_message'] = $body;
            $out[] = $flat;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function messageText(array $row): string
    {
        $code = self::scalar($row, ['pattern_code', 'code']);
        $found = self::digText($row, ['pattern_message', 'message', 'body', 'text', 'summary', 'content', 'msg']);
        if ($found !== '' && $found !== $code) {
            return $found;
        }
        foreach (['pattern', 'data'] as $nest) {
            if (! isset($row[$nest]) || ! is_array($row[$nest]) || array_is_list($row[$nest])) {
                continue;
            }
            $nested = self::digText($row[$nest], ['pattern_message', 'message', 'body', 'text', 'summary']);
            if ($nested !== '' && $nested !== $code) {
                return $nested;
            }
        }

        return $found;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $keys
     */
    private static function digText(array $row, array $keys): string
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $row) || $row[$key] === null || $row[$key] === '') {
                continue;
            }
            $value = $row[$key];
            if (is_string($value)) {
                $trim = trim($value);
                if ($trim !== '' && (str_starts_with($trim, '{') || str_starts_with($trim, '['))) {
                    $decoded = json_decode($trim, true);
                    if (is_array($decoded)) {
                        $value = $decoded;
                    } else {
                        return $trim;
                    }
                } else {
                    return $trim;
                }
            }
            if (is_array($value) && ! array_is_list($value)) {
                $inner = self::digText($value, ['pattern_message', 'message', 'body', 'text', 'summary', 'content']);
                if ($inner !== '') {
                    return $inner;
                }
            } elseif (is_scalar($value)) {
                $trim = trim((string) $value);
                if ($trim !== '') {
                    return $trim;
                }
            }
        }

        return '';
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function extract(mixed $data): array
    {
        if (! is_array($data)) {
            return [];
        }
        if (self::isList($data)) {
            return self::onlyArrays($data);
        }
        foreach (['entries', 'messages', 'data', 'items', 'list', 'outbox', 'inbox', 'numbers', 'rows', 'result'] as $key) {
            if (! array_key_exists($key, $data)) {
                continue;
            }
            $found = self::extract($data[$key]);
            if ($found !== []) {
                return $found;
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<string>
     */
    private static function recipients(array $row): array
    {
        $raw = $row['recipients'] ?? $row['recipient'] ?? $row['phone'] ?? $row['to'] ?? null;
        if (is_string($raw)) {
            $trim = trim($raw);
            if ($trim === '') {
                return [];
            }
            if (str_starts_with($trim, '[') || str_starts_with($trim, '{')) {
                $decoded = json_decode($trim, true);
                if (is_array($decoded)) {
                    $raw = $decoded;
                } else {
                    return [$trim];
                }
            } else {
                return [$trim];
            }
        }
        if (! is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $item) {
            if (is_scalar($item) && trim((string) $item) !== '') {
                $out[] = trim((string) $item);
            } elseif (is_array($item)) {
                $phone = self::scalar($item, ['number', 'phone', 'recipient', 'mobile']);
                if ($phone !== '') {
                    $out[] = $phone;
                }
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $keys
     */
    private static function scalar(array $row, array $keys): string
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $row) || $row[$key] === null || $row[$key] === '') {
                continue;
            }
            if (is_scalar($row[$key])) {
                return trim((string) $row[$key]);
            }
        }

        return '';
    }

    /**
     * @param  array<mixed>  $data
     */
    private static function isList(array $data): bool
    {
        if ($data === []) {
            return true;
        }

        return array_is_list($data);
    }

    /**
     * @param  list<mixed>  $rows
     * @return list<array<string, mixed>>
     */
    private static function onlyArrays(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $out[] = $row;
            }
        }

        return $out;
    }
}
