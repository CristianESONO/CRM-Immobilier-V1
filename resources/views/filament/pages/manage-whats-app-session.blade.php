<x-filament-panels::page>
    <div class="space-y-6">
        
        <!-- Status Card -->
        <div class="p-6 bg-white rounded-xl shadow-sm border border-gray-200 dark:bg-gray-800 dark:border-gray-700">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">Session WhatsApp Active (OpenWA Gateway)</h2>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                        Session ID courante : <code class="px-2 py-1 bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-gray-200 rounded font-mono text-xs">{{ $sessionInfo['id'] ?? '1b9201d2-932d-4cae-8b5f-c58c1d9780a1' }}</code>
                    </p>
                </div>
                <div>
                    @php
                        $status = strtolower($sessionInfo['status'] ?? 'unknown');
                        $statusColors = [
                            'created' => 'bg-slate-200 text-slate-900 border border-slate-400 font-bold',
                            'ready' => 'bg-emerald-600 text-white font-bold',
                            'qr_ready' => 'bg-amber-500 text-white font-bold',
                            'authenticating' => 'bg-blue-600 text-white font-bold',
                            'disconnected' => 'bg-rose-600 text-white font-bold',
                            'failed' => 'bg-rose-600 text-white font-bold',
                        ];
                        $colorClass = $statusColors[$status] ?? 'bg-gray-800 text-white font-bold';
                    @endphp
                    <span class="inline-flex items-center px-4 py-2 text-sm font-bold rounded-full shadow-sm {{ $colorClass }}">
                        Statut : {{ strtoupper($status) }}
                    </span>
                </div>
            </div>

            <div class="mt-6 flex flex-wrap items-center gap-4">
                <x-filament::button wire:click="refreshSessionStatus" color="primary">
                    🔄 Rafraîchir le Statut
                </x-filament::button>

                <x-filament::button wire:click="startSession" color="warning">
                    ⚡ Démarrer la Session
                </x-filament::button>
            </div>
        </div>

        <!-- Create New Session Card -->
        <div class="p-6 bg-white rounded-xl shadow-sm border border-gray-200 dark:bg-gray-800 dark:border-gray-700">
            <h3 class="text-md font-bold text-gray-900 dark:text-white mb-1">➕ Créer une nouvelle session dédiée (ex: GRET INVEST)</h3>
            <p class="text-xs text-gray-600 dark:text-gray-400 mb-4">Crée une instance WhatsApp propre à ce projet sur la passerelle OpenWA.</p>
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-4">
                <div class="flex-1">
                    <input type="text" wire:model="newSessionName" placeholder="Nom de session (ex: gretinvest)" class="block w-full rounded-lg border border-gray-300 bg-white dark:bg-gray-900 px-4 py-2.5 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm" />
                </div>
                <x-filament::button wire:click="createNewSession" color="info">
                    Créer la Session
                </x-filament::button>
            </div>
        </div>

        <!-- QR Code display if qr_ready -->
        @if(!empty($qrCode))
            <div class="p-6 bg-white rounded-xl shadow-sm border border-gray-200 dark:bg-gray-800 dark:border-gray-700 text-center">
                <h3 class="text-md font-bold text-gray-900 dark:text-white mb-2">Scannez le QR Code avec WhatsApp</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">Ouvrez WhatsApp > Appareils liés > Lier un appareil</p>
                <div class="inline-block p-4 bg-white border border-gray-300 rounded-lg shadow-inner">
                    <img src="{{ $qrCode }}" alt="QR Code WhatsApp OpenWA" class="mx-auto w-64 h-64" />
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">Le QR Code se rafraîchit automatiquement toutes les 20 secondes.</p>
            </div>
        @endif

        <!-- Direct Test Sending Form -->
        <div class="p-6 bg-white rounded-xl shadow-sm border border-gray-200 dark:bg-gray-800 dark:border-gray-700">
            <h3 class="text-md font-bold text-gray-900 dark:text-white mb-4">Test d'envoi direct GRET INVEST</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-800 dark:text-gray-200 mb-1">Numéro Destinataire (E.164)</label>
                    <input type="text" wire:model="testPhone" placeholder="Ex: 221785962662" class="block w-full rounded-lg border border-gray-300 bg-white dark:bg-gray-900 px-4 py-2.5 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-800 dark:text-gray-200 mb-1">Message Texte</label>
                    <input type="text" wire:model="testMessage" placeholder="Votre message..." class="block w-full rounded-lg border border-gray-300 bg-white dark:bg-gray-900 px-4 py-2.5 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm" />
                </div>
            </div>
            <div class="mt-4">
                <x-filament::button wire:click="sendTestMessage" color="success">
                    🚀 Envoyer Message de Test
                </x-filament::button>
            </div>
        </div>

    </div>
</x-filament-panels::page>
