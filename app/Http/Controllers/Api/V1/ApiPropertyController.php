<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApiPropertyController extends Controller
{
    public function properties(Request $request): JsonResponse
    {
        /** @var Tenant|null $tenant */
        $tenant = $request->attributes->get('tenant') ?? app(Tenant::class);

        $properties = Property::where('tenant_id', $tenant->id)
            ->withCount(['units'])
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $properties,
        ]);
    }

    public function units(Request $request): JsonResponse
    {
        /** @var Tenant|null $tenant */
        $tenant = $request->attributes->get('tenant') ?? app(Tenant::class);

        $query = Unit::where('tenant_id', $tenant->id)->with('property');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->paginate(20),
        ]);
    }
}
