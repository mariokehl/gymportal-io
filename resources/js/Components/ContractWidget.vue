<!-- ContractWidget.vue -->
<template>
    <div class="contract-widget space-y-8">
        <!-- Header mit Gym-Info -->
        <div class="bg-white shadow-sm rounded-lg p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900">{{ currentGym.name }}</h2>
                    <p class="text-gray-600">Konfiguriere das Registrierungs-Widget für dein Fitnessstudio</p>
                </div>
                <div class="flex items-center space-x-2">
                    <div class="flex items-center">
                        <div :class="[
                            'w-3 h-3 rounded-full mr-2',
                            settings.widget_enabled ? 'bg-green-500' : 'bg-gray-400'
                        ]"></div>
                        <span class="text-sm text-gray-600">
                            {{ settings.widget_enabled ? 'Aktiviert' : 'Deaktiviert' }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Widget Configuration -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center">
                    <component :is="Settings" class="w-6 h-6 text-indigo-600 mr-3" />
                    <h3 class="text-xl font-semibold text-gray-900">Widget-Einstellungen</h3>
                </div>
            </div>

            <form @submit.prevent="saveWidgetConfig" class="space-y-6">
                <!-- Widget aktivieren/deaktivieren -->
                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                    <div>
                        <h4 class="text-sm font-medium text-gray-900">Widget aktivieren</h4>
                        <p class="text-sm text-gray-500">Macht das Widget auf deiner Website verfügbar</p>
                    </div>
                    <div class="relative inline-block w-11 h-5">
                        <input v-model="settings.widget_enabled" id="switch-component-blue" type="checkbox" class="peer appearance-none w-11 h-5 bg-slate-100 rounded-full checked:bg-indigo-600 cursor-pointer transition-colors duration-300" />
                        <label for="switch-component-blue" class="absolute top-0 left-0 w-5 h-5 bg-white rounded-full border border-slate-300 shadow-sm transition-transform duration-300 peer-checked:translate-x-6 peer-checked:border-indigo-600 cursor-pointer"></label>
                    </div>
                </div>

                <!-- Farben -->
                <div>
                    <h4 class="text-lg font-medium text-gray-900 mb-4 flex items-center">
                        <component :is="Palette" class="w-5 h-5 mr-2" />
                        Farben
                    </h4>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Primärfarbe</label>
                            <div class="flex items-center space-x-3">
                                <input
                                    type="color"
                                    v-model="settings.colors.primary"
                                    class="w-12 h-10 rounded border border-gray-300 cursor-pointer"
                                >
                                <input
                                    type="text"
                                    v-model="settings.colors.primary"
                                    class="flex-1 px-3 py-2 border border-gray-300 rounded-md text-sm"
                                >
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Sekundärfarbe</label>
                            <div class="flex items-center space-x-3">
                                <input
                                    type="color"
                                    v-model="settings.colors.secondary"
                                    class="w-12 h-10 rounded border border-gray-300 cursor-pointer"
                                >
                                <input
                                    type="text"
                                    v-model="settings.colors.secondary"
                                    class="flex-1 px-3 py-2 border border-gray-300 rounded-md text-sm"
                                >
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Texte -->
                <div>
                    <h4 class="text-lg font-medium text-gray-900 mb-4 flex items-center">
                        <component :is="Type" class="w-5 h-5 mr-2" />
                        Texte
                    </h4>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Widget-Titel</label>
                            <input
                                type="text"
                                v-model="settings.texts.title"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500"
                                placeholder="z.B. Wähle deinen Tarif"
                            >
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Willkommensnachricht</label>
                            <textarea
                                v-model="settings.texts.welcome_message"
                                rows="3"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500"
                                placeholder="z.B. Willkommen bei unserem Fitnessstudio..."
                            ></textarea>
                        </div>
                    </div>
                </div>

                <!-- Vertragsauswahl -->
                <div>
                    <h4 class="text-lg font-medium text-gray-900 mb-4 flex items-center">
                        <component :is="ListOrdered" class="w-5 h-5 mr-2" />
                        Vertragsauswahl
                    </h4>
                    <p class="text-sm text-gray-500 mb-4">
                        Wähle bis zu 3 Verträge aus und bestimme ihre Anzeigereihenfolge im Widget
                    </p>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <!-- Verfügbare Verträge -->
                        <div>
                            <h5 class="text-sm font-medium text-gray-700 mb-3">Verfügbare Verträge</h5>
                            <div class="bg-gray-50 rounded-lg p-4 min-h-[200px]">
                                <!-- Loading State -->
                                <div v-if="contractsLoading" class="text-center py-8">
                                    <div class="inline-block animate-spin rounded-full h-6 w-6 border-b-2 border-indigo-600"></div>
                                    <p class="mt-2 text-sm text-gray-600">Verträge werden geladen...</p>
                                </div>

                                <!-- Empty State -->
                                <div v-else-if="availableContracts.length === 0" class="text-center text-gray-400 py-8">
                                    <component :is="Package" class="w-8 h-8 mx-auto mb-2" />
                                    <p class="text-sm">Keine Verträge verfügbar</p>
                                    <button @click="loadAvailableContracts"
                                            class="mt-2 text-xs text-indigo-600 hover:text-indigo-500">
                                        Erneut versuchen
                                    </button>
                                </div>

                                <!-- Contracts List -->
                                <div v-else class="space-y-2">
                                    <div v-for="contract in availableContracts"
                                        :key="contract.id"
                                        @click="selectContract(contract)"
                                        :class="[
                                            'p-3 bg-white rounded-md border cursor-pointer transition-all',
                                            isContractSelected(contract.id)
                                                ? 'border-gray-300 opacity-50 cursor-not-allowed'
                                                : 'border-gray-200 hover:border-indigo-300 hover:shadow-sm'
                                        ]"
                                        :disabled="isContractSelected(contract.id) || selectedContracts.length >= 3">
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center">
                                                <input type="checkbox"
                                                    :checked="isContractSelected(contract.id)"
                                                    :disabled="!isContractSelected(contract.id) && selectedContracts.length >= 3"
                                                    class="mr-3 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                                    @click.stop="toggleContract(contract)">
                                                <div>
                                                    <p class="font-medium text-sm text-gray-900">{{ contract.name }}</p>
                                                    <p class="text-xs text-gray-500">
                                                        {{ contract.formatted_price }} / {{ contract.billing_cycle_text || contract.billing_cycle }}
                                                    </p>
                                                </div>
                                            </div>
                                            <span v-if="contract.is_active" class="text-xs bg-green-100 text-green-800 px-2 py-1 rounded">
                                                Aktiv
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Ausgewählte Verträge -->
                        <div>
                            <div class="flex items-center justify-between mb-3">
                                <h5 class="text-sm font-medium text-gray-700">
                                    Ausgewählte Verträge ({{ selectedContracts.length }}/3)
                                </h5>
                                <button v-if="selectedContracts.length > 0"
                                        @click="clearSelection"
                                        class="text-xs text-gray-500 hover:text-gray-700">
                                    Alle entfernen
                                </button>
                            </div>
                            <div class="bg-indigo-50 rounded-lg p-4 min-h-[200px]">
                                <div v-if="selectedContracts.length === 0"
                                    class="text-center text-indigo-400 py-8">
                                    <component :is="MousePointerClick" class="w-8 h-8 mx-auto mb-2" />
                                    <p class="text-sm">Wähle Verträge aus der linken Liste</p>
                                </div>

                                <draggable v-else
                                        v-model="selectedContracts"
                                        item-key="id"
                                        handle=".drag-handle"
                                        :animation="200"
                                        class="space-y-2">
                                    <template #item="{element, index}">
                                        <div class="p-3 bg-white rounded-md border border-indigo-200 shadow-sm">
                                            <div class="flex items-center">
                                                <div class="drag-handle cursor-move mr-3 text-gray-400 hover:text-gray-600">
                                                    <component :is="GripVertical" class="w-5 h-5" />
                                                </div>
                                                <div class="flex items-center justify-between flex-1">
                                                    <div class="flex items-center">
                                                        <span class="inline-flex items-center justify-center w-6 h-6 bg-indigo-600 text-white text-xs font-bold rounded-full mr-3">
                                                            {{ index + 1 }}
                                                        </span>
                                                        <div>
                                                            <p class="font-medium text-sm text-gray-900">{{ element.name }}</p>
                                                            <p class="text-xs text-gray-500">
                                                                {{ element.formatted_price }} / {{ element.billing_cycle_text || element.billing_cycle }}
                                                            </p>
                                                        </div>
                                                    </div>
                                                    <button @click="removeContract(element.id)"
                                                            class="ml-3 text-gray-400 hover:text-red-600 transition-colors">
                                                        <component :is="X" class="w-4 h-4" />
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                </draggable>

                                <!-- Hinweis -->
                                <div v-if="selectedContracts.length === 3"
                                    class="mt-3 p-2 bg-indigo-100 rounded text-xs text-indigo-700">
                                    <component :is="Info" class="w-3 h-3 inline mr-1" />
                                    Maximale Anzahl erreicht
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Zusätzliche Optionen -->
                    <div class="mt-4 p-3 bg-gray-50 rounded-lg">
                        <label class="flex items-center justify-between cursor-pointer">
                            <div>
                                <span class="text-sm font-medium text-gray-900">Automatische Sortierung</span>
                                <p class="text-xs text-gray-500">Verträge nach Preis aufsteigend sortieren</p>
                            </div>
                            <input type="checkbox"
                                v-model="settings.contracts.auto_sort"
                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        </label>
                    </div>
                </div>

                <!-- Funktionen -->
                <div>
                    <h4 class="text-lg font-medium text-gray-900 mb-4 flex items-center">
                        <component :is="ToggleLeft" class="w-5 h-5 mr-2" />
                        Funktionen
                    </h4>
                    <div class="space-y-3">
                        <label class="flex items-center justify-between p-3 bg-gray-50 rounded-lg cursor-pointer">
                            <div>
                                <span class="text-sm font-medium text-gray-900">Laufzeit-Auswahl</span>
                                <p class="text-xs text-gray-500">12/24 Monate Auswahl anzeigen</p>
                            </div>
                            <input
                                type="checkbox"
                                v-model="settings.features.show_duration_selector"
                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                            >
                        </label>
                        <label class="flex items-center justify-between p-3 bg-gray-50 rounded-lg cursor-pointer">
                            <div>
                                <span class="text-sm font-medium text-gray-900">Fitness-Ziele</span>
                                <p class="text-xs text-gray-500">Ziele-Auswahl im Formular</p>
                            </div>
                            <input
                                type="checkbox"
                                v-model="settings.features.show_goals_selection"
                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                            >
                        </label>
                        <label class="flex items-center justify-between p-3 bg-gray-50 rounded-lg cursor-pointer">
                            <div>
                                <span class="text-sm font-medium text-gray-900">Geburtsdatum erforderlich</span>
                                <p class="text-xs text-gray-500">Pflichtfeld im Formular</p>
                            </div>
                            <input
                                type="checkbox"
                                v-model="settings.features.require_birth_date"
                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                            >
                        </label>
                    </div>
                </div>

                <div class="pt-4 border-t border-gray-200 space-y-3">
                    <button
                        type="button"
                        @click="openWidgetPreview"
                        class="w-full flex items-center justify-center px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 transition-colors"
                    >
                        <component :is="ExternalLink" class="w-4 h-4 mr-2" />
                        Vorschau anzeigen
                    </button>
                    <button
                        type="submit"
                        :disabled="isSaving"
                        class="w-full flex items-center justify-center px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                    >
                        <component :is="Save" class="w-4 h-4 mr-2" />
                        {{ isSaving ? 'Speichern...' : 'Einstellungen speichern' }}
                    </button>
                </div>
            </form>
        </div>

        <!-- Integration Methods -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <div class="flex items-center mb-4">
                <component :is="ToyBrick" class="w-6 h-6 text-indigo-600 mr-3" />
                <h3 class="text-xl font-semibold text-gray-900">Integration-Methoden</h3>
            </div>

            <!-- Tab Navigation -->
            <div class="border-b border-gray-200 mb-6">
                <nav class="-mb-px flex space-x-8">
                    <button v-for="method in integrationMethods" :key="method.key"
                            @click="activeMethod = method.key"
                            :class="[
                                'py-2 px-1 border-b-2 font-medium text-sm',
                                activeMethod === method.key
                                    ? 'border-indigo-500 text-indigo-600'
                                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                            ]">
                        <component :is="method.icon" class="w-4 h-4 mr-2 inline" />
                        {{ method.label }}
                    </button>
                </nav>
            </div>

            <!-- Script Integration -->
            <div v-if="activeMethod === 'script'" class="space-y-4">
                <div class="bg-indigo-50 border border-indigo-200 rounded-lg p-4">
                    <h4 class="font-medium text-indigo-900 mb-2">Script Integration (Empfohlen)</h4>
                    <p class="text-indigo-800 text-sm">Einfachste Integration - fügen Sie nur den Code in Ihre Website ein.</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        HTML-Code für Ihre Website:
                    </label>
                    <div class="relative">
                        <pre class="bg-gray-900 text-gray-100 p-4 rounded-lg overflow-x-auto text-sm">{{ scriptCode }}</pre>
                        <button @click="copyToClipboard(scriptCode)"
                                class="absolute top-2 right-2 bg-gray-700 hover:bg-gray-600 text-white p-2 rounded text-xs">
                            <component :is="Copy" class="w-4 h-4" />
                        </button>
                    </div>
                </div>
            </div>

            <!-- iFrame Integration -->
            <div v-if="activeMethod === 'iframe'" class="space-y-4">
                <div class="bg-gray-50 border border-gray-200 rounded-md p-3 shadow-sm">
                    <div class="flex items-center">
                        <svg class="h-4 w-4 text-gray-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd"></path>
                        </svg>
                        <span class="text-sm text-gray-700">
                            Feature noch nicht implementiert - wird in Kürze verfügbar sein
                        </span>
                    </div>
                </div>

                <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                    <h4 class="font-medium text-green-900 mb-2">iFrame Integration</h4>
                    <p class="text-green-800 text-sm">Vollständige Isolation - Widget läuft in eigenem Kontext.</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        iFrame-Code:
                    </label>
                    <div class="relative">
                        <pre class="bg-gray-900 text-gray-100 p-4 rounded-lg overflow-x-auto text-sm">{{ iframeCode }}</pre>
                        <button @click="copyToClipboard(iframeCode)"
                                class="absolute top-2 right-2 bg-gray-700 hover:bg-gray-600 text-white p-2 rounded text-xs">
                            <component :is="Copy" class="w-4 h-4" />
                        </button>
                    </div>
                </div>
            </div>

            <!-- Web Component -->
            <div v-if="activeMethod === 'webcomponent'" class="space-y-4">
                <div class="bg-gray-50 border border-gray-200 rounded-md p-3 shadow-sm">
                    <div class="flex items-center">
                        <svg class="h-4 w-4 text-gray-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd"></path>
                        </svg>
                        <span class="text-sm text-gray-700">
                            Feature noch nicht implementiert - wird in Kürze verfügbar sein
                        </span>
                    </div>
                </div>

                <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
                    <h4 class="font-medium text-purple-900 mb-2">Web Component</h4>
                    <p class="text-purple-800 text-sm">Moderne, standardkonforme Integration für entwickleraffine Nutzer.</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Web Component Code:
                    </label>
                    <div class="relative">
                        <pre class="bg-gray-900 text-gray-100 p-4 rounded-lg overflow-x-auto text-sm">{{ webComponentCode }}</pre>
                        <button @click="copyToClipboard(webComponentCode)"
                                class="absolute top-2 right-2 bg-gray-700 hover:bg-gray-600 text-white p-2 rounded text-xs">
                            <component :is="Copy" class="w-4 h-4" />
                        </button>
                    </div>
                </div>
            </div>

            <!-- API Integration -->
            <div v-if="activeMethod === 'api'" class="space-y-4">
                <div class="bg-gray-50 border border-gray-200 rounded-md p-3 shadow-sm">
                    <div class="flex items-center">
                        <svg class="h-4 w-4 text-gray-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd"></path>
                        </svg>
                        <span class="text-sm text-gray-700">
                            Feature noch nicht implementiert - wird in Kürze verfügbar sein
                        </span>
                    </div>
                </div>

                <div class="bg-orange-50 border border-orange-200 rounded-lg p-4">
                    <h4 class="font-medium text-orange-900 mb-2">REST API</h4>
                    <p class="text-orange-800 text-sm">Headless Ansatz für maximale Flexibilität - bauen Sie Ihre eigene UI.</p>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            API-Endpunkte:
                        </label>
                        <div class="relative">
                            <pre class="bg-gray-900 text-gray-100 p-4 rounded-lg overflow-x-auto text-sm">{{ apiEndpoints }}</pre>
                            <button @click="copyToClipboard(apiEndpoints)"
                                    class="absolute top-2 right-2 bg-gray-700 hover:bg-gray-600 text-white p-2 rounded text-xs">
                                <component :is="Copy" class="w-4 h-4" />
                            </button>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            JavaScript SDK:
                        </label>
                        <div class="relative">
                            <pre class="bg-gray-900 text-gray-100 p-4 rounded-lg overflow-x-auto text-sm">{{ sdkCode }}</pre>
                            <button @click="copyToClipboard(sdkCode)"
                                    class="absolute top-2 right-2 bg-gray-700 hover:bg-gray-600 text-white p-2 rounded text-xs">
                                <component :is="Copy" class="w-4 h-4" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- API Keys -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center mb-4">
                <component :is="Key" class="w-6 h-6 text-indigo-600 mr-3" />
                <h3 class="text-xl font-semibold text-gray-900">API-Schlüssel</h3>
            </div>

            <!-- Loading State -->
            <div v-if="apiKeys.loading" class="text-center py-4">
                <div class="inline-block animate-spin rounded-full h-6 w-6 border-b-2 border-indigo-600"></div>
                <p class="mt-2 text-sm text-gray-600">API-Schlüssel werden geladen...</p>
            </div>

            <!-- Error State -->
            <div v-else-if="apiKeys.error" class="bg-red-50 border border-red-200 rounded-lg p-4">
                <p class="text-red-800 text-sm">{{ apiKeys.error }}</p>
                <button @click="loadApiKeys" class="mt-2 text-red-600 hover:text-red-500 text-sm">
                    Erneut versuchen
                </button>
            </div>

            <!-- API Keys Content -->
            <div v-else class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Public Key (für Frontend-Integration):
                    </label>
                    <div class="flex items-center space-x-2">
                        <input :value="apiKeys.public" type="text" readonly
                            class="flex-1 p-2 border border-gray-300 rounded-md bg-gray-50 text-sm font-mono">
                        <button @click="copyToClipboard(apiKeys.public)"
                                :disabled="!apiKeys.public"
                                class="bg-gray-600 hover:bg-gray-700 disabled:opacity-50 text-white p-2 rounded">
                            <component :is="Copy" class="w-4 h-4" />
                        </button>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Private Key (für Server-Integration):
                    </label>
                    <div class="flex items-center space-x-2">
                        <input :value="showPrivateKey ? apiKeys.private : '••••••••••••••••••••••••••••••••'"
                            type="text" readonly
                            class="flex-1 p-2 border border-gray-300 rounded-md bg-gray-50 text-sm font-mono">
                        <button @click="togglePrivateKey"
                                :disabled="!apiKeys.private"
                                class="bg-gray-600 hover:bg-gray-700 disabled:opacity-50 text-white p-2 rounded">
                            <component :is="showPrivateKey ? EyeOff : Eye" class="w-4 h-4" />
                        </button>
                        <button @click="copyToClipboard(apiKeys.private)"
                                :disabled="!apiKeys.private"
                                class="bg-gray-600 hover:bg-gray-700 disabled:opacity-50 text-white p-2 rounded">
                            <component :is="Copy" class="w-4 h-4" />
                        </button>
                    </div>
                </div>

                <div class="flex justify-end">
                    <button @click="regenerateApiKeys"
                            :disabled="apiKeys.loading"
                            class="flex items-center text-sm bg-red-600 hover:bg-red-700 text-white disabled:opacity-50 transition-colors py-2 px-4 rounded-md">
                        <component :is="RefreshCw" class="w-4 h-4 mr-1" />
                        API-Schlüssel regenerieren
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import {
    Code, Globe, Layers, Settings, Copy, Eye,
    EyeOff, Palette, ToggleLeft, Save, Key,
    ToyBrick, RefreshCw, Type, ExternalLink,
    ListOrdered, Package, MousePointerClick,
    GripVertical, X, Info
} from 'lucide-vue-next'
import draggable from 'vuedraggable'

