<?php

namespace App\Http\Controllers\Portal;

use App\Events\ContactCreated;
use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\Opportunity;
use App\Models\PartnerPortalAccess;
use App\Models\Reservation;
use App\Models\Source;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PartnerPortalController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $access = PartnerPortalAccess::where('email', strtolower($request->email))->first();

        if (!$access || !password_verify($request->password, $access->password_hash)) {
            return response()->json(['message' => 'Identifiants invalides'], 401);
        }

        $access->update([
            'portal_token' => bin2hex(random_bytes(30)),
            'last_login_at' => now(),
        ]);

        return response()->json([
            'message' => 'Connexion prescripteur réussie',
            'portal_token' => $access->portal_token,
            'referrer_id' => $access->referrer_id,
        ]);
    }

    public function dashboard(Request $request): JsonResponse
    {
        $access = $this->getAuthenticatedPartner($request);
        if (!$access) {
            return response()->json(['message' => 'Non autorisé'], 401);
        }

        $referrerId = $access->referrer_id;
        $tenantId = $access->tenant_id;

        $leads = Contact::where('tenant_id', $tenantId)
            ->where('referrer_id', $referrerId)
            ->get();

        $leadIds = $leads->pluck('id');

        $reservations = Reservation::where('tenant_id', $tenantId)
            ->whereIn('contact_id', $leadIds)
            ->whereIn('status', ['confirmed', 'completed'])
            ->get();

        $totalSalesAmount = $reservations->sum('total_amount');
        $commissionEarned = round(($totalSalesAmount * (float) $access->commission_rate) / 100, 2);

        return response()->json([
            'partner' => [
                'referrer_id' => $access->referrer_id,
                'commission_rate' => $access->commission_rate,
            ],
            'leads_count' => $leads->count(),
            'reservations_count' => $reservations->count(),
            'total_sales_amount' => $totalSalesAmount,
            'commission_earned' => $commissionEarned,
            'recent_leads' => $leads->take(10),
        ]);
    }

    public function submitLead(Request $request): JsonResponse
    {
        $access = $this->getAuthenticatedPartner($request);
        if (!$access) {
            return response()->json(['message' => 'Non autorisé'], 401);
        }

        $validated = $request->validate([
            'first_name' => ['required', 'string'],
            'last_name' => ['required', 'string'],
            'email' => ['nullable', 'email'],
            'phone' => ['required', 'string'],
        ]);

        $source = Source::where('tenant_id', $access->tenant_id)->first();

        $contact = Contact::create([
            'tenant_id' => $access->tenant_id,
            'source_id' => $source?->id ?? 1,
            'referrer_id' => $access->referrer_id,
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'] ?? null,
            'phone_e164' => $validated['phone'],
            'status' => 'nouveau',
        ]);

        event(new ContactCreated($contact));

        return response()->json([
            'message' => 'Prospect soumis avec succès',
            'contact_id' => $contact->id,
        ], 201);
    }

    protected function getAuthenticatedPartner(Request $request): ?PartnerPortalAccess
    {
        $token = $request->header('X-Partner-Portal-Token') ?? $request->query('token');
        if (!$token) {
            return null;
        }

        return PartnerPortalAccess::where('portal_token', $token)->first();
    }
}
