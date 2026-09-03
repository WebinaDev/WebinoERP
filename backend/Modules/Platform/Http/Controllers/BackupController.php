<?php

namespace Modules\Platform\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Platform\Entities\PlatformBackup;
use Modules\Platform\Entities\PlatformBackupSchedule;
use Modules\Platform\Entities\PlatformResource;
use Modules\Platform\Jobs\RestoreDatabaseBackupJob;
use Modules\Platform\Jobs\RunDatabaseBackupJob;

class BackupController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => PlatformBackup::query()->latest()->limit(100)->get(), 'message' => null, 'meta' => null, 'errors' => null]);
    }

    public function schedules(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => PlatformBackupSchedule::query()->latest()->get(), 'message' => null, 'meta' => null, 'errors' => null]);
    }

    public function storeSchedule(Request $request): JsonResponse
    {
        $data = $request->validate([
            'resource_id' => 'required|exists:platform_resources,id',
            'storage_id' => 'nullable|exists:platform_storages,id',
            'cron' => 'required|string|max:64',
            'retention_days' => 'nullable|integer|min:1|max:3650',
            'enabled' => 'nullable|boolean',
        ]);
        $row = PlatformBackupSchedule::query()->create($data);
        return response()->json(['success' => true, 'data' => $row, 'message' => null, 'meta' => null, 'errors' => null], 201);
    }

    public function run(Request $request): JsonResponse
    {
        $data = $request->validate([
            'resource_id' => 'required|exists:platform_resources,id',
            'storage_id' => 'nullable|exists:platform_storages,id',
        ]);
        $backup = PlatformBackup::query()->create([
            'resource_id' => $data['resource_id'],
            'storage_id' => $data['storage_id'] ?? null,
            'status' => 'queued',
        ]);
        RunDatabaseBackupJob::dispatch($backup->id);
        return response()->json(['success' => true, 'data' => $backup, 'message' => null, 'meta' => null, 'errors' => null], 202);
    }

    public function restore(PlatformBackup $backup): JsonResponse
    {
        RestoreDatabaseBackupJob::dispatch($backup->id);
        $backup->update(['status' => 'restore_queued']);

        return response()->json(['success' => true, 'data' => $backup->fresh(), 'message' => null, 'meta' => null, 'errors' => null], 202);
    }
}
