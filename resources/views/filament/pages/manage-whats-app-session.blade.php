<x-filament-panels::page>
    <div class="space-y-6">
        
        <!-- Status Card -->
        <div class="p-6 bg-white rounded-xl shadow-sm border border-gray-200 dark:bg-gray-800 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">Session WhatsApp Active (OpenWA)</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Session ID courante : <code class="px-2 py-1 bg-gray-100 dark:bg-gray-700 rounded font-mono text-xs">{{ $sessionInfo['id'] ?? '1b9201d2-932d-4cae-8b5f-c58c1d9780a1' }}</code>
                    </p>
                </div>
                <div>
                    @php
                        $status = $sessionInfo['status'] ?? 'unknown';
                        $statusColors = [
                            'ready' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                            'qr_ready' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
                            'authenticating' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                            'disconnected' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                        ];
                        $colorClass = $statusColors[$status] ?? 'bg-gray-100 text-gray-800';
                    @endphp
                    <span class="px-4 py-2 text-sm font-semibold rounded-full {{ $colorClass }}">
                        Statut : {{ strtoupper($status) }}
                    </span>
                </div>
            </div>

            <div class="mt-6 flex space-x-3">
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
            <h3 class="text-md font-bold text-gray-900 dark:text-white mb-2">➕ Créer une nouvelle session dédiée (ex: GRET INVEST)</h3>
            <p class="text-xs text-gray-500 mb-4">Crée une instance WhatsApp propre à ce projet sur la passerelle OpenWA.</p>
            <div class="flex items-center space-x-4">
                <div class="flex-1">
                    <input type="text" wire:model="newSessionName" placeholder="Nom de session (ex: gretinvest)" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm text-gray-900" />
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
                <p class="text-sm text-gray-500 mb-4">Ouvrez WhatsApp > Appareils liés > Lier un appareil</p>
                <div class="inline-block p-4 bg-white border rounded-lg shadow-inner">
                    <img src="{{ $qrCode }}" alt="QR Code WhatsApp OpenWA" class="mx-auto w-64 h-64" />
                </div>
                <p class="text-xs text-gray-400 mt-2">Le QR Code se rafraîchit automatiquement toutes les 20 secondes.</p>
            </div>
        @endif

        <!-- Direct Test Sending Form -->
        <div class="p-6 bg-white rounded-xl shadow-sm border border-gray-200 dark:bg-gray-800 dark:border-gray-700">
            <h3 class="text-md font-bold text-gray-900 dark:text-white mb-4">Test d'envoi direct YokAlma</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Numéro Destinataire (E.164)</label>
                    <input type="text" wire:model="testPhone" placeholder="Ex: 221785962662" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm text-gray-900" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Message Texte</label>
                    <input type="text" wire:model="testMessage" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm text-gray-900" />
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
