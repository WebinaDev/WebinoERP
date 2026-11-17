<?php

namespace Modules\DeviceGateway\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\DeviceGateway\Entities\DevGateway;
use Modules\DeviceGateway\Entities\DevPeripheral;
use Modules\DeviceGateway\Entities\DevPrintJob;
use Modules\DeviceGateway\Services\DeviceDispatcherService;

class DeviceGatewayController extends Controller
{
    public function gateways(Request $request): JsonResponse
    {
        $query = DevGateway::query()->with(['company', 'branch', 'warehouse', 'peripherals']);
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return response()->json(['data' => $query->get()]);
    }

    public function storeGateway(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:160',
            'os_platform' => 'required|in:windows,linux,macos,android,ios',
            'company_id' => 'nullable|exists:crm_companies,id',
            'branch_id' => 'nullable|exists:org_branches,id',
            'warehouse_id' => 'nullable|exists:scm_warehouses,id',
            'ip_address' => 'nullable|ip',
        ]);

        $gatewayKey = 'GW-'.strtoupper(bin2hex(random_bytes(6)));

        $gw = DevGateway::query()->create(array_merge($data, [
            'gateway_key' => $gatewayKey,
            'status' => 'online',
            'last_heartbeat_at' => now(),
        ]));

        return response()->json(['data' => $gw->load(['company', 'branch'])], 201);
    }

    public function heartbeat(Request $request, string $gatewayKey): JsonResponse
    {
        $gw = DevGateway::query()->where('gateway_key', $gatewayKey)->firstOrFail();
        $gw->update([
            'status' => 'online',
            'last_heartbeat_at' => now(),
            'ip_address' => $request->ip(),
        ]);

        // Return pending print jobs for this gateway
        $peripheralIds = $gw->peripherals()->pluck('id')->toArray();
        $pendingJobs = DevPrintJob::query()
            ->whereIn('peripheral_id', $peripheralIds)
            ->where('status', 'pending')
            ->get();

        return response()->json([
            'data' => [
                'acknowledged' => true,
                'pending_jobs' => $pendingJobs,
            ],
        ]);
    }

    public function peripherals(Request $request): JsonResponse
    {
        $query = DevPeripheral::query()->with('gateway');
        if ($request->filled('device_type')) {
            $query->where('device_type', $request->input('device_type'));
        }

        return response()->json(['data' => $query->get()]);
    }

    public function storePeripheral(Request $request): JsonResponse
    {
        $data = $request->validate([
            'gateway_id' => 'required|exists:dev_gateways,id',
            'device_type' => 'required|in:label_printer,receipt_printer,barcode_scanner,scale,pos_terminal',
            'name' => 'required|string|max:160',
            'model' => 'nullable|string|max:120',
            'connection_type' => 'required|in:usb,network,serial,bluetooth',
            'port_identifier' => 'nullable|string|max:80',
            'config' => 'nullable|array',
            'is_default' => 'nullable|boolean',
        ]);

        $peri = DevPeripheral::query()->create($data);

        return response()->json(['data' => $peri->load('gateway')], 201);
    }

    public function printJobs(Request $request): JsonResponse
    {
        $query = DevPrintJob::query()->with(['peripheral.gateway', 'creator']);

        return response()->json(['data' => $query->orderByDesc('id')->paginate(30)]);
    }

    public function dispatchPrint(Request $request, DeviceDispatcherService $dispatcher): JsonResponse
    {
        $data = $request->validate([
            'peripheral_id' => 'required|exists:dev_peripherals,id',
            'job_type' => 'required|in:barcode_label,receipt,packing_slip,shipping_label',
            'payload' => 'required|string',
        ]);

        $job = $dispatcher->dispatchPrint(
            (int) $data['peripheral_id'],
            $data['job_type'],
            $data['payload'],
            $request->user()->id
        );

        return response()->json(['data' => $job->load('peripheral')], 201);
    }
}
