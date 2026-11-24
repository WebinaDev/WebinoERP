<?php

namespace Modules\Platform\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Modules\Platform\Entities\PlatformMetricSnapshot;
use Modules\Platform\Entities\PlatformServer;
use Symfony\Component\Process\Process;
use Throwable;

class PlatformServerMetricsCollector
{
    public function __construct(
        protected SshExecutor $ssh,
        protected PlatformAlertEvaluator $evaluator
    ) {}

    /**
     * Collect all metrics for a given server, store snapshots, and trigger alert evaluation.
     *
     * @return array<string, mixed>
     */
    public function collect(PlatformServer $server): array
    {
        $raw = $this->gatherRawMetrics($server);
        $collectedAt = Carbon::now();

        $snapshots = [];
        foreach ($raw as $key => $data) {
            $value = (float) $data['value'];
            $unit = $data['unit'] ?? 'count';
            $labels = $data['labels'] ?? null;

            PlatformMetricSnapshot::query()->create([
                'server_id' => $server->id,
                'scope' => 'server',
                'metric_key' => $key,
                'value' => $value,
                'unit' => $unit,
                'labels' => $labels,
                'collected_at' => $collectedAt,
            ]);

            $snapshots[$key] = [
                'value' => $value,
                'unit' => $unit,
                'labels' => $labels,
            ];
        }

        $server->update([
            'last_seen_at' => $collectedAt,
            'status' => 'ready',
        ]);

        // Trigger alert rules evaluation
        try {
            $this->evaluator->evaluateServer($server);
        } catch (Throwable $e) {
            Log::warning('PlatformAlertEvaluator failed for server '.$server->id.': '.$e->getMessage());
        }

        return [
            'server_id' => $server->id,
            'collected_at' => $collectedAt->toIso8601String(),
            'metrics' => $snapshots,
        ];
    }

    /**
     * Execute commands either locally or via SSH and parse metrics.
     *
     * @return array<string, array{value: float, unit: string, labels?: array}>
     */
    protected function gatherRawMetrics(PlatformServer $server): array
    {
        // One-liner command to extract system statistics reliably on Linux
        $cmd = <<<'SH'
# Uptime seconds
UPTIME=$(cat /proc/uptime 2>/dev/null | awk '{print int($1)}')
[ -z "$UPTIME" ] && UPTIME=$(awk '{print int($1)}' /proc/uptime 2>/dev/null || echo 0)

# Load averages
LOADS=$(cat /proc/loadavg 2>/dev/null | awk '{print $1,$2,$3}')

# Memory
MEM_TOTAL=0; MEM_AVAIL=0; MEM_FREE=0; SWAP_TOTAL=0; SWAP_FREE=0
while read -r key val rest; do
    case "$key" in
        MemTotal:) MEM_TOTAL=$((val * 1024)) ;;
        MemAvailable:) MEM_AVAIL=$((val * 1024)) ;;
        MemFree:) MEM_FREE=$((val * 1024)) ;;
        SwapTotal:) SWAP_TOTAL=$((val * 1024)) ;;
        SwapFree:) SWAP_FREE=$((val * 1024)) ;;
    esac
done < /proc/meminfo

# CPU usage % via top or /proc/stat
CPU_IDLE=$(top -bn1 2>/dev/null | grep -i "Cpu(s)" | sed "s/.*, *\([0-9.]*\)%* id.*/\1/" | awk '{print 100 - $1}')
if [ -z "$CPU_IDLE" ] || [ "$CPU_IDLE" = "100" ]; then
    CPU_IDLE=$(vmstat 1 2 2>/dev/null | tail -1 | awk '{print 100 - $15}')
fi
[ -z "$CPU_IDLE" ] && CPU_IDLE=0

# Disk / mount
DISK_INFO=$(df -B1 -P / 2>/dev/null | tail -1 | awk '{print $2,$3,$4,$5}')
INODE_INFO=$(df -i -P / 2>/dev/null | tail -1 | awk '{print $2,$3,$4,$5}')

# Network rx/tx bytes on default/first interface
NET_LINE=$(grep -E '^(eth0|ens|enp|eth|wlan|venet)' /proc/net/dev 2>/dev/null | head -1 | tr ':' ' ')
if [ -z "$NET_LINE" ]; then
    NET_LINE=$(cat /proc/net/dev 2>/dev/null | tail -n +3 | grep -v 'lo:' | head -1 | tr ':' ' ')
fi
NET_IFACE=$(echo "$NET_LINE" | awk '{print $1}')
NET_RX=$(echo "$NET_LINE" | awk '{print $2}')
NET_TX=$(echo "$NET_LINE" | awk '{print $10}')
[ -z "$NET_RX" ] && NET_RX=0
[ -z "$NET_TX" ] && NET_TX=0

