<?php

namespace Modules\DeviceGateway\Services;

use Modules\DeviceGateway\Entities\DevPeripheral;
use Modules\DeviceGateway\Entities\DevPrintJob;

class DeviceDispatcherService
{
    /**
     * Dispatch print job to label printer or POS thermal receipt printer
     */
    public function dispatchPrint(int $peripheralId, string $jobType, string $payload, ?int $userId = null): DevPrintJob
    {
        $peripheral = DevPeripheral::query()->findOrFail($peripheralId);

        return DevPrintJob::query()->create([
            'peripheral_id' => $peripheral->id,
            'job_type' => $jobType,
            'payload' => $payload,
            'status' => 'pending',
            'created_by' => $userId,
        ]);
    }

    /**
     * Generate standard ZPL barcode string for product / serial
     */
    public function generateZplBarcode(string $code, string $name, ?string $price = null): string
    {
        return "^XA\n^FO50,50^ADN,36,20^FD{$name}^FS\n^FO50,100^BCN,100,Y,N,N^FD{$code}^FS\n^FO50,220^ADN,25,15^FDPrice: {$price}^FS\n^XZ";
    }
}
