<?php

namespace Modules\Mfg\Http\Controllers;

use App\Http\Controllers\Api\PaginatesApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Mfg\Entities\MfgBom;
use Modules\Mfg\Services\MfgBomService;

class BomController extends Controller
{
    use PaginatesApi;

    public function __construct(private MfgBomService $service) {}

    public function index(Request $request): JsonResponse
    {
        $q = MfgBom::query()->with('lines')->orderByDesc('id');
        if ($request->filled('status')) {
            $q->where('status', $request->string('status'));
        }
        if ($request->filled('product_id')) {
            $q->where('product_id', (int) $request->input('product_id'));
        }

        return $this->paginatedResponse($q->paginate($this->perPage($request)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => 'required|integer|exists:acc_products,id',
            'version' => 'nullable|string|max:50',
            'status' => 'nullable|string|max:20',
            'notes' => 'nullable|string',
            'lines' => 'nullable|array',
            'lines.*.component_product_id' => 'required_with:lines|integer|exists:acc_products,id',
            'lines.*.quantity' => 'nullable|numeric|min:0',
            'lines.*.unit' => 'nullable|string|max:20',
            'lines.*.scrap_percent' => 'nullable|numeric|min:0|max:100',
        ]);

        $bom = $this->service->create(
            collect($data)->except('lines')->all(),
            $data['lines'] ?? [],
        );

        return response()->json(['data' => $bom, 'message' => 'Created'], 201);
    }

    public function show(MfgBom $bom): JsonResponse
    {
        return response()->json(['data' => $bom->load('lines')]);
    }

    public function update(Request $request, MfgBom $bom): JsonResponse
    {
        $data = $request->validate([
            'product_id' => 'sometimes|integer|exists:acc_products,id',
            'version' => 'nullable|string|max:50',
            'status' => 'nullable|string|max:20',
            'notes' => 'nullable|string',
            'lines' => 'nullable|array',
            'lines.*.component_product_id' => 'required_with:lines|integer|exists:acc_products,id',
            'lines.*.quantity' => 'nullable|numeric|min:0',
            'lines.*.unit' => 'nullable|string|max:20',
            'lines.*.scrap_percent' => 'nullable|numeric|min:0|max:100',
        ]);

        $lines = array_key_exists('lines', $data) ? ($data['lines'] ?? []) : null;
        $updated = $this->service->update($bom, collect($data)->except('lines')->all(), $lines);

        return response()->json(['data' => $updated, 'message' => 'Updated']);
    }

    public function destroy(MfgBom $bom): JsonResponse
    {
        $bom->delete();

        return response()->json(null, 204);
    }

    public function lines(MfgBom $bom): JsonResponse
    {
        return response()->json(['data' => $bom->lines]);
    }
}
