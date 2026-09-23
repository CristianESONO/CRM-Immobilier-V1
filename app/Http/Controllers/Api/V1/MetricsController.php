<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Observability\AppObservabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MetricsController extends Controller
{
    public function __construct(protected AppObservabilityService $observabilityService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $tenantId = (int) $request->header('X-Tenant-ID');
        if (!$tenantId && $request->attributes->has('api_key')) {
            $tenantId = $request->attributes->get('api_key')->tenant_id;
        }

        if (!$tenantId) {
            return response()->json([
                'status' => 'error',
                'code' => 400,
                'message' => 'Veuillez spécifier le tenant via l\'en-tête X-Tenant-ID ou une clé API.',
            ], 400);
        }

        $metrics = $this->observabilityService->getMetrics($tenantId);

        return response()->json([
            'status' => 'success',
            'data' => $metrics,
        ]);
    }
}
