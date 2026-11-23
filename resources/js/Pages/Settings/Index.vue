<template>
    <AppLayout title="Einstellungen">
        <template #header>
            Einstellungen
        </template>

        <div class="max-w-6xl mx-auto">
            <!-- Tabs -->
            <TabRail v-model="activeTab" :tabs="tabs" class="mb-6" />

            <!-- Gym Settings -->
            <div v-if="activeTab === 'gym'" class="space-y-6">
                <div class="bg-surface shadow-sm rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg leading-6 font-semibold text-ink mb-4">
                            Stammdaten der Organisation
                        </h3>

                        <form @submit.prevent="saveGymSettings" class="space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="settings-gym-form-name" class="block text-sm/6 font-medium text-ink-secondary">Name <span class="text-danger">*</span></label>
                                    <input id="settings-gym-form-name" v-model="gymForm.name" type="text" class="field block w-full px-3 py-1.5" required />
                                    <p class="mt-1 text-xs text-ink-muted">
                                        Änderungen am Namen werden nach außen kommuniziert und beeinflussen die URL des Mitglieder-Bereichs und der PWA.
                                    </p>
                                </div>

                                <div>
                                    <label for="settings-gym-form-display-name" class="block text-sm/6 font-medium text-ink-secondary">Anzeigename (optional)</label>
                                    <input id="settings-gym-form-display-name" v-model="gymForm.display_name" type="text" class="field block w-full px-3 py-1.5" />
                                    <p class="mt-1 text-xs text-ink-muted">
                                        Wird nur intern im System und im Organisations-Wechsler angezeigt. Bei leerem Feld wird der Name verwendet.
                                    </p>
                                </div>

                                <div class="md:col-span-2">
                                    <label for="settings-gym-form-description" class="block text-sm/6 font-medium text-ink-secondary">Beschreibung</label>
                                    <textarea id="settings-gym-form-description" v-model="gymForm.description" rows="3" class="field block w-full px-3 py-1.5"></textarea>
                                </div>

                                <LogoUpload v-model="gymForm.logo_path" :current-gym="currentGym" />

                                <div>
                                    <label for="settings-gym-form-address" class="block text-sm/6 font-medium text-ink-secondary">Straße und Hausnummer <span class="text-danger">*</span></label>
                                    <input id="settings-gym-form-address" v-model="gymForm.address" type="text" class="field block w-full px-3 py-1.5" />
                                </div>

                                <div>
                                    <label for="settings-gym-form-city" class="block text-sm/6 font-medium text-ink-secondary">Stadt <span class="text-danger">*</span></label>
                                    <input id="settings-gym-form-city" v-model="gymForm.city" type="text" class="field block w-full px-3 py-1.5" />
                                </div>

                                <div>
                                    <label for="settings-gym-form-postal-code" class="block text-sm/6 font-medium text-ink-secondary">Postleitzahl <span class="text-danger">*</span></label>
                                    <input id="settings-gym-form-postal-code" v-model="gymForm.postal_code" type="text" class="field block w-full px-3 py-1.5" />
                                </div>

                                <div>
                                    <label for="settings-gym-form-country" class="block text-sm font-medium text-ink-secondary mb-1">Land <span class="text-danger">*</span></label>
                                    <select id="settings-gym-form-country" v-model="gymForm.country" class="field w-full p-2">
                                        <option value="DE">Deutschland</option>
                                        <option value="AT">Österreich</option>
                                        <option value="CH">Schweiz</option>
                                    </select>
                                </div>

                                <div>
                                    <label for="settings-gym-form-latitude" class="block text-sm/6 font-medium text-ink-secondary">Breitengrad (Latitude)</label>
                                    <input id="settings-gym-form-latitude" v-model="gymForm.latitude" type="number" step="any" min="-90" max="90" placeholder="z.B. 52.520008" class="field block w-full px-3 py-1.5" />
                                    <p class="mt-1 text-xs text-ink-muted">
                                        Geografische Koordinate für Kartenanzeige (-90 bis 90)
                                    </p>
                                </div>

                                <div>
                                    <label for="settings-gym-form-longitude" class="block text-sm/6 font-medium text-ink-secondary">Längengrad (Longitude)</label>
                                    <input id="settings-gym-form-longitude" v-model="gymForm.longitude" type="number" step="any" min="-180" max="180" placeholder="z.B. 13.404954" class="field block w-full px-3 py-1.5" />
                                    <p class="mt-1 text-xs text-ink-muted">
                                        Geografische Koordinate für Kartenanzeige (-180 bis 180)
                                    </p>
                                </div>

                                <div>
                                    <label for="settings-gym-form-phone" class="block text-sm/6 font-medium text-ink-secondary">Telefon <span class="text-danger">*</span></label>
                                    <input id="settings-gym-form-phone" v-model="gymForm.phone" type="tel" class="field block w-full px-3 py-1.5" />
                                </div>

                                <div>
                                    <label for="settings-gym-form-email" class="block text-sm/6 font-medium text-ink-secondary">E-Mail <span class="text-danger">*</span></label>
                                    <input id="settings-gym-form-email" v-model="gymForm.email" type="email" class="field block w-full px-3 py-1.5" />
                                </div>

                                <div>
                                    <label for="settings-gym-form-website" class="block text-sm/6 font-medium text-ink-secondary">Website</label>
                                    <input id="settings-gym-form-website" v-model="gymForm.website" type="url" class="field block w-full px-3 py-1.5" />
                                </div>
                            </div>

                            <!-- SEPA Banking Information Section -->
                            <div v-if="sepaDirectDebitEnabled" class="mt-8 pt-8 border-t border-line">
                                <h4 class="text-base font-semibold text-ink mb-2">
                                    SEPA-Bankverbindung
                                </h4>
                                <p class="text-sm text-ink-secondary mb-6">
                                    Diese Daten werden für Vorkasse, Zahlungserinnerungen und SEPA-Lastschriften per PAIN.008 Export verwendet.
                                    <strong>Hinweis:</strong> Diese Angaben werden nicht für die Belastung der SaaS-Gebühren verwendet.
                                </p>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label for="settings-gym-form-account-holder" class="block text-sm/6 font-medium text-ink-secondary mb-1">Kontoinhaber</label>
                                        <input id="settings-gym-form-account-holder"
                                            v-model="gymForm.account_holder"
                                            type="text"
                                            placeholder="Musterfirma GmbH"
                                            class="field block w-full px-3 py-1.5" />
                                    </div>

                                    <div>
                                        <label class="block text-sm/6 font-medium text-ink-secondary mb-1">IBAN</label>
                                        <IbanInput
                                            v-model="gymForm.iban"
                                            :required="false"
                                            placeholder="DE89 3704 0044 0532 0130 00" />
                                    </div>

                                    <div>
                                        <label for="settings-gym-form-bic" class="block text-sm/6 font-medium text-ink-secondary">BIC</label>
                                        <input id="settings-gym-form-bic"
                                            v-model="gymForm.bic"
                                            type="text"
                                            maxlength="11"
                                            placeholder="COBADEFFXXX"
                                            class="field block w-full px-3 py-1.5" />
                                        <p class="mt-1 text-xs text-ink-muted">
                                            8 oder 11 Zeichen
                                        </p>
                                    </div>

                                    <div>
                                        <label for="settings-gym-form-creditor-identifier" class="block text-sm/6 font-medium text-ink-secondary">Gläubiger-Identifikationsnummer</label>
                                        <input id="settings-gym-form-creditor-identifier"
                                            v-model="gymForm.creditor_identifier"
                                            type="text"
                                            maxlength="35"
                                            placeholder="DE98ZZZ09999999999"
                                            class="field block w-full px-3 py-1.5" />
                                        <p class="mt-1 text-xs text-ink-muted">
                                            Wird von der Bundesbank vergeben
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="flex justify-end">
                                <button
                                    type="submit"
                                    :disabled="isSubmittingGym"
                                    class="inline-flex justify-center items-center py-2 px-4 border border-transparent bg-brand hover:bg-brand-strong text-white text-sm font-medium rounded-md disabled:opacity-50 disabled:cursor-not-allowed">
                                    Speichern
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <OrganizationSymbolCard :current-gym="currentGym" />

                <div class="bg-surface shadow-sm rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg leading-6 font-semibold text-ink mb-4">
                            Organisation löschen
                        </h3>
                        <p class="text-sm text-ink-secondary mt-1">
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
                                    class="inline-flex justify-center items-center py-2 px-4 border border-transparent bg-danger-solid hover:bg-danger-solid-strong text-white text-sm font-medium rounded-md disabled:opacity-50 disabled:cursor-not-allowed">
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
                    :pending-invitations="pendingInvitations"
                    :is-gym-owner="isGymOwner"
                    @success="handleSuccess"
                    @error="handleError" />
            </div>

            <!-- Payment Settings -->
            <div v-if="activeTab === 'payments'" class="space-y-6">
                <!-- Übersicht Zahlungsarten -->
                <div class="bg-surface shadow-sm rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg leading-6 font-semibold text-ink mb-4">
                            Zahlungsarten-Übersicht
                        </h3>

                        <!-- Status Anzeige wenn Mollie aktiv -->
                        <div v-if="mollieStatus.isActive" class="bg-success-subtle border border-success-line rounded-lg p-4 mb-6">
                            <div class="flex items-start">
                                <div class="flex-shrink-0">
                                    <svg class="w-5 h-5 text-success" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd"
                                            d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                            clip-rule="evenodd"></path>
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h4 class="text-sm font-medium text-success-strong">Mollie Integration aktiv</h4>
                                    <div class="mt-1">
                                        <p class="text-sm text-success">
                                            {{ mollieStatus.methodCount }} Zahlungsmethoden verfügbar
                                            • {{ mollieStatus.isTestMode ? 'Test-Modus' : 'Live-Modus' }}
                                        </p>
                                    </div>
                                    <div class="mt-2">
                                        <button @click="editMollieConfig"
                                            class="text-sm text-success-strong hover:text-success-strong font-medium">
                                            Konfiguration bearbeiten →
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Current payment methods from Model -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div v-for="method in currentPaymentMethods" :key="method.key"
                                class="border border-line rounded-lg p-4">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 rounded-lg flex items-center justify-center mr-3"
                                            :class="getMethodIconBg(method.icon)">
                                            <component :is="getIconComponent(method.icon)"
                                                :class="getMethodIconColor(method.icon)" class="w-5 h-5" />
                                        </div>
                                        <div>
                                            <h4 class="text-sm font-medium text-ink">{{ method.name }}</h4>
                                            <p class="text-xs text-ink-muted">{{ method.description }}</p>
                                            <p v-if="method.is_overridden" class="text-xs text-warning mt-1">
                                                Durch externe Integration überschrieben
                                            </p>
                                        </div>
                                    </div>
                                    <div class="flex items-center">
                                        <span :class="method.enabled ? 'text-success' : 'text-ink-muted'"
                                            class="text-xs font-medium">
                                            {{ method.enabled ? 'Aktiv' : 'Inaktiv' }}
                                        </span>
                                        <div class="ml-2 w-2 h-2 rounded-full"
                                            :class="method.enabled ? 'bg-success-solid' : 'bg-line-strong'"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Default Payment Methods config -->
                <div class="bg-surface shadow-sm rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg leading-6 font-semibold text-ink mb-4">
                            Standard Zahlungsarten
                        </h3>
                        <p class="text-sm text-ink-secondary mb-6">
                            Grundlegende Zahlungsarten, die standardmäßig zur Verfügung stehen.
                        </p>

                        <div class="space-y-4">
                            <div v-for="method in standardMethods" :key="method.key"
                                class="flex items-center justify-between p-4 border border-line rounded-lg">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 rounded-lg flex items-center justify-center mr-3"
                                        :class="getMethodIconBg(method.icon)">
                                        <component :is="getIconComponent(method.icon)"
                                            :class="getMethodIconColor(method.icon)" class="w-5 h-5" />
                                    </div>
                                    <div>
                                        <h4 class="text-sm font-medium text-ink">{{ method.name }}</h4>
                                        <p class="text-xs text-ink-muted">{{ method.description }}</p>
                                        <div class="flex items-center space-x-3 mt-1">
                                            <span v-if="method.requires_mandate" class="text-xs text-warning">
                                                SEPA-Mandat erforderlich
                                            </span>
                                        </div>
                                        <p v-if="method.is_overridden" class="text-xs text-warning mt-1">
                                            Durch externe Integration überschrieben
                                        </p>
                                    </div>
                                </div>
                                <div class="flex items-center space-x-3">
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox"
                                            role="switch"
                                            :aria-label="`${method.name} anbieten`"
                                            v-model="method.enabled"
                                            :disabled="method.is_overridden"
                                            @change="updateStandardMethod(method)"
                                            class="sr-only peer">
                                        <div class="w-11 h-6 bg-fill-strong peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-brand-line rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-surface after:border-line-strong after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-brand disabled:opacity-50 disabled:cursor-not-allowed"></div>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Execution dates of the payments -->
                <PaymentExecutionSettingsWidget ref="executionSettingsWidget" />

                <!-- External Integrations -->
                <div class="bg-surface shadow-sm rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <div class="flex justify-between items-center mb-4">
                            <div>
                                <h3 class="text-lg leading-6 font-semibold text-ink">
                                    Externe Zahlungsdienstleister
                                </h3>
                                <p class="text-sm text-ink-secondary mt-1">
                                    Integrieren Sie externe Zahlungsdienstleister für erweiterte Funktionen.
                                </p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Mollie Integration -->
                            <div class="border border-line rounded-lg p-6">
                                <div class="flex items-center mb-4">
                                    <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center mr-4">
                                        <svg class="w-6 h-6 text-orange-600" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm0 2.4c5.302 0 9.6 4.298 9.6 9.6s-4.298 9.6-9.6 9.6S2.4 17.302 2.4 12 6.698 2.4 12 2.4z"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <h4 class="text-sm font-medium text-ink">Mollie</h4>
                                        <p class="text-xs text-ink-muted">Europäischer Zahlungsdienstleister</p>
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <div class="flex items-center justify-between text-xs text-ink-secondary mb-2">
                                        <span>Status:</span>
                                        <span :class="mollieStatus.isActive ? 'text-success' : 'text-ink-muted'">
                                            {{ mollieStatus.isActive ? 'Konfiguriert' : 'Nicht konfiguriert' }}
                                        </span>
                                    </div>
                                    <div v-if="mollieStatus.isActive" class="text-xs text-ink-secondary space-y-1">
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
                                        class="w-full bg-brand hover:bg-brand-strong text-white text-sm font-medium py-2 px-4 rounded-md">
                                        Einrichten
                                    </button>
                                    <template v-else>
                                        <button @click="editMollieConfig"
                                            class="w-full bg-fill hover:bg-fill-strong text-ink-secondary text-sm font-medium py-2 px-4 rounded-md mb-2">
                                            Konfiguration bearbeiten
                                        </button>
                                        <button @click="removeMollieConfig"
                                            class="w-full bg-danger-subtle hover:bg-danger-soft text-danger text-sm font-medium py-2 px-4 rounded-md border border-danger-line">
                                            Integration entfernen
                                        </button>
                                    </template>
                                </div>
                            </div>

                            <!-- Placeholder for more Integrations -->
                            <div class="border border-line rounded-lg p-6 bg-surface-muted">
                                <div class="flex items-center mb-4">
                                    <div class="w-12 h-12 bg-fill-strong rounded-lg flex items-center justify-center mr-4">
                                        <component :is="Plus" class="w-6 h-6 text-ink-muted" />
                                    </div>
                                    <div>
                                        <h4 class="text-sm font-medium text-ink-muted">Weitere Integrationen</h4>
                                        <p class="text-xs text-ink-muted">Kommen bald</p>
                                    </div>
                                </div>
                                <p class="text-xs text-ink-muted mb-4">
                                    Weitere Zahlungsdienstleister wie Stripe, PayPal und andere werden in Zukunft verfügbar sein.
                                </p>
                                <button disabled
                                    class="w-full bg-fill-strong text-ink-muted text-sm font-medium py-2 px-4 rounded-md cursor-not-allowed">
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

                <!-- Legal URLs Manager -->
                <LegalUrlsManager
                    :current-gym="currentGym"
                    @success="handleSuccess"
                    @error="handleError" />
            </div>

            <!-- Guest portal -->
            <div v-if="activeTab === 'guests'" class="space-y-6">
                <GuestPortalSettingsWidget
                    @success="handleSuccess"
                    @error="handleError" />
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
            <div class="relative top-20 mx-auto p-5 border border-line-subtle w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-surface" @click.stop>
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
    Wallet, DollarSign, FileText, HandCoins, Mail, Smartphone, FileSignature, Ticket
} from '@lucide/vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import AppShell from '@/Layouts/AppShell.vue'
import TabRail from '@/Components/TabRail.vue'
import LogoUpload from '@/Components/LogoUpload.vue'
import ContractWidget from '@/Components/ContractWidget.vue'
import MollieSetupWizard from '@/Components/MollieSetupWizard.vue'
import EmailTemplatesWidget from '@/Components/EmailTemplatesWidget.vue'
import TeamManagement from '@/Components/TeamManagement.vue'
import IbanInput from '@/Components/IbanInput.vue'
import LegalUrlsManager from '@/Components/LegalUrlsManager.vue'
import PwaSettingsWidget from '@/Components/PwaSettingsWidget.vue'
import GuestPortalSettingsWidget from '@/Components/GuestPortalSettingsWidget.vue'
import ContractSettingsWidget from '@/Components/ContractSettingsWidget.vue'
import PaymentExecutionSettingsWidget from '@/Components/PaymentExecutionSettingsWidget.vue'
import OrganizationSymbolCard from '@/Components/OrganizationSymbolCard.vue'
import { useToast } from '@/composables/useToast'
import { useTabQuery } from '@/composables/useTabQuery'

