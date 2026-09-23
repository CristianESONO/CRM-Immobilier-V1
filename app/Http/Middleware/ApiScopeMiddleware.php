<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ApiScopeMiddleware
{
    public function handle(Request $request, Closure $next, string $requiredScope = '*')
    {
        $rawToken = $request->header('X-API-Key') ?? $request->bearerToken();

        if (!$rawToken) {
            return response()->json([
                'status' => 'error',
                'code' => 401,
                'message' => 'Clé d\'API manquante. Veuillez fournir l\'en-tête X-API-Key ou Authorization: Bearer.',
            ], 401);
        }

        $keyHash = hash('sha256', $rawToken);
        $tenantId = $request->header('X-Tenant-ID');

        $apiKeyQuery = ApiKey::where('key_hash', $keyHash);
        if ($tenantId) {
            $apiKeyQuery->where('tenant_id', $tenantId);
        }

        $apiKey = $apiKeyQuery->first();

        if (!$apiKey) {
            return response()->json([
                'status' => 'error',
                'code' => 401,
                'message' => 'Clé d\'API invalide ou introuvable pour ce tenant.',
            ], 401);
        }

        if ($apiKey->expires_at && $apiKey->expires_at->isPast()) {
            return response()->json([
                'status' => 'error',
                'code' => 401,
                'message' => 'Clé d\'API expirée.',
            ], 401);
        }

        if ($apiKey->revoked_at && $apiKey->revoked_at->isPast()) {
            return response()->json([
                'status' => 'error',
                'code' => 401,
                'message' => 'Clé d\'API révoquée.',
            ], 401);
        }

        if ($requiredScope !== '*' && !$apiKey->hasScope($requiredScope)) {
            return response()->json([
                'status' => 'error',
                'code' => 403,
                'message' => "Scope insuffisant. Le scope [{$requiredScope}] est requis.",
            ], 403);
        }

        // Update last used
        $apiKey->update(['last_used_at' => now()]);
        $request->attributes->set('api_key', $apiKey);

        // Idempotency Key Handling for Mutations
        $idempotencyKey = $request->header('X-Idempotency-Key');
        if ($idempotencyKey && in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $cacheKey = "idempotency:{$apiKey->tenant_id}:{$idempotencyKey}";
            if (Cache::has($cacheKey)) {
                $cachedResponse = Cache::get($cacheKey);
                return response()->json($cachedResponse['content'], $cachedResponse['status']);
            }
        }

        $response = $next($request);

        if ($idempotencyKey && in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true) && $response->getStatusCode() < 500) {
            $cacheKey = "idempotency:{$apiKey->tenant_id}:{$idempotencyKey}";
            Cache::put($cacheKey, [
                'content' => json_decode($response->getContent(), true),
                'status' => $response->getStatusCode(),
            ], 86400); // 24h
        }

        return $response;
    }
}