// Props
const props = defineProps({
    currentGym: Object
})

// Reactive data
const page = usePage()
const activeMethod = ref('script')
const showPrivateKey = ref(false)
const isSaving = ref(false)
const availableContracts = ref([])
const selectedContracts = ref([])
const contractsLoading = ref(false)

const settings = ref({
    widget_enabled: props.currentGym.widget_enabled || false,
    colors: {
        primary: '#3b82f6',
        secondary: '#f8fafc',
        text: '#1f2937'
    },
    texts: {
        title: 'Wähle deinen Tarif',
        welcome_message: `Willkommen bei ${props.currentGym.name || 'unserem Studio'}`,
        success_message: 'Vielen Dank für deine Registrierung!'
    },
    features: {
        show_duration_selector: true,
        show_goals_selection: true,
        require_birth_date: true,
        require_phone: true
    },
    contracts: {
        selected_ids: [],
        auto_sort: false
    },
    integrations: {
        google_recaptcha: false
    },
    ...(props.currentGym.widget_settings || {})
})

const apiKeys = ref({
    public: '',
    private: '',
    loading: false,
    error: null
})

const integrationMethods = [
    { key: 'script', label: 'Script', icon: Code },
    { key: 'iframe', label: 'iFrame', icon: Globe },
    { key: 'webcomponent', label: 'Web Component', icon: Layers },
    { key: 'api', label: 'REST API', icon: Settings }
]

