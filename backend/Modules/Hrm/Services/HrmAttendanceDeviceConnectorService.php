<?php

namespace Modules\Hrm\Services;

use Carbon\Carbon;
use Modules\Hrm\Entities\HrmAttendanceDevice;
use Modules\Hrm\Entities\HrmAttendancePunch;

class HrmAttendanceDeviceConnectorService
{
    public function __construct(
        private readonly HrmAttendanceIngestService $ingestService
    ) {}

    /**
     * Ingest native ZKTeco push protocol payload (IClock ADMS/Push format)
     * format: stamp \t pin \t status \t verify
     *
     * @return array{applied: int, unmatched: int, conflict: int, duplicate: int, invalid: int}
     */
    public function ingestZktecoPushStream(HrmAttendanceDevice $device, string $streamData): array
    {
        $lines = preg_split('/[\r\n]+/', trim($streamData));
        $punches = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            // Standard ZK tab or comma separated format: PIN, TIME, STATE
            $parts = preg_split('/[\t,]+/', $line);
            if (count($parts) >= 2) {
                $rawCode = trim($parts[0]);
                $timeStr = trim($parts[1]);
                $state = isset($parts[2]) ? (int) $parts[2] : 0; // 0=in, 1=out

                $punches[] = [
                    'device_user_id' => $rawCode,
                    'punched_at' => $timeStr,
                    'direction' => $state === 1 ? 'out' : 'in',
                    'raw_code' => $rawCode,
                ];
            }
        }

        return $this->ingestService->ingest($device, $punches, 'zkteco_push');
    }

    /**
     * Ingest Suprema BioStar 2 REST/Webhook Event payload
     *
     * @param array<string, mixed> $payload
     * @return array{applied: int, unmatched: int, conflict: int, duplicate: int, invalid: int}
     */
    public function ingestSupremaEvent(HrmAttendanceDevice $device, array $payload): array
    {
        $events = $payload['events'] ?? [$payload];
        $punches = [];

        foreach ($events as $evt) {
            $userId = (string) ($evt['user_id'] ?? $evt['userId'] ?? '');
            $datetime = (string) ($evt['datetime'] ?? $evt['timestamp'] ?? '');
            $inOut = (string) ($evt['direction'] ?? 'in');

            if ($userId !== '' && $datetime !== '') {
                $punches[] = [
                    'device_user_id' => $userId,
                    'punched_at' => $datetime,
                    'direction' => strtolower($inOut) === 'out' ? 'out' : 'in',
                    'raw_code' => $userId,
                ];
            }
        }

        return $this->ingestService->ingest($device, $punches, 'suprema_biostar');
    }
}
