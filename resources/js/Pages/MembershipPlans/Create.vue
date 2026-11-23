<template>
  <AppLayout title="Neuer Vertrag">
    <template #header>
      Neuen Vertrag erstellen
    </template>

    <!-- Breadcrumb -->
    <nav class="mb-6 text-sm">
      <Link :href="route('contracts.index')" class="text-brand-ink underline decoration-brand-line underline-offset-4 hover:text-brand-ink-strong hover:decoration-current">
        Verträge
      </Link>
      <span class="text-ink-muted mx-2">/</span>
      <span class="text-ink">Neuer Vertrag</span>
    </nav>

    <form class="flex flex-col xl:flex-row gap-6 items-start" @submit.prevent="submit">
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

        <!-- Price, Original Price (UVP), Setup Fee and Billing Cycle -->
        <div class="mb-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
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

        <!-- Commitment and Cancellation -->
        <div class="mb-6 grid grid-cols-1 md:grid-cols-2 gap-4">
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
                aria-label="Einheit der Kündigungsfrist"
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

        <!-- Auto Renewal Type (nach Erstlaufzeit) -->
        <div class="mb-6">
          <label class="block text-sm font-medium text-ink-secondary mb-2">
            Verlängerung nach Erstlaufzeit
          </label>
          <div class="space-y-2">
            <label class="flex items-start space-x-3 cursor-pointer">
              <input
                v-model="form.auto_renew_type"
                type="radio"
                value="indefinite"
                class="mt-1 w-4 h-4 text-brand-ink border-line-strong"
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
                class="mt-1 w-4 h-4 text-brand-ink border-line-strong"
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
                class="mt-1 w-4 h-4 text-brand-ink border-line-strong"
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

        <!-- Vertragsstart -->
        <div class="mb-6">
          <label class="block text-sm font-medium text-ink-secondary mb-2">
            Vertragsstart
          </label>
          <div class="space-y-2">
            <label class="flex items-start space-x-3 cursor-pointer">
              <input
                v-model="form.start_date_mode"
                type="radio"
                value="next_possible"
                class="mt-1 w-4 h-4 text-brand-ink border-line-strong"
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
                class="mt-1 w-4 h-4 text-brand-ink border-line-strong"
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

        <!-- Active Status -->
        <div class="mb-8">
          <label for="is_active" class="flex items-start space-x-3 cursor-pointer">
            <input
              id="is_active"
              v-model="form.is_active"
              type="checkbox"
              class="mt-0.5 w-4 h-4 text-brand-ink border-line-strong rounded"
            />
            <span class="text-sm font-medium text-ink-secondary">
              Vertrag ist aktiv und kann von Mitgliedern gewählt werden
            </span>
          </label>
          <p class="mt-1 text-xs text-ink-muted">
            Inaktive Verträge sind für neue Mitgliedschaften nicht verfügbar
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
            <span>{{ processing ? 'Speichern...' : 'Vertrag erstellen' }}</span>
          </button>

          <Link
            :href="route('contracts.index')"
            class="bg-fill hover:bg-fill-strong text-ink-secondary px-6 py-2 rounded-lg font-medium transition-colors flex items-center space-x-2"
          >
            <X class="w-4 h-4" />
            <span>Abbrechen</span>
          </Link>
      </div>
    </div>

      <!-- Discounts and add-ons, alongside the form on wide screens -->
      <aside class="w-full xl:w-[452px] xl:flex-none flex flex-col gap-4 xl:sticky xl:top-6">
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
  </AppLayout>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import AppShell from '@/Layouts/AppShell.vue'
import AddonAssignmentSection from '@/Components/MembershipPlans/AddonAssignmentSection.vue'
import DiscountPhasesSection from '@/Components/MembershipPlans/DiscountPhasesSection.vue'
import { Save, X } from '@lucide/vue'

defineOptions({ layout: AppShell })

const props = defineProps({
  addons: { type: Array, default: () => [] },
})

// Form data
const form = useForm({
  name: '',
  description: '',
  price: '',
  original_price: '',
  setup_fee: '',
  billing_cycle: '',
  is_active: true,
  commitment_months: '',
  cancellation_period: 30,
  cancellation_period_unit: 'days',
  auto_renew_type: 'indefinite',
  renewal_months: 12,
  start_date_mode: 'next_possible',
  fixed_start_date: '',
  discounts_enabled: false,
  discount_phases: [],
  addon_modes: {}
})

// Computed
const { errors, processing } = form

// Methods
const submit = () => {
  form.post(route('contracts.store'))
}
</script>