// Computed properties for code snippets
const scriptCode = computed(() => `<!-- gymportal.io Widget Integration -->
<div id="gymportal-widget"></div>
<script>
(function() {
    const script = document.createElement("script");
    script.src = "${window.location.origin}/embed/widget.js";
    script.onload = function() {
        GymportalWidget.init({
            containerId: "gymportal-widget",
            apiEndpoint: "${window.location.origin}",
            apiKey: "${apiKeys.value.public}",
            studioId: "${props.currentGym.id}"
        });
    };
    document.head.appendChild(script);
})();
<` + `/script>`)

const iframeCode = computed(() => `<!-- Fitness Widget iFrame -->
<iframe
  src="https://widgets.fitnessstudio.com/fitness?studio=${props.currentGym?.id || 'studio-123'}"
  width="100%"
  height="800"
  frameborder="0"
  style="border-radius: 8px;">
</iframe>`)

const webComponentCode = computed(() => `<!-- Fitness Widget Web Component -->
<script src="https://widgets.fitnessstudio.com/fitness-component.js"><` + `/script>

<gymportal-widget
  studio-id="${props.currentGym?.id || 'studio-123'}"
  api-key="${apiKeys.value.public}"
  height="800">
</gymportal-widget>`)

const apiEndpoints = computed(() => `// Fitness Widget API Endpoints
GET /api/v1/studios/${props.currentGym?.id || 'studio-123'}/packages
Authorization: Bearer ${apiKeys.value.private}

GET /api/v1/studios/${props.currentGym?.id || 'studio-123'}/config
Authorization: Bearer ${apiKeys.value.public}

POST /api/v1/memberships
Authorization: Bearer ${apiKeys.value.private}
Content-Type: application/json

{
  "studioId": "${props.currentGym?.id || 'studio-123'}",
  "packageId": "premium",
  "customerData": {
    "firstName": "Max",
    "lastName": "Mustermann",
    "email": "max@example.com"
  }
}`)