echo "UPTIME=$UPTIME"
echo "LOADS=$LOADS"
echo "MEM_TOTAL=$MEM_TOTAL"
echo "MEM_AVAIL=$MEM_AVAIL"
echo "MEM_FREE=$MEM_FREE"
echo "SWAP_TOTAL=$SWAP_TOTAL"
echo "SWAP_FREE=$SWAP_FREE"
echo "CPU_USAGE=$CPU_IDLE"
echo "DISK_INFO=$DISK_INFO"
echo "INODE_INFO=$INODE_INFO"
echo "NET_IFACE=$NET_IFACE"
echo "NET_RX=$NET_RX"
echo "NET_TX=$NET_TX"
SH;

        $stdout = '';
        if ($server->is_localhost || in_array($server->ip, ['127.0.0.1', 'localhost', '::1'], true)) {
            $process = new Process(['sh', '-c', $cmd]);
            $process->setTimeout(30);
            $process->run();
            $stdout = $process->getOutput();
        } else {
            $res = $this->ssh->run($server, $cmd, 30);
            $stdout = $res['stdout'];
        }

        return $this->parseOutput($stdout);
    }

    protected function parseOutput(string $output): array
    {
        $vars = [];
        $lines = explode("\n", trim($output));
        foreach ($lines as $line) {
            if (str_contains($line, '=')) {
                [$k, $v] = explode('=', trim($line), 2);
                $vars[$k] = $v;
            }
        }

        // Parse uptime
        $uptime = (float) ($vars['UPTIME'] ?? 0);

        // Parse loads
        $loads = preg_split('/\s+/', trim($vars['LOADS'] ?? '0 0 0'));
        $load1 = (float) ($loads[0] ?? 0);
        $load5 = (float) ($loads[1] ?? 0);
        $load15 = (float) ($loads[2] ?? 0);

        // Parse Memory
        $memTotal = (float) ($vars['MEM_TOTAL'] ?? 0);
        $memAvail = (float) ($vars['MEM_AVAIL'] ?? ($vars['MEM_FREE'] ?? 0));
        $memUsed = max(0.0, $memTotal - $memAvail);
        $memPercent = $memTotal > 0 ? round(($memUsed / $memTotal) * 100, 2) : 0.0;

        // Swap
        $swapTotal = (float) ($vars['SWAP_TOTAL'] ?? 0);
        $swapFree = (float) ($vars['SWAP_FREE'] ?? 0);
        $swapUsed = max(0.0, $swapTotal - $swapFree);

        // CPU
        $cpuUsage = min(100.0, max(0.0, (float) ($vars['CPU_USAGE'] ?? 0)));

        // Disk (bytes)
        $diskParts = preg_split('/\s+/', trim($vars['DISK_INFO'] ?? '0 0 0 0%'));
        $diskTotal = (float) ($diskParts[0] ?? 0);
        $diskUsed = (float) ($diskParts[1] ?? 0);
        $diskPercent = $diskTotal > 0 ? round(($diskUsed / $diskTotal) * 100, 2) : 0.0;

        // Inodes
        $inodeParts = preg_split('/\s+/', trim($vars['INODE_INFO'] ?? '0 0 0 0%'));
        $inodeTotal = (float) ($inodeParts[0] ?? 0);
        $inodeUsed = (float) ($inodeParts[1] ?? 0);
        $inodePercent = $inodeTotal > 0 ? round(($inodeUsed / $inodeTotal) * 100, 2) : 0.0;

        // Net
        $netRx = (float) ($vars['NET_RX'] ?? 0);
        $netTx = (float) ($vars['NET_TX'] ?? 0);
        $netIface = $vars['NET_IFACE'] ?? 'eth0';

        return [
            'cpu.usage' => ['value' => $cpuUsage, 'unit' => 'percent'],
            'load.1' => ['value' => $load1, 'unit' => 'count'],
            'load.5' => ['value' => $load5, 'unit' => 'count'],
            'load.15' => ['value' => $load15, 'unit' => 'count'],
            'mem.total' => ['value' => $memTotal, 'unit' => 'bytes'],
            'mem.used' => ['value' => $memUsed, 'unit' => 'bytes'],
            'mem.available' => ['value' => $memAvail, 'unit' => 'bytes'],
            'mem.usage_percent' => ['value' => $memPercent, 'unit' => 'percent'],
            'swap.total' => ['value' => $swapTotal, 'unit' => 'bytes'],
            'swap.used' => ['value' => $swapUsed, 'unit' => 'bytes'],
            'disk.total' => ['value' => $diskTotal, 'unit' => 'bytes', 'labels' => ['mount' => '/']],
            'disk.used' => ['value' => $diskUsed, 'unit' => 'bytes', 'labels' => ['mount' => '/']],
            'disk.usage_percent' => ['value' => $diskPercent, 'unit' => 'percent', 'labels' => ['mount' => '/']],
            'inode.total' => ['value' => $inodeTotal, 'unit' => 'count', 'labels' => ['mount' => '/']],
            'inode.used' => ['value' => $inodeUsed, 'unit' => 'count', 'labels' => ['mount' => '/']],
            'inode.usage_percent' => ['value' => $inodePercent, 'unit' => 'percent', 'labels' => ['mount' => '/']],
            'net.rx_bytes' => ['value' => $netRx, 'unit' => 'bytes', 'labels' => ['interface' => $netIface]],
            'net.tx_bytes' => ['value' => $netTx, 'unit' => 'bytes', 'labels' => ['interface' => $netIface]],
            'uptime_seconds' => ['value' => $uptime, 'unit' => 'seconds'],
        ];
    }
}
