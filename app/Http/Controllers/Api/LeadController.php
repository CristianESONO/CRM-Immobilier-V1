<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\Source;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LeadController extends Controller
{
    public function store(Request $request)
    {
        // Honeypot check (bot prevention)
        if ($request->filled('website_hp_field')) {
            return response()->json(['success' => true, 'message' => 'Lead received'], 200);
        }

        $tenantSlug = $request->header('X-Tenant-Slug') ?? $request->input('tenant_slug');
        $tenant = Tenant::where('slug', $tenantSlug)->first();

        if (!$tenant) {
            return response()->json(['error' => 'Invalid tenant identifier'], 400);
        }

        session(['tenant_id' => $tenant->id]);

        $validator = Validator::make($request->all(), [
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'channel' => 'nullable|string',
            'source_label' => 'nullable|string',
            'sub_source' => 'nullable|string',
            'utm_source' => 'nullable|string',
            'utm_medium' => 'nullable|string',
            'utm_campaign' => 'nullable|string',
            'landing_page' => 'nullable|string',
            'property_type' => 'nullable|string',
            'budget_max' => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        // Ensure source exists or resolve default web source
        $channel = $data['channel'] ?? 'web';
        $sourceLabel = $data['source_label'] ?? 'Formulaire Web LP';

        $source = Source::firstOrCreate(
            ['tenant_id' => $tenant->id, 'label' => $sourceLabel],
            ['channel' => $channel, 'is_active' => true]
        );

        $contact = Contact::create([
            'tenant_id' => $tenant->id,
            'source_id' => $source->id,
            'first_name' => $data['first_name'] ?? null,
            'last_name' => $data['last_name'] ?? null,
            'phone_e164' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'sub_source' => $data['sub_source'] ?? null,
            'utm_source' => $data['utm_source'] ?? null,
            'utm_medium' => $data['utm_medium'] ?? null,
            'utm_campaign' => $data['utm_campaign'] ?? null,
            'landing_page' => $data['landing_page'] ?? null,
            'property_type' => $data['property_type'] ?? null,
            'budget_max' => $data['budget_max'] ?? null,
            'q_source_at' => now(), // Source verified at collection
            'consent_at' => now(),
            'consent_source' => 'web_form',
            'status' => 'nouveau',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Lead created successfully',
            'contact_id' => $contact->id
        ], 201);
    }
}