const sdkCode = computed(() => `<!-- Fitness SDK Integration -->
<script src="https://cdn.fitnessstudio.com/fitness-sdk.js"><` + `/script>
<script>
const fitness = new FitnessSDK('${apiKeys.value.public}', '${props.currentGym?.id || 'studio-123'}');

// Packages laden
async function loadPackages() {
  const packages = await fitness.getPackages();

  packages.forEach(pkg => {
    const div = document.createElement('div');
    div.innerHTML = \`
      <h3>\${pkg.name}</h3>
      <p>€\${pkg.price}/Monat</p>
      <button onclick="selectPackage('\${pkg.id}')">Auswählen</button>
    \`;
    document.getElementById('packages').appendChild(div);
  });
}

// Mitgliedschaft erstellen
async function createMembership(membershipData) {
  return await fitness.createMembership(membershipData);
}
<` + `/script>`)

// Methods
const isContractSelected = (contractId) => {
    return selectedContracts.value.some(c => c.id === contractId)
}

const selectContract = (contract) => {
    if (isContractSelected(contract.id) || selectedContracts.value.length >= 3) {
        return
    }
    selectedContracts.value.push({...contract})
    updateSelectedIds()
}

const toggleContract = (contract) => {
    if (isContractSelected(contract.id)) {
        removeContract(contract.id)
    } else {
        selectContract(contract)
    }
}

