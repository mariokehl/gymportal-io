<template>
  <AppLayout title="Vertrag bearbeiten">
    <template #header>
      Vertrag bearbeiten
    </template>

    <!-- Breadcrumb -->
    <nav class="mb-6 text-sm">
      <Link :href="route('contracts.index')" class="text-brand hover:text-brand-strong">
        Verträge
      </Link>
      <span class="text-ink-muted mx-2">/</span>
      <span class="text-ink">{{ membershipPlan.name }}</span>
      <span class="text-ink-muted mx-2">/</span>
      <span class="text-ink">Bearbeiten</span>
    </nav>

    <!-- Warning for active members -->
    <div v-if="activeMembersCount > 0" class="mb-6 bg-warning-subtle border border-warning-line rounded-lg p-4">
      <div class="flex items-start space-x-3">
        <AlertTriangle class="w-5 h-5 text-warning mt-0.5" />
        <div class="flex-1">
          <h3 class="text-sm font-medium text-warning-strong">Achtung: Aktive Mitgliedschaften</h3>
          <p class="text-sm text-warning mt-1">
            Dieser Vertrag wird aktuell von <strong>{{ activeMembersCount }}</strong>
            {{ activeMembersCount === 1 ? 'Mitglied' : 'Mitgliedern' }} genutzt.
            Änderungen können sich auf bestehende Mitgliedschaften auswirken.
          </p>
          <div v-if="activeMemberships.length > 0" class="mt-3">
            <p class="text-xs text-warning font-medium mb-2">Betroffene Mitglieder:</p>
            <div class="flex flex-wrap gap-2">
              <span
                v-for="membership in activeMemberships"
                :key="membership.id"
                class="bg-warning-soft text-warning-strong px-2 py-1 rounded text-xs"
              >
                {{ membership.member.first_name }} {{ membership.member.last_name }}
              </span>
              <span v-if="activeMembersCount > activeMemberships.length" class="text-xs text-warning">
                +{{ activeMembersCount - activeMemberships.length }} weitere
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Tabs (nur wenn die Organisation mehrere Standorte hat) -->
    <TabRail v-if="showLocationsTab" v-model="activeTab" :tabs="tabs" class="mb-6" />

    <form
      v-show="!showLocationsTab || activeTab === 'general'"
      class="flex flex-col xl:flex-row gap-6 items-start"
      @submit.prevent="submit"
    >
      <!-- Contract form -->
      <div class="w-full xl:flex-1 xl:min-w-0 max-w-3xl bg-surface rounded-lg shadow-sm border border-line p-6">
        <!-- Name -->
        <div class="mb-6">
          <label for="name" class="block text-sm font-medium text-ink-secondary mb-2">
            Name des Vertrags <span class="text-danger">*</span>
          </label>
          <input
            id="name"
            v-model="form.name"
            type="text"
            class="field w-full px-3 py-2"
            :class="{ 'border-danger': errors.name }"
            placeholder="z.B. Standard Mitgliedschaft"
            required
          />
          <p v-if="errors.name" class="mt-1 text-sm text-danger">{{ errors.name }}</p>
        </div>

        <!-- Description -->
        <div class="mb-6">
          <label for="description" class="block text-sm font-medium text-ink-secondary mb-2">
            Beschreibung
          </label>
          <textarea
            id="description"
            v-model="form.description"
            rows="3"
            class="field w-full px-3 py-2"
            :class="{ 'border-danger': errors.description }"
            placeholder="Optionale Beschreibung des Vertrags..."
          ></textarea>
          <p v-if="errors.description" class="mt-1 text-sm text-danger">{{ errors.description }}</p>
        </div>

        <!-- Trial Period Info for Free Trial Plans -->
        <div v-if="membershipPlan.is_free_trial_plan" class="mb-6 bg-purple-50 border border-purple-200 rounded-lg p-4">
          <p class="text-sm text-purple-800">
            <strong>Gratis-Mitgliedschaft</strong>
          </p>
          <p class="text-xs text-purple-600 mt-1">
            Bei Gratis-Testzeiträumen sind Preis, Aktivierungsgebühr, Abrechnungszyklus, Mindestlaufzeit und Kündigungsfrist nicht relevant.
          </p>
        </div>

        <!-- Price, Original Price (UVP), Setup Fee and Billing Cycle - Hidden for free trial plans -->
        <div v-if="!membershipPlan.is_free_trial_plan" class="mb-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
          <div>
            <label for="price" class="block text-sm font-medium text-ink-secondary mb-2">
              Preis (€) <span class="text-danger">*</span>
            </label>
            <input
              id="price"
              v-model="form.price"
              type="number"
              step="0.01"
              min="0"
              max="9999.99"
              class="field w-full px-3 py-2"
              :class="{ 'border-danger': errors.price }"
              placeholder="0.00"
              required
            />
            <p v-if="errors.price" class="mt-1 text-sm text-danger">{{ errors.price }}</p>
            <p v-if="activeMembersCount > 0" class="mt-1 text-xs text-warning">
              Preisänderungen betreffen bestehende Mitgliedschaften bei der nächsten Abrechnung
            </p>
          </div>

          <div>
            <label for="original_price" class="block text-sm font-medium text-ink-secondary mb-2">
              UVP (€)
            </label>
            <input
              id="original_price"
              v-model="form.original_price"
              type="number"
              step="0.01"
              min="0"
              max="9999.99"
              class="field w-full px-3 py-2"
              :class="{ 'border-danger': errors.original_price }"
              placeholder="0.00"
            />
            <p v-if="errors.original_price" class="mt-1 text-sm text-danger">{{ errors.original_price }}</p>
            <p class="mt-1 text-xs text-ink-muted">Optionaler Originalpreis; wird im Widget durchgestrichen angezeigt</p>
          </div>

          <div>
            <label for="setup_fee" class="block text-sm font-medium text-ink-secondary mb-2">
              Aktivierungsgebühr (€)
            </label>
            <input
              id="setup_fee"
              v-model="form.setup_fee"
              type="number"
              step="0.01"
              min="0"
              max="999.99"
              class="field w-full px-3 py-2"
              :class="{ 'border-danger': errors.setup_fee }"
              placeholder="0.00"
            />
            <p v-if="errors.setup_fee" class="mt-1 text-sm text-danger">{{ errors.setup_fee }}</p>
            <p class="mt-1 text-xs text-ink-muted">Einmalige Gebühr bei Vertragsabschluss</p>
            <p v-if="activeMembersCount > 0" class="mt-1 text-xs text-warning">
              Aktivierungsgebühr gilt nur für neue Mitgliedschaften
            </p>
          </div>

          <div>
            <label for="billing_cycle" class="block text-sm font-medium text-ink-secondary mb-2">
              Abrechnungszyklus <span class="text-danger">*</span>
            </label>
            <select
              id="billing_cycle"
              v-model="form.billing_cycle"
              class="field w-full px-3 py-2"
              :class="{ 'border-danger': errors.billing_cycle }"
              required
            >
              <option value="">Bitte wählen</option>
              <option value="monthly">Monatlich</option>
              <option value="quarterly">Vierteljährlich</option>
              <option value="biannual">Halbjährlich</option>
              <option value="yearly">Jährlich</option>
            </select>
            <p v-if="errors.billing_cycle" class="mt-1 text-sm text-danger">{{ errors.billing_cycle }}</p>
          </div>
        </div>

        <!-- Commitment and Cancellation - Hidden for free trial plans -->
        <div v-if="!membershipPlan.is_free_trial_plan" class="mb-6 grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label for="commitment_months" class="block text-sm font-medium text-ink-secondary mb-2">
              Mindestlaufzeit (Monate)
            </label>
            <input
              id="commitment_months"
              v-model="form.commitment_months"
              type="number"
              min="0"
              max="36"
              class="field w-full px-3 py-2"
              :class="{ 'border-danger': errors.commitment_months }"
              placeholder="0 = keine Mindestlaufzeit"
            />
            <p v-if="errors.commitment_months" class="mt-1 text-sm text-danger">{{ errors.commitment_months }}</p>
            <p class="mt-1 text-xs text-ink-muted">Leer lassen für keine Mindestlaufzeit</p>
          </div>

          <div>
            <label for="cancellation_period" class="block text-sm font-medium text-ink-secondary mb-2">
              Kündigungsfrist <span class="text-danger">*</span>
            </label>
            <div class="flex gap-2">
              <input
                id="cancellation_period"
                v-model="form.cancellation_period"
                type="number"
                min="0"
                :max="form.cancellation_period_unit === 'months' ? 24 : 365"
                class="field flex-1 px-3 py-2"
                :class="{ 'border-danger': errors.cancellation_period }"
                placeholder="30"
                required
              />
              <select
                id="cancellation_period_unit"
                v-model="form.cancellation_period_unit"
                class="field w-28 px-3 py-2"
                :class="{ 'border-danger': errors.cancellation_period_unit }"
              >
                <option value="days">Tage</option>
                <option value="months">Monate</option>
              </select>
            </div>
            <p v-if="errors.cancellation_period" class="mt-1 text-sm text-danger">{{ errors.cancellation_period }}</p>
            <p v-if="errors.cancellation_period_unit" class="mt-1 text-sm text-danger">{{ errors.cancellation_period_unit }}</p>
          </div>
        </div>

        <!-- Auto Renewal Type (nach Erstlaufzeit) - Hidden for free trial plans -->
        <div v-if="!membershipPlan.is_free_trial_plan" class="mb-6">
          <label class="block text-sm font-medium text-ink-secondary mb-2">
            Verlängerung nach Erstlaufzeit
          </label>
          <div class="space-y-2">
            <label class="flex items-start space-x-3 cursor-pointer">
              <input
                v-model="form.auto_renew_type"
                type="radio"
                value="indefinite"
                class="mt-1 w-4 h-4 text-brand border-line-strong"
              />
              <div>
                <span class="text-sm font-medium text-ink-secondary">Unbefristet</span>
                <p class="text-xs text-ink-muted">Vertrag geht in unbefristete Mitgliedschaft über</p>
              </div>
            </label>
            <label class="flex items-start space-x-3 cursor-pointer">
              <input
                v-model="form.auto_renew_type"
                type="radio"
                value="monthly"
                class="mt-1 w-4 h-4 text-brand border-line-strong"
              />
              <div>
                <span class="text-sm font-medium text-ink-secondary">Monatlich rollierend</span>
                <p class="text-xs text-ink-muted">Vertrag verlängert sich monatlich um jeweils 1 Monat</p>
              </div>
            </label>
            <label class="flex items-start space-x-3 cursor-pointer">
              <input
                v-model="form.auto_renew_type"
                type="radio"
                value="fixed"
                class="mt-1 w-4 h-4 text-brand border-line-strong"
              />
              <div>
                <span class="text-sm font-medium text-ink-secondary">Feste Verlängerung (Altverträge)</span>
                <p class="text-xs text-ink-muted">Nur für Verträge mit Beginn vor dem 01.03.2022. Neuere Verträge mit diesem Tarif verlängern sich monatlich. Der Tarif kann nicht online abgeschlossen werden.</p>
              </div>
            </label>
            <div v-if="form.auto_renew_type === 'fixed'" class="ml-7 flex items-center gap-2">
              <input
                v-model.number="form.renewal_months"
                type="number"
                min="2"
                max="24"
                class="field w-24 px-3 py-2"
                :class="{ 'border-danger': errors.renewal_months }"
              />
              <span class="text-sm text-ink-secondary">Monate je Verlängerung</span>
            </div>
            <p v-if="errors.renewal_months" class="ml-7 text-sm text-danger">{{ errors.renewal_months }}</p>
          </div>
        </div>

        <!-- Vertragsstart - Hidden for free trial plans -->
        <div v-if="!membershipPlan.is_free_trial_plan" class="mb-6">
          <label class="block text-sm font-medium text-ink-secondary mb-2">
            Vertragsstart
          </label>
          <div class="space-y-2">
            <label class="flex items-start space-x-3 cursor-pointer">
              <input
                v-model="form.start_date_mode"
                type="radio"
                value="next_possible"
                class="mt-1 w-4 h-4 text-brand border-line-strong"
              />
              <div>
                <span class="text-sm font-medium text-ink-secondary">Nächstmöglich</span>
                <p class="text-xs text-ink-muted">Die Mitgliedschaft startet zum Registrierungszeitpunkt</p>
              </div>
            </label>
            <label class="flex items-start space-x-3 cursor-pointer">
              <input
                v-model="form.start_date_mode"
                type="radio"
                value="fixed"
                class="mt-1 w-4 h-4 text-brand border-line-strong"
              />
              <div>
                <span class="text-sm font-medium text-ink-secondary">Fester Starttermin</span>
                <p class="text-xs text-ink-muted">
                  Bis zu diesem Datum startet jede neue Mitgliedschaft am angegebenen Tag (ohne vorgeschalteten Gratis-Zeitraum).
                  Ab diesem Datum gilt wieder „Nächstmöglich“.
                </p>
              </div>
            </label>
          </div>
          <div v-if="form.start_date_mode === 'fixed'" class="mt-3">
            <label for="fixed_start_date" class="block text-sm font-medium text-ink-secondary mb-2">
              Startdatum <span class="text-danger">*</span>
            </label>
            <input
              id="fixed_start_date"
              v-model="form.fixed_start_date"
              type="date"
              class="field w-full px-3 py-2"
              :class="{ 'border-danger': errors.fixed_start_date }"
            />
            <p v-if="errors.fixed_start_date" class="mt-1 text-sm text-danger">{{ errors.fixed_start_date }}</p>
          </div>
        </div>

        <!-- Active Status - Hidden for free trial plans -->
        <div v-if="!membershipPlan.is_free_trial_plan" class="mb-8">
          <label for="is_active" class="flex items-start space-x-3 cursor-pointer">
            <input
              id="is_active"
              v-model="form.is_active"
              type="checkbox"
              class="mt-0.5 w-4 h-4 text-brand border-line-strong rounded"
            />
            <span class="text-sm font-medium text-ink-secondary">
              Vertrag ist aktiv und kann von Mitgliedern gewählt werden
            </span>
          </label>
          <p class="mt-1 text-xs text-ink-muted">
            Inaktive Verträge sind für neue Mitgliedschaften nicht verfügbar
          </p>
          <p v-if="activeMembersCount > 0" class="mt-1 text-xs text-warning">
            Das Deaktivieren verhindert neue Mitgliedschaften, bestehende bleiben aktiv
          </p>
        </div>

        <!-- Form Actions -->
        <div class="flex space-x-4">
          <button
            type="submit"
            :disabled="processing"
            class="bg-brand hover:bg-brand-strong disabled:bg-brand text-white px-6 py-2 rounded-lg font-medium transition-colors flex items-center space-x-2"
          >
            <Save class="w-4 h-4" />
            <span>{{ processing ? 'Speichern...' : 'Änderungen speichern' }}</span>
          </button>

          <Link
            :href="route('contracts.show', membershipPlan.id)"
            class="bg-fill hover:bg-fill-strong text-ink-secondary px-6 py-2 rounded-lg font-medium transition-colors flex items-center space-x-2"
          >
            <Eye class="w-4 h-4" />
            <span>Ansehen</span>
          </Link>

          <Link
            :href="route('contracts.index')"
            class="bg-fill hover:bg-fill-strong text-ink-secondary px-6 py-2 rounded-lg font-medium transition-colors flex items-center space-x-2"
          >
            <X class="w-4 h-4" />
            <span>Abbrechen</span>
          </Link>
      </div>
    </div>

      <!-- Discounts and add-ons, alongside the form on wide screens.
           A free trial plan is neither priced nor extendable, so both drop out. -->
      <aside
        v-if="!membershipPlan.is_free_trial_plan"
        class="w-full xl:w-[452px] xl:flex-none flex flex-col gap-4 xl:sticky xl:top-6"
      >
        <DiscountPhasesSection
          v-model="form.discount_phases"
          v-model:enabled="form.discounts_enabled"
          :price="form.price"
          :original-price="form.original_price"
          :commitment-months="form.commitment_months"
          :billing-cycle="form.billing_cycle"
          :errors="errors"
        />

        <AddonAssignmentSection v-model="form.addon_modes" :addons="addons" />
      </aside>
    </form>

    <!-- Standorte -->
    <div v-if="showLocationsTab" v-show="activeTab === 'locations'">
      <ContractLocations
        :plan-id="membershipPlan.id"
        :plan-name="membershipPlan.name"
        :active-members-count="activeMembersCount"
        :location-scope="locationScope"
        @success="success"
        @error="toastError"
      />
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import AppShell from '@/Layouts/AppShell.vue'
import TabRail from '@/Components/TabRail.vue'
import ContractLocations from '@/Components/MembershipPlans/ContractLocations.vue'
import AddonAssignmentSection from '@/Components/MembershipPlans/AddonAssignmentSection.vue'
import { Save, X, Eye, AlertTriangle, MapPin, FileText } from '@lucide/vue'
import { useToast } from '@/composables/useToast'
import DiscountPhasesSection from '@/Components/MembershipPlans/DiscountPhasesSection.vue'