defineOptions({ layout: AppShell })

const { success, error: toastError } = useToast()

// Props
const props = defineProps({
    user: Object,
    currentGym: Object,
    gymUsers: Array,
    pendingInvitations: {
        type: Array,
        default: () => []
    },
    isGymOwner: {
        type: Boolean,
        default: false
    }
})

// Reactive data
const showMollieSetup = ref(false)
const isSubmittingGym = ref(false)
const executionSettingsWidget = ref(null)

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
    { key: 'payments', label: 'Zahlungen', icon: CreditCard },
    { key: 'emails', label: 'E-Mail-Vorlagen', icon: Mail },
    { key: 'contracts', label: 'Online-Widget', icon: Signature },
    { key: 'contract_settings', label: 'Verträge', icon: FileSignature },
    { key: 'pwa', label: 'App (PWA)', icon: Smartphone },
    { key: 'guests', label: 'Gästeportal', icon: Ticket },
]

const activeTab = useTabQuery(tabs, 'gym')

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
        'Wallet': 'bg-success-soft',
        'HandCoins': 'bg-warning-soft',
        'FileText': 'bg-info-soft',
        'DollarSign': 'bg-purple-100',
        'CreditCard': 'bg-orange-100'
    }
    return iconBgMap[iconName] || 'bg-fill'
}