const removeContract = (contractId) => {
    selectedContracts.value = selectedContracts.value.filter(c => c.id !== contractId)
    updateSelectedIds()
}

const clearSelection = () => {
    selectedContracts.value = []
    updateSelectedIds()
}

const updateSelectedIds = () => {
    settings.value.contracts.selected_ids = selectedContracts.value.map(c => c.id)
}

const loadAvailableContracts = async () => {
    contractsLoading.value = true
    try {
        const response = await axios.get(route('admin.widget.contracts'))
        availableContracts.value = response.data.contracts

        // Nach dem Laden der verfügbaren Verträge, lade die bereits ausgewählten
        loadSelectedContracts()
    } catch (error) {
        console.error('Fehler beim Laden der Verträge:', error)
        page.props.flash.error = 'Verträge konnten nicht geladen werden'
    } finally {
        contractsLoading.value = false
    }
}

const loadSelectedContracts = () => {
    // Prüfe ob es bereits gespeicherte Vertragsauswahlen gibt
    if (props.currentGym?.widget_settings?.contracts?.selected_ids &&
        Array.isArray(props.currentGym.widget_settings.contracts.selected_ids) &&
        props.currentGym.widget_settings.contracts.selected_ids.length > 0) {

        const savedIds = props.currentGym.widget_settings.contracts.selected_ids

        // Filtere die verfügbaren Verträge nach den gespeicherten IDs
        const contractsToSelect = availableContracts.value
            .filter(contract => savedIds.includes(contract.id))

        // Sortiere sie in der gespeicherten Reihenfolge
        selectedContracts.value = contractsToSelect.sort((a, b) => {
            return savedIds.indexOf(a.id) - savedIds.indexOf(b.id)
        })

        // Setze auch die auto_sort Option
        if (props.currentGym.widget_settings.contracts.auto_sort !== undefined) {
            settings.value.contracts.auto_sort = props.currentGym.widget_settings.contracts.auto_sort
        }
    }
}

