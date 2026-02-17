<template>
    <AppLayout title="Einstellungen">
        <template #header>
            Einstellungen
        </template>

        <div class="max-w-6xl mx-auto">
            <!-- Tabs -->
            <div class="border-b border-gray-200 mb-6">
                <nav class="-mb-px flex space-x-8">
                    <button v-for="tab in tabs" :key="tab.key" @click="activeTab = tab.key" :class="[
                        'py-2 px-1 border-b-2 font-medium text-sm',
                        activeTab === tab.key
                            ? 'border-indigo-500 text-indigo-600'
                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                    ]">
                        <component :is="tab.icon" class="w-4 h-4 mr-2 inline" />
                        {{ tab.label }}
                    </button>
                </nav>
            </div>

            <!-- Success/Error Messages -->
            <div v-if="successMessage"
                class="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded">
                {{ successMessage }}
            </div>
            <div v-if="errorMessage" class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded">
                {{ errorMessage }}
            </div>

            <!-- Gym Settings -->
            <div v-if="activeTab === 'gym'" class="space-y-6">
                <div class="bg-white shadow-sm rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                            Stammdaten der Organisation
                        </h3>

                        <form @submit.prevent="saveGymSettings" class="space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm/6 font-medium text-gray-700">Name <span class="text-red-500">*</span></label>
                                    <input v-model="gymForm.name" type="text" class="block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-700 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6" required />
                                    <p class="mt-1 text-xs text-gray-500">
                                        Änderungen am Namen werden nach außen kommuniziert und beeinflussen die URL des Mitglieder-Bereichs und der PWA.
                                    </p>
                                </div>

                                <div>
                                    <label class="block text-sm/6 font-medium text-gray-700">Anzeigename (optional)</label>
                                    <input v-model="gymForm.display_name" type="text" class="block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-700 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6" />
                                    <p class="mt-1 text-xs text-gray-500">
                                        Wird nur intern im System und im Organisations-Wechsler angezeigt. Bei leerem Feld wird der Name verwendet.
                                    </p>
                                </div>

                                <div class="md:col-span-2">
                                    <label class="block text-sm/6 font-medium text-gray-700">Beschreibung</label>
                                    <textarea v-model="gymForm.description" rows="3" class="block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-700 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6"></textarea>
                                </div>

                                <LogoUpload v-model="gymForm.logo_path" :current-gym="currentGym" />

                                <div>
                                    <label class="block text-sm/6 font-medium text-gray-700">Straße und Hausnummer <span class="text-red-500">*</span></label>
                                    <input v-model="gymForm.address" type="text" class="block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-700 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6" />
                                </div>

                                <div>
                                    <label class="block text-sm/6 font-medium text-gray-700">Stadt <span class="text-red-500">*</span></label>
                                    <input v-model="gymForm.city" type="text" class="block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-700 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6" />
                                </div>

                                <div>
                                    <label class="block text-sm/6 font-medium text-gray-700">Postleitzahl <span class="text-red-500">*</span></label>
                                    <input v-model="gymForm.postal_code" type="text" class="block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-700 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6" />
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Land <span class="text-red-500">*</span></label>
                                    <select v-model="gymForm.country" class="w-full p-2 border border-gray-300 rounded-md bg-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                        <option value="DE">Deutschland</option>
                                        <option value="AT">Österreich</option>
                                        <option value="CH">Schweiz</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-sm/6 font-medium text-gray-700">Breitengrad (Latitude)</label>
                                    <input v-model="gymForm.latitude" type="number" step="any" min="-90" max="90" placeholder="z.B. 52.520008" class="block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-700 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6" />
                                    <p class="mt-1 text-xs text-gray-500">
                                        Geografische Koordinate für Kartenanzeige (-90 bis 90)
                                    </p>
                                </div>

                                <div>
                                    <label class="block text-sm/6 font-medium text-gray-700">Längengrad (Longitude)</label>
                                    <input v-model="gymForm.longitude" type="number" step="any" min="-180" max="180" placeholder="z.B. 13.404954" class="block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-700 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6" />
                                    <p class="mt-1 text-xs text-gray-500">
                                        Geografische Koordinate für Kartenanzeige (-180 bis 180)
                                    </p>
                                </div>

                                <div>
                                    <label class="block text-sm/6 font-medium text-gray-700">Telefon <span class="text-red-500">*</span></label>
                                    <input v-model="gymForm.phone" type="tel" class="block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-700 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6" />
                                </div>

                                <div>
                                    <label class="block text-sm/6 font-medium text-gray-700">E-Mail <span class="text-red-500">*</span></label>
                                    <input v-model="gymForm.email" type="email" class="block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-700 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6" />
                                </div>

                                <div>
                                    <label class="block text-sm/6 font-medium text-gray-700">Website</label>
                                    <input v-model="gymForm.website" type="url" class="block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-700 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6" />
                                </div>
                            </div>

                            <!-- SEPA Banking Information Section -->
                            <div v-if="sepaDirectDebitEnabled" class="mt-8 pt-8 border-t border-gray-200">
                                <h4 class="text-base font-medium text-gray-900 mb-2">
                                    SEPA-Bankverbindung
                                </h4>
                                <p class="text-sm text-gray-600 mb-6">
                                    Diese Daten werden für Vorkasse, Zahlungserinnerungen und SEPA-Lastschriften per PAIN.008 Export verwendet.
                                    <strong>Hinweis:</strong> Diese Angaben werden nicht für die Belastung der SaaS-Gebühren verwendet.
                                </p>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label class="block text-sm/6 font-medium text-gray-700 mb-1">Kontoinhaber</label>
                                        <input
                                            v-model="gymForm.account_holder"
                                            type="text"
                                            placeholder="Musterfirma GmbH"
                                            class="block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-700 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6" />
                                    </div>

                                    <div>
                                        <label class="block text-sm/6 font-medium text-gray-700 mb-1">IBAN</label>
                                        <IbanInput
                                            v-model="gymForm.iban"
                                            :required="false"
                                            placeholder="DE89 3704 0044 0532 0130 00" />
                                    </div>

                                    <div>
                                        <label class="block text-sm/6 font-medium text-gray-700">BIC</label>
                                        <input
                                            v-model="gymForm.bic"
                                            type="text"
                                            maxlength="11"
                                            placeholder="COBADEFFXXX"
                                            class="block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-700 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6" />
                                        <p class="mt-1 text-xs text-gray-500">
                                            8 oder 11 Zeichen
                                        </p>
                                    </div>

                                    <div>
                                        <label class="block text-sm/6 font-medium text-gray-700">Gläubiger-Identifikationsnummer</label>
                                        <input
                                            v-model="gymForm.creditor_identifier"
                                            type="text"
                                            maxlength="35"
                                            placeholder="DE98ZZZ09999999999"
                                            class="block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-700 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6" />
                                        <p class="mt-1 text-xs text-gray-500">
                                            Wird von der Bundesbank vergeben
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="flex justify-end">
                                <button
                                    type="submit"
                                    :disabled="isSubmittingGym"
                                    class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2 px-4 rounded-md disabled:opacity-50">
                                    Speichern
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Legal URLs Manager -->
                <LegalUrlsManager
                    :current-gym="currentGym"
                    @success="handleSuccess"
                    @error="handleError" />

                <div class="bg-white shadow-sm rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                            Organisation löschen
                        </h3>
                        <p class="text-sm text-gray-600 mt-1">
                            Sobald eine Organisation gelöscht wird, werden alle zugehörigen Ressourcen und Daten
                            dauerhaft gelöscht.
                            Laden Sie vor dem Löschen dieser Organisation alle Daten und Informationen herunter, die Sie
                            behalten möchten.
                        </p>

                        <form @submit.prevent="deleteGym" class="space-y-6">
                            <div class="flex justify-end">
                                <button
                                    type="submit"
                                    :disabled="isSubmittingGym"
                                    class="bg-red-600 hover:bg-red-700 text-white font-medium py-2 px-4 rounded-md disabled:opacity-50">
                                    Organisation löschen
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Team Management -->
            <div v-if="activeTab === 'team'">
                <TeamManagement
                    :current-user="user"
                    :current-gym="currentGym"
                    :gym-users="gymUsers"
                    @success="handleSuccess"
                    @error="handleError" />
            </div>

            <!-- Payment Settings -->
            <div v-if="activeTab === 'payments'" class="space-y-6">
                <!-- Übersicht Zahlungsarten -->
                <div class="bg-white shadow-sm rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                            Zahlungsarten-Übersicht
                        </h3>

                        <!-- Status Anzeige wenn Mollie aktiv -->
                        <div v-if="mollieStatus.isActive" class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
                            <div class="flex items-start">
                                <div class="flex-shrink-0">
                                    <svg class="w-5 h-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd"
                                            d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                            clip-rule="evenodd"></path>
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h4 class="text-sm font-medium text-green-800">Mollie Integration aktiv</h4>
                                    <div class="mt-1">
                                        <p class="text-sm text-green-700">
                                            {{ mollieStatus.methodCount }} Zahlungsmethoden verfügbar
                                            • {{ mollieStatus.isTestMode ? 'Test-Modus' : 'Live-Modus' }}
                                        </p>
                                    </div>
                                    <div class="mt-2">
                                        <button @click="editMollieConfig"
                                            class="text-sm text-green-800 hover:text-green-900 font-medium">
                                            Konfiguration bearbeiten →
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Current payment methods from Model -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div v-for="method in currentPaymentMethods" :key="method.key"
                                class="border border-gray-200 rounded-lg p-4">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 rounded-lg flex items-center justify-center mr-3"
                                            :class="getMethodIconBg(method.icon)">
                                            <component :is="getIconComponent(method.icon)"
                                                :class="getMethodIconColor(method.icon)" class="w-5 h-5" />
                                        </div>
                                        <div>
                                            <h4 class="text-sm font-medium text-gray-900">{{ method.name }}</h4>
                                            <p class="text-xs text-gray-500">{{ method.description }}</p>
                                            <p v-if="method.is_overridden" class="text-xs text-orange-600 mt-1">
                                                ⚠️ Durch externe Integration überschrieben
                                            </p>
                                        </div>
                                    </div>
                                    <div class="flex items-center">
                                        <span :class="method.enabled ? 'text-green-600' : 'text-gray-400'"
                                            class="text-xs font-medium">
                                            {{ method.enabled ? 'Aktiv' : 'Inaktiv' }}
                                        </span>
                                        <div class="ml-2 w-2 h-2 rounded-full"
                                            :class="method.enabled ? 'bg-green-400' : 'bg-gray-300'"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Default Payment Methods config -->
                <div class="bg-white shadow-sm rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                            Standard Zahlungsarten
                        </h3>
                        <p class="text-sm text-gray-600 mb-6">
                            Grundlegende Zahlungsarten, die standardmäßig zur Verfügung stehen.
                        </p>

                        <div class="space-y-4">
                            <div v-for="method in standardMethods" :key="method.key"
                                class="flex items-center justify-between p-4 border border-gray-200 rounded-lg">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 rounded-lg flex items-center justify-center mr-3"
                                        :class="getMethodIconBg(method.icon)">
                                        <component :is="getIconComponent(method.icon)"
                                            :class="getMethodIconColor(method.icon)" class="w-5 h-5" />
                                    </div>
                                    <div>
                                        <h4 class="text-sm font-medium text-gray-900">{{ method.name }}</h4>
                                        <p class="text-xs text-gray-500">{{ method.description }}</p>
                                        <div class="flex items-center space-x-3 mt-1">
                                            <span v-if="method.requires_mandate" class="text-xs text-orange-600">
                                                📝 SEPA-Mandat erforderlich
                                            </span>
                                        </div>
                                        <p v-if="method.is_overridden" class="text-xs text-orange-600 mt-1">
                                            ⚠️ Durch externe Integration überschrieben
                                        </p>
                                    </div>
                                </div>
                                <div class="flex items-center space-x-3">
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox"
                                            v-model="method.enabled"
                                            :disabled="method.is_overridden"
                                            @change="updateStandardMethod(method)"
                                            class="sr-only peer">
                                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600 disabled:opacity-50 disabled:cursor-not-allowed"></div>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- External Integrations -->
                <div class="bg-white shadow-sm rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <div class="flex justify-between items-center mb-4">
                            <div>
                                <h3 class="text-lg leading-6 font-medium text-gray-900">
                                    Externe Zahlungsdienstleister
                                </h3>
                                <p class="text-sm text-gray-600 mt-1">
                                    Integrieren Sie externe Zahlungsdienstleister für erweiterte Funktionen.
                                </p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Mollie Integration -->
                            <div class="border border-gray-200 rounded-lg p-6">
                                <div class="flex items-center mb-4">
                                    <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center mr-4">
                                        <svg class="w-6 h-6 text-orange-600" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm0 2.4c5.302 0 9.6 4.298 9.6 9.6s-4.298 9.6-9.6 9.6S2.4 17.302 2.4 12 6.698 2.4 12 2.4z"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <h4 class="text-sm font-medium text-gray-900">Mollie</h4>
                                        <p class="text-xs text-gray-500">Europäischer Zahlungsdienstleister</p>
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <div class="flex items-center justify-between text-xs text-gray-600 mb-2">
                                        <span>Status:</span>
                                        <span :class="mollieStatus.isActive ? 'text-green-600' : 'text-gray-500'">
                                            {{ mollieStatus.isActive ? 'Konfiguriert' : 'Nicht konfiguriert' }}
                                        </span>
                                    </div>
                                    <div v-if="mollieStatus.isActive" class="text-xs text-gray-600 space-y-1">
                                        <div class="flex justify-between">
                                            <span>Modus:</span>
                                            <span>{{ mollieStatus.isTestMode ? 'Test' : 'Live' }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span>Methoden:</span>
                                            <span>{{ mollieStatus.methodCount }}</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="space-y-2">
                                    <button v-if="!mollieStatus.isActive"
                                        @click="setupMollie"
                                        class="w-full bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium py-2 px-4 rounded-md">
                                        Einrichten
                                    </button>
                                    <template v-else>
                                        <button @click="editMollieConfig"
                                            class="w-full bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium py-2 px-4 rounded-md mb-2">
                                            Konfiguration bearbeiten
                                        </button>
                                        <button @click="removeMollieConfig"
                                            class="w-full bg-red-50 hover:bg-red-100 text-red-600 text-sm font-medium py-2 px-4 rounded-md border border-red-200">
                                            Integration entfernen
                                        </button>
                                    </template>
                                </div>
                            </div>

                            <!-- Placeholder for more Integrations -->
                            <div class="border border-gray-200 rounded-lg p-6 bg-gray-50">
                                <div class="flex items-center mb-4">
                                    <div class="w-12 h-12 bg-gray-200 rounded-lg flex items-center justify-center mr-4">
                                        <component :is="Plus" class="w-6 h-6 text-gray-400" />
                                    </div>
                                    <div>
                                        <h4 class="text-sm font-medium text-gray-500">Weitere Integrationen</h4>
                                        <p class="text-xs text-gray-400">Kommen bald</p>
                                    </div>
                                </div>
                                <p class="text-xs text-gray-500 mb-4">
                                    Weitere Zahlungsdienstleister wie Stripe, PayPal und andere werden in Zukunft verfügbar sein.
                                </p>
                                <button disabled
                                    class="w-full bg-gray-200 text-gray-400 text-sm font-medium py-2 px-4 rounded-md cursor-not-allowed">
                                    Bald verfügbar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Email Templates -->
            <div v-if="activeTab === 'emails'">
                <EmailTemplatesWidget :current-gym="currentGym" />
            </div>

            <!-- Contracts -->
            <div v-if="activeTab === 'contracts'" class="space-y-6">
                <ContractWidget :current-gym="currentGym" />
            </div>

            <!-- Contract Settings -->
            <div v-if="activeTab === 'contract_settings'" class="space-y-6">
                <ContractSettingsWidget :current-gym="currentGym" />
            </div>

            <!-- PWA Settings -->
            <div v-if="activeTab === 'pwa'" class="space-y-6">
                <PwaSettingsWidget
                    :current-gym="currentGym"
                    @success="handleSuccess"
                    @error="handleError" />
            </div>
        </div>

        <!-- Mollie Setup Modal -->
        <div v-if="showMollieSetup" class="fixed inset-0 bg-gray-500/75 overflow-y-auto h-full w-full z-50" @click="closeMollieSetup">
            <div class="relative top-20 mx-auto p-5 border border-gray-50 w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white" @click.stop>
                <MollieSetupWizard
                    :organization="currentGym"
                    @setup-completed="onMollieSetupCompleted"
                    @configuration-saved="onMollieConfigSaved" />
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import {
    Building2, Users, Plus, Signature, CreditCard,
    Wallet, DollarSign, FileText, HandCoins, Mail, Smartphone, FileSignature
} from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import LogoUpload from '@/Components/LogoUpload.vue'
import ContractWidget from '@/Components/ContractWidget.vue'
import MollieSetupWizard from '@/Components/MollieSetupWizard.vue'
import EmailTemplatesWidget from '@/Components/EmailTemplatesWidget.vue'
import TeamManagement from '@/Components/TeamManagement.vue'
import IbanInput from '@/Components/IbanInput.vue'
import LegalUrlsManager from '@/Components/LegalUrlsManager.vue'
import PwaSettingsWidget from '@/Components/PwaSettingsWidget.vue'
import ContractSettingsWidget from '@/Components/ContractSettingsWidget.vue'

// Props
const props = defineProps({
    user: Object,
    currentGym: Object,
    gymUsers: Array
})

// Reactive data
const activeTab = ref('gym')
const showMollieSetup = ref(false)
const isSubmittingGym = ref(false)
const successMessage = ref('')
const errorMessage = ref('')

// Payment methods state - will be loaded by the Model
const standardMethods = ref([])
const currentPaymentMethods = ref([])

const mollieStatus = ref({
    isActive: false,
    isTestMode: false,
    methodCount: 0,
    enabledMethods: []
})

const tabs = [
    { key: 'gym', label: 'Organisation', icon: Building2 },
    { key: 'team', label: 'Team', icon: Users },
    { key: 'payments', label: 'Zahlungsarten', icon: CreditCard },
    { key: 'emails', label: 'E-Mail-Vorlagen', icon: Mail },
    { key: 'contracts', label: 'Online-Widget', icon: Signature },
    { key: 'contract_settings', label: 'Verträge', icon: FileSignature },
    { key: 'pwa', label: 'App (PWA)', icon: Smartphone },
]

// Computed property to check if SEPA Direct Debit is enabled
const sepaDirectDebitEnabled = computed(() => {
    // Check if SEPA direct debit is enabled in standard methods
    const hasStandardSepa = standardMethods.value.some(
        method => method.key === 'sepa_direct_debit' && method.enabled
    )

    // Check if Mollie direct debit is enabled
    const hasMollieSepa = mollieStatus.value.enabledMethods.includes('directdebit')

    return hasStandardSepa || hasMollieSepa
})

const gymForm = ref({
    name: props.currentGym?.name || '',
    display_name: props.currentGym?.display_name || '',
    slug: props.currentGym?.slug || '',
    description: props.currentGym?.description || '',
    address: props.currentGym?.address || '',
    city: props.currentGym?.city || '',
    postal_code: props.currentGym?.postal_code || '',
    country: props.currentGym?.country || 'DE',
    latitude: props.currentGym?.latitude || null,
    longitude: props.currentGym?.longitude || null,
    phone: props.currentGym?.phone || '',
    email: props.currentGym?.email || '',
    account_holder: props.currentGym?.account_holder || '',
    iban: props.currentGym?.iban || '',
    bic: props.currentGym?.bic || '',
    creditor_identifier: props.currentGym?.creditor_identifier || '',
    website: props.currentGym?.website || '',
    logo_path: props.currentGym?.logo_path || '',
})

// Icon mapping for Payment Methods
const iconComponents = {
    Wallet,
    HandCoins,
    FileText,
    DollarSign,
    CreditCard
}

// Methods
const getIconComponent = (iconName) => {
    return iconComponents[iconName] || CreditCard
}

const getMethodIconBg = (iconName) => {
    const iconBgMap = {
        'Wallet': 'bg-green-100',
        'HandCoins': 'bg-yellow-100',
        'FileText': 'bg-blue-100',
        'DollarSign': 'bg-purple-100',
        'CreditCard': 'bg-orange-100'
    }
    return iconBgMap[iconName] || 'bg-gray-100'
}

const getMethodIconColor = (iconName) => {
    const iconColorMap = {
        'Wallet': 'text-green-600',
        'HandCoins': 'text-yellow-600',
        'FileText': 'text-blue-600',
        'DollarSign': 'text-purple-600',
        'CreditCard': 'text-orange-600'
    }
    return iconColorMap[iconName] || 'text-gray-600'
}

const saveGymSettings = async () => {
    isSubmittingGym.value = true

    try {
        const response = await axios.put(route('settings.gym.update', props.currentGym.id), gymForm.value)

        if (response.data.gym) {
            Object.assign(gymForm.value, response.data.gym)
        }

        successMessage.value = 'Organisation erfolgreich gespeichert!'
        setTimeout(() => successMessage.value = '', 3000)
    } catch (error) {
        errorMessage.value = 'Fehler beim Speichern der Organisation'
        setTimeout(() => errorMessage.value = '', 3000)
    } finally {
        isSubmittingGym.value = false
    }
}

const deleteGym = async () => {
    if (!confirm('Möchten Sie die Organisation wirklich löschen?')) {
        return
    }

    isSubmittingGym.value = true

    try {
        await router.delete(route('gyms.remove', props.currentGym.id), {}, {
            onSuccess: () => {
                router.visit('/dashboard')
            },
            onError: () => {
                errorMessage.value = 'Fehler beim Löschen der Organisation'
                setTimeout(() => errorMessage.value = '', 3000)
            },
            onFinish: () => {
                isSubmittingGym.value = false
            }
        })
    } catch (error) {
        console.error('Error deleting gym:', error)
        isSubmittingGym.value = false
    }
}

// Event handlers for TeamManagement component
const handleSuccess = (message) => {
    successMessage.value = message
    setTimeout(() => successMessage.value = '', 3000)
}

const handleError = (message) => {
    errorMessage.value = message
    setTimeout(() => errorMessage.value = '', 3000)
}

// Payment methods - New: Load from Model
const loadPaymentMethods = async () => {
    try {
        const response = await axios.get(route('settings.payment-methods.overview'))
        const data = response.data

        standardMethods.value = data.methods.standard || []
        currentPaymentMethods.value = data.methods.enabled || []

        // Mollie Status update
        mollieStatus.value = {
            isActive: data.mollie_status.is_active || false,
            isTestMode: data.mollie_status.is_test_mode || false,
            methodCount: data.mollie_status.method_count || 0,
            enabledMethods: data.methods.mollie || []
        }

    } catch (error) {
        console.error('Fehler beim Laden der Zahlungsmethoden:', error)
    }
}

const updateStandardMethod = async (method) => {
    try {
        await axios.put(route('settings.payment-methods.update'), {
            method: method.key,
            enabled: method.enabled
        })

        successMessage.value = `${method.name} ${method.enabled ? 'aktiviert' : 'deaktiviert'}!`
        setTimeout(() => successMessage.value = '', 3000)

        // Payment methods refresh
        await loadPaymentMethods()
    } catch (error) {
        // Revert change on error
        method.enabled = !method.enabled
        errorMessage.value = 'Fehler beim Aktualisieren der Zahlungsmethode'
        setTimeout(() => errorMessage.value = '', 3000)
    }
}

const setupMollie = () => {
    showMollieSetup.value = true
}

const editMollieConfig = () => {
    showMollieSetup.value = true
}

const closeMollieSetup = () => {
    showMollieSetup.value = false
}

const onMollieSetupCompleted = async () => {
    await loadPaymentMethods()
    successMessage.value = 'Mollie Integration erfolgreich eingerichtet!'
    router.reload({
        only: ['currentGym'],
        preserveScroll: true,
        preserveState: false,
        onSuccess: () => {
            setTimeout(() => successMessage.value = '', 3000)
        }
    })
}

const onMollieConfigSaved = async (config) => {
    mollieStatus.value.isActive = true
    mollieStatus.value.isTestMode = config.test_mode
    mollieStatus.value.methodCount = config.enabled_methods?.length || 0
    await loadPaymentMethods()
}

const removeMollieConfig = async () => {
    if (!confirm('Möchten Sie die Mollie-Integration wirklich entfernen?')) {
        return
    }

    try {
        await axios.delete(route('settings.mollie.remove'))

        mollieStatus.value = {
            isActive: false,
            isTestMode: false,
            methodCount: 0,
            enabledMethods: []
        }

        await loadPaymentMethods()
        successMessage.value = 'Mollie Integration erfolgreich entfernt!'
        router.reload({
            only: ['currentGym'],
            preserveScroll: true,
            preserveState: false,
            onSuccess: () => {
                setTimeout(() => successMessage.value = '', 3000)
            }
        })
    } catch (error) {
        console.error(error)
        errorMessage.value = 'Fehler beim Entfernen der Mollie Integration'
        setTimeout(() => errorMessage.value = '', 3000)
    }
}

// Load data on mount
onMounted(() => {
    loadPaymentMethods()
})
</script>
