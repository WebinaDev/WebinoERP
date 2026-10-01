<?php

namespace Modules\Platform\Http\Controllers;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\SiteBuilder\Entities\WebinoSiteProvision;
use Modules\SiteBuilder\Jobs\ProvisionWebinoSiteJob;
use Modules\SiteBuilder\Support\LaunchSchema;
use Modules\SiteBuilder\Support\ProvisionProgress;
use Throwable;

/**
 * Legacy Platform launch endpoint — queues the same async Site Builder job.
 */
class WebinoProvisionController extends Controller
{
    public function launch(Request $request): JsonResponse
    {
        $data = $request->validate([
            'provision_id' => 'required|exists:webino_site_provisions,id',
            'server_id' => 'required|exists:platform_servers,id',
            'site_type_slug' => 'nullable|string|max:32',
        ]);

        try {
            $provision = WebinoSiteProvision::query()->findOrFail($data['provision_id']);
            $wizard = $provision->wizard_payload ?? [];
            $wizard['server_id'] = (int) $data['server_id'];
            if (! empty($data['site_type_slug'])) {
                $wizard['site_type_slug'] = $data['site_type_slug'];
            }

            if (LaunchSchema::missingProgressColumn()) {
                return response()->json($this->failureEnvelope(LaunchSchema::outdatedPayload()), 422);
            }

            $previousStatus = $provision->status;
            $provision->update([
                'wizard_payload' => $wizard,
                'status' => WebinoSiteProvision::STATUS_PENDING,
                'error_log' => null,
                'progress' => ProvisionProgress::make(ProvisionProgress::PHASE_QUEUED),
            ]);

            if (! ProvisionWebinoSiteJob::enqueue($provision->id)) {
                $provision->update([
                    'status' => $previousStatus,
                    'progress' => null,
                    'error_log' => LaunchSchema::QUEUE_UNAVAILABLE,
                ]);

                return response()->json($this->failureEnvelope(LaunchSchema::queueUnavailablePayload()), 422);
            }

            try {
                $row = $provision->fresh(['license', 'package', 'crmAccount']);
            } catch (DecryptException $e) {
                report($e);
                $row = $provision->fresh();
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'provision' => $row,
                    'queued' => true,
                ],
                'message' => 'Launch queued.',
                'meta' => null,
                'errors' => null,
            ], 202);
        } catch (DecryptException $e) {
            report($e);

            return response()->json($this->failureEnvelope(LaunchSchema::queueUnavailablePayload()), 422);
        } catch (QueryException $e) {
            report($e);

            return response()->json($this->failureEnvelope(LaunchSchema::outdatedPayload($e->getMessage())), 422);
        } catch (Throwable $e) {
            report($e);

            $payload = LaunchSchema::isProgressFailure($e)
                ? LaunchSchema::outdatedPayload($e->getMessage())
                : LaunchSchema::queueUnavailablePayload();

            return response()->json($this->failureEnvelope($payload), 422);
        }
    }

    /**
     * @param  array{message: string, errors: array<string, mixed>}  $payload
     * @return array<string, mixed>
     */
    private function failureEnvelope(array $payload): array
    {
        return [
            'success' => false,
            'data' => null,
            'message' => $payload['message'],
            'meta' => null,
            'errors' => $payload['errors'],
        ];
    }
}