const openWidgetPreview = () => {
    const previewUrl = `${window.location.origin}/widget-test`
    window.open(previewUrl, '_blank', 'width=1200,height=800,scrollbars=yes,resizable=yes')
}

const saveWidgetConfig = async () => {
    isSaving.value = true
    try {
        await axios.put(route('admin.widget.update'), {
            widget_enabled: settings.value.widget_enabled,
            widget_settings: {
                ...settings.value,
                contracts: {
                    selected_ids: selectedContracts.value.map(c => c.id),
                    auto_sort: settings.value.contracts.auto_sort
                }
            }
        })

        page.props.flash.message = 'Einstellungen erfolgreich gespeichert!'
    } catch (error) {
        console.error('Fehler beim Speichern:', error)
        page.props.flash.error = 'Fehler beim Speichern der Einstellungen'
    } finally {
        isSaving.value = false
    }
}

const copyToClipboard = async (text) => {
    try {
        await navigator.clipboard.writeText(text)
        // You could show a toast notification here
        console.log('Copied to clipboard')
    } catch (error) {
        console.error('Failed to copy:', error)
    }
}

const togglePrivateKey = () => {
    showPrivateKey.value = !showPrivateKey.value
}

const regenerateApiKeys = async () => {
    if (confirm('Möchten Sie wirklich neue API-Schlüssel generieren? Die alten Schlüssel werden dadurch ungültig!')) {
        try {
            const response = await axios.post(route('admin.widget.regenerate-api-key'))
            apiKeys.value.public = response.data.api_key
            page.props.flash.message = 'API-Keys erfolgreich neu generiert!'
        } catch (error) {
            console.error('Fehler beim Generieren:', error)
            page.props.flash.error = 'Fehler beim Generieren des API-Keys'
        }
    }
}