defineOptions({ layout: AppShell })

// Props
const props = defineProps({
  membershipPlan: Object,
  addons: { type: Array, default: () => [] },
  addonModes: { type: Object, default: () => ({}) },
  activeMembersCount: Number,
  activeMemberships: Array,
  locationScope: {
    type: Object,
    default: () => ({
      scope: 'own',
      allowed_gym_ids: [],
      locations: [],
      has_siblings: false,
      location_rule: 'own',
      gym_name: '',
      effect: []
    })
  }
})

const { success, error: toastError } = useToast()

// A single-location organisation has nothing to restrict, so the tab stays hidden.
const showLocationsTab = computed(() => props.locationScope.has_siblings)

const activeTab = ref('general')

const tabs = [
  { key: 'general', label: 'Allgemein', icon: FileText },
  { key: 'locations', label: 'Standorte', icon: MapPin }
]

// Form data
const form = useForm({
  name: props.membershipPlan.name,
  description: props.membershipPlan.description || '',
  price: props.membershipPlan.price,
  original_price: props.membershipPlan.original_price || '',
  setup_fee: props.membershipPlan.setup_fee || '',
  billing_cycle: props.membershipPlan.billing_cycle,
  is_active: props.membershipPlan.is_active,
  commitment_months: props.membershipPlan.commitment_months || '',
  cancellation_period: props.membershipPlan.cancellation_period,
  cancellation_period_unit: props.membershipPlan.cancellation_period_unit || 'days',
  auto_renew_type: props.membershipPlan.auto_renew_type || 'indefinite',
  renewal_months: props.membershipPlan.renewal_months || 12,
  start_date_mode: props.membershipPlan.start_date_mode || 'next_possible',
  fixed_start_date: props.membershipPlan.fixed_start_date
    ? String(props.membershipPlan.fixed_start_date).slice(0, 10)
    : '',
  discounts_enabled: props.membershipPlan.discounts_enabled ?? false,
  discount_phases: (props.membershipPlan.discount_phases || []).map((phase) => ({
    duration_months: phase.duration_months,
    price: phase.price,
    original_price: phase.original_price ?? ''
  })),
  addon_modes: { ...props.addonModes }
})

// Computed
const { errors, processing } = form

// Methods
const submit = () => {
  form.put(route('contracts.update', props.membershipPlan.id))
}
</script>