const getMethodIconColor = (iconName) => {
    const iconColorMap = {
        'Wallet': 'text-success',
        'HandCoins': 'text-warning',
        'FileText': 'text-info',
        'DollarSign': 'text-purple-600',
        'CreditCard': 'text-orange-600'
    }
    return iconColorMap[iconName] || 'text-ink-secondary'
}

const saveGymSettings = async () => {
    isSubmittingGym.value = true

    try {
        const response = await axios.put(route('settings.gym.update', props.currentGym.id), gymForm.value)

        if (response.data.gym) {
            Object.assign(gymForm.value, response.data.gym)
        }

        success('Organisation erfolgreich gespeichert!')
    } catch (error) {
        toastError('Fehler beim Speichern der Organisation')
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
                toastError('Fehler beim Löschen der Organisation')
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
    success(message)
}

const handleError = (message) => {
    toastError(message)
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

        success(`${method.name} ${method.enabled ? 'aktiviert' : 'deaktiviert'}!`)

        // Payment methods refresh
        await loadPaymentMethods()

        // Enabling/disabling a method changes which ones are configurable
        await executionSettingsWidget.value?.loadSettings()
    } catch (error) {
        // Revert change on error
        method.enabled = !method.enabled
        toastError('Fehler beim Aktualisieren der Zahlungsmethode')
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
    success('Mollie Integration erfolgreich eingerichtet!')
    router.reload({
        only: ['currentGym'],
        preserveScroll: true,
        preserveState: false,
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
        success('Mollie Integration erfolgreich entfernt!')
        router.reload({
            only: ['currentGym'],
            preserveScroll: true,
            preserveState: false,
        })
    } catch (error) {
        console.error(error)
        toastError('Fehler beim Entfernen der Mollie Integration')
    }
}

// Load data on mount
onMounted(() => {
    loadPaymentMethods()
})
</script>