const loadApiKeys = async () => {
    if (apiKeys.value.loading) return

    apiKeys.value.loading = true
    apiKeys.value.error = null

    try {
        const response = await axios.get(route('admin.widget.api-keys'))
        apiKeys.value.public = response.data.public_key
        apiKeys.value.private = response.data.private_key
    } catch (error) {
        console.error('Fehler beim Laden der API Keys:', error)
        apiKeys.value.error = 'API-Schlüssel konnten nicht geladen werden'
    } finally {
        apiKeys.value.loading = false
    }
}

// API Keys nur laden, wenn Widget aktiviert ist
watch(() => settings.value.widget_enabled, (newValue) => {
    if (newValue && !apiKeys.value.public && !apiKeys.value.loading) {
        loadApiKeys()
    }
})

// Initial laden, wenn Widget bereits aktiviert ist
onMounted(() => {
    if (settings.value.widget_enabled) {
        loadApiKeys()
    }

    // Lade verfügbare Verträge
    loadAvailableContracts()
})
</script>

<style scoped>
/* Toggle Switch Styles */
.peer:checked + div {
    background-color: #2563eb;
}

.peer:checked + div:after {
    transform: translateX(100%);
    border-color: white;
}

/* Code block styling */
pre {
    font-family: 'Fira Code', 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
    font-size: 0.875rem;
    line-height: 1.5;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .grid-cols-1.md\\:grid-cols-2 {
        grid-template-columns: 1fr;
    }
}

/* Styles for Drag & Drop */
.sortable-ghost {
    opacity: 0.5;
}

.sortable-drag {
    background: white;
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
}
</style>
