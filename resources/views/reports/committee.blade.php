<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Rapport de Comité Commercial - CRM Immobilier GRET INVEST</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            margin: 30px;
            color: #1e293b;
            background-color: #ffffff;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 3px solid #0f172a;
            padding-bottom: 15px;
            margin-bottom: 25px;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            color: #0f172a;
        }
        .header p {
            margin: 5px 0 0 0;
            color: #64748b;
            font-size: 14px;
        }
        .badge {
            background-color: #2563eb;
            color: white;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: bold;
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 30px;
        }
        .card {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
        }
        .card-value {
            font-size: 26px;
            font-weight: bold;
            color: #0f172a;
            margin-top: 5px;
        }
        .card-label {
            font-size: 12px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        th, td {
            border: 1px solid #cbd5e1;
            padding: 10px 12px;
            text-align: left;
            font-size: 13px;
        }
        th {
            background-color: #f1f5f9;
            color: #334155;
            font-weight: bold;
        }
        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 11px;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
            padding-top: 15px;
        }
        @media print {
            .no-print { display: none; }
            body { margin: 0; }
        }
    </style>
</head>
<body>

    <div class="no-print" style="margin-bottom: 20px; text-align: right;">
        <button onclick="window.print()" style="background-color: #2563eb; color: white; border: none; padding: 10px 20px; font-size: 14px; border-radius: 6px; cursor: pointer; font-weight: bold;">
            🖨️ Imprimer / Télécharger PDF
        </button>
    </div>

    <div class="header">
        <div>
            <h1>RAPPORT DE COMITÉ COMMERCIAL</h1>
            <p>Plateforme CRM SaaS - Promotion Immobilière GRET INVEST</p>
        </div>
        <div>
            <span class="badge">GRET INVEST</span>
            <p style="text-align: right; margin-top: 5px; font-size: 12px;">Généré le {{ now()->format('d/m/Y H:i') }}</p>
        </div>
    </div>

    <!-- Metrics Cards -->
    <div class="grid">
        <div class="card">
            <div class="card-label">Total Prospects (Bruts)</div>
            <div class="card-value">{{ $totalContacts }}</div>
        </div>
        <div class="card">
            <div class="card-label">Prospects Qualifiés (4/4)</div>
            <div class="card-value" style="color: #16a34a;">{{ $qualifiedContacts }}</div>
        </div>
        <div class="card">
            <div class="card-label">Délai Réponse Moyen</div>
            <div class="card-value">{{ $avgResponseMinutes }} min</div>
        </div>
        <div class="card">
            <div class="card-label">Délai Réponse Médian</div>
            <div class="card-value">{{ $medianResponseMinutes }} min</div>
        </div>
    </div>

    <!-- Trajectory Section -->
    <h3 style="color: #0f172a; border-bottom: 2px solid #e2e8f0; padding-bottom: 8px;">1. Écart à la Trajectoire Commerciale</h3>
    <table>
        <thead>
            <tr>
                <th>Jalon Mois</th>
                <th>Cible Qualifiés</th>
                <th>Réel Réalisé</th>
                <th>Écart</th>
                <th>Statut Trajectoire</th>
            </tr>
        </thead>
        <tbody>
            @foreach($trajectory as $item)
            <tr>
                <td><strong>{{ $item['month'] }}</strong></td>
                <td>{{ $item['target'] }} qualifiés</td>
                <td>{{ $item['actual'] }} qualifiés</td>
                <td style="color: {{ $item['diff'] >= 0 ? '#16a34a' : '#dc2626' }}; font-weight: bold;">
                    {{ $item['diff'] >= 0 ? '+' : '' }}{{ $item['diff'] }}
                </td>
                <td>
                    @if($item['diff'] >= 0)
                        <span style="color: #16a34a; font-weight: bold;">Dans les objectifs ✅</span>
                    @else
                        <span style="color: #dc2626; font-weight: bold;">Alerte Trajectoire ⚠️</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <!-- Funnel Pipeline Section -->
    <h3 style="color: #0f172a; border-bottom: 2px solid #e2e8f0; padding-bottom: 8px;">2. Entonnoir de Conversion (Pipeline)</h3>
    <table>
        <thead>
            <tr>
                <th>Étape Pipeline</th>
                <th>Nombre de Contacts</th>
                <th>% du Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($pipelineStages as $stage => $count)
            <tr>
                <td><strong>{{ ucfirst($stage) }}</strong></td>
                <td>{{ $count }}</td>
                <td>{{ $totalContacts > 0 ? round(($count / $totalContacts) * 100, 1) : 0 }}%</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <!-- Sources Breakdown -->
    <h3 style="color: #0f172a; border-bottom: 2px solid #e2e8f0; padding-bottom: 8px;">3. Contribution des Canaux d'Acquisition</h3>
    <table>
        <thead>
            <tr>
                <th>Canal / Source</th>
                <th>Bruts Collectés</th>
                <th>Qualifiés</th>
                <th>Taux de Qualification</th>
            </tr>
        </thead>
        <tbody>
            @foreach($sourceContribution as $source)
            <tr>
                <td><strong>{{ $source['label'] }}</strong> ({{ $source['channel'] }})</td>
                <td>{{ $source['total'] }}</td>
                <td>{{ $source['qualified'] }}</td>
                <td>{{ $source['total'] > 0 ? round(($source['qualified'] / $source['total']) * 100, 1) : 0 }}%</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        CRM Immobilier SaaS V1 - Rapport confidentiel généré pour le comité commercial GRET INVEST - LinkUp Technologies.
    </div>

</body>
</html>
