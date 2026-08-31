<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Source;
use App\Models\Tenant;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function exportCommitteePdf(Request $request)
    {
        $tenantId = session('tenant_id') ?? 1;

        $totalContacts = Contact::count();
        $qualifiedContacts = Contact::whereNotNull('qualified_at')->count();

        // Response time metrics
        $responseTimes = Contact::whereNotNull('first_response_minutes')
            ->pluck('first_response_minutes')
            ->sort()
            ->values();

        $avgResponseMinutes = round($responseTimes->avg() ?? 0);
        
        $count = $responseTimes->count();
        if ($count > 0) {
            $middle = floor($count / 2);
            $medianResponseMinutes = ($count % 2 == 0)
                ? round(($responseTimes[$middle - 1] + $responseTimes[$middle]) / 2)
                : round($responseTimes[$middle]);
        } else {
            $medianResponseMinutes = 0;
        }

        // Trajectory targets
        $targets = [
            'M2' => 15,
            'M3' => 40,
            'M4' => 70,
            'M5' => 115,
            'M6' => 150,
        ];

        $trajectory = [];
        foreach ($targets as $month => $target) {
            $diff = $qualifiedContacts - $target;
            $trajectory[] = [
                'month' => $month,
                'target' => $target,
                'actual' => $qualifiedContacts,
                'diff' => $diff,
            ];
        }

        // Pipeline stage counts
        $stages = [
            'nouveau', 'contacte', 'qualifie', 'rdv_planifie',
            'visite_planifiee', 'visite_realisee', 'proposition', 'gagne', 'perdu'
        ];

        $pipelineStages = [];
        foreach ($stages as $stage) {
            $pipelineStages[$stage] = Contact::where('status', $stage)->count();
        }

        // Source breakdown
        $sources = Source::all();
        $sourceContribution = [];

        foreach ($sources as $source) {
            $total = Contact::where('source_id', $source->id)->count();
            $qualified = Contact::where('source_id', $source->id)->whereNotNull('qualified_at')->count();

            $sourceContribution[] = [
                'label' => $source->label,
                'channel' => $source->channel,
                'total' => $total,
                'qualified' => $qualified,
            ];
        }

        return view('reports.committee', compact(
            'totalContacts',
            'qualifiedContacts',
            'avgResponseMinutes',
            'medianResponseMinutes',
            'trajectory',
            'pipelineStages',
            'sourceContribution'
        ));
    }
}
