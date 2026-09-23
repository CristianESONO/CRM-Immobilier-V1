<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\BuyerDocument;
use App\Models\BuyerPortalAccess;
use App\Models\Reservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BuyerPortalController extends Controller
{
    /**
     * Authenticate buyer and return portal session data.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $access = BuyerPortalAccess::where('email', strtolower($request->email))->first();

        if (!$access || !password_verify($request->password, $access->password_hash)) {
            return response()->json(['message' => 'Identifiants invalides'], 401);
        }

        $access->update([
            'portal_token' => bin2hex(random_bytes(30)),
            'last_login_at' => now(),
        ]);

        return response()->json([
            'message' => 'Connexion réussie',
            'portal_token' => $access->portal_token,
            'contact_id' => $access->contact_id,
        ]);
    }

    /**
     * Fetch buyer's personal dashboard data (reservations, unit, contracts, schedules, documents).
     */
    public function dashboard(Request $request): JsonResponse
    {
        $access = $this->getAuthenticatedAccess($request);
        if (!$access) {
            return response()->json(['message' => 'Non autorisé ou jeton expiré'], 401);
        }

        $contact = $access->contact;
        $reservations = Reservation::where('tenant_id', $access->tenant_id)
            ->where('contact_id', $access->contact_id)
            ->with(['property', 'unit', 'schedules', 'contract', 'buyerDocuments'])
            ->get();

        return response()->json([
            'contact' => [
                'id' => $contact->id,
                'first_name' => $contact->first_name,
                'last_name' => $contact->last_name,
                'email' => $contact->email,
                'phone' => $contact->phone,
            ],
            'reservations' => $reservations,
        ]);
    }

    protected function getAuthenticatedAccess(Request $request): ?BuyerPortalAccess
    {
        $token = $request->header('X-Buyer-Portal-Token') ?? $request->query('token');
        if (!$token) {
            return null;
        }

        return BuyerPortalAccess::where('portal_token', $token)->first();
    }
}
