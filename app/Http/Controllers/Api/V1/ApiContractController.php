<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApiContractController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Tenant|null $tenant */
        $tenant = $request->attributes->get('tenant') ?? app(Tenant::class);

        $query = Contract::where('tenant_id', $tenant->id)
            ->with(['reservation']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->paginate(20),
        ]);
    }
}
