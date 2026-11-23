<template>
  <form class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_360px] gap-5 items-start" @submit.prevent="$emit('submit')">
    <!-- Main column -->
    <div class="bg-surface rounded-xl shadow-sm border border-line p-5 sm:p-7">
      <!-- Name -->
      <div>
        <label for="name" class="block text-sm font-medium text-ink-secondary mb-1.5">
          Name des Add-ons <span class="text-danger">*</span>
        </label>
        <input
          id="name"
          v-model="form.name"
          type="text"
          class="field w-full px-3 py-2.5"
          :class="{ 'border-danger': form.errors.name }"
          placeholder="z.B. Getränke-Flatrate"
          required
        />
        <p v-if="form.errors.name" class="mt-1 text-sm text-danger">{{ form.errors.name }}</p>
      </div>

      <!-- Description -->
      <div class="mt-4.5">
        <label for="description" class="block text-sm font-medium text-ink-secondary mb-1.5">Beschreibung</label>
        <textarea
          id="description"
          v-model="form.description"
          rows="3"
          class="field w-full px-3 py-2.5"
          :class="{ 'border-danger': form.errors.description }"
          placeholder="Optionale Beschreibung, die im Widget angezeigt wird…"
        ></textarea>
        <p v-if="form.errors.description" class="mt-1 text-sm text-danger">{{ form.errors.description }}</p>
      </div>

      <!-- Service type -->
      <fieldset class="mt-6">
        <legend class="text-sm font-semibold text-ink">Leistungstyp</legend>
        <p class="text-sm text-ink-muted mt-1 mb-3">Bestimmt, wie die Leistung genutzt und abgerechnet wird.</p>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <button
            v-for="type in serviceTypes"
            :key="type.value"
            type="button"
            class="text-left border-2 rounded-lg p-4 transition-colors"
            :class="form.service_type === type.value
              ? 'border-brand bg-violet-50'
              : 'border-line bg-surface hover:border-line-strong'"
            :aria-pressed="form.service_type === type.value"
            @click="form.service_type = type.value"
          >
            <span class="flex items-center gap-2.5">
              <span
                class="w-8.5 h-8.5 rounded-lg flex items-center justify-center shrink-0"
                :class="form.service_type === type.value ? 'bg-brand-soft text-brand-ink' : 'bg-fill text-ink-muted'"
              >
                <component :is="type.icon" class="w-4.5 h-4.5" />
              </span>
              <span class="font-semibold text-ink">{{ type.label }}</span>
            </span>
            <span class="block text-sm text-ink-muted mt-2.5">{{ type.description }}</span>
          </button>
        </div>
        <p v-if="form.errors.service_type" class="mt-1 text-sm text-danger">{{ form.errors.service_type }}</p>
      </fieldset>

      <!-- Billing type -->
      <fieldset class="mt-6">
        <legend class="text-sm font-semibold text-ink mb-3">Abrechnungsart</legend>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <button
            v-for="billing in billingTypes"
            :key="billing.value"
            type="button"
            class="text-left border-2 rounded-lg px-4 py-3.5 transition-colors"
            :class="form.billing_type === billing.value
              ? 'border-brand bg-violet-50'
              : 'border-line bg-surface hover:border-line-strong'"
            :aria-pressed="form.billing_type === billing.value"
            @click="form.billing_type = billing.value"
          >
            <span class="flex items-center gap-2">
              <span
                class="w-4.5 h-4.5 rounded-full border-2 shrink-0"
                :class="form.billing_type === billing.value
                  ? 'border-brand ring-3 ring-inset ring-focus bg-surface'
                  : 'border-line-strong'"
              ></span>
              <span class="font-semibold text-ink">{{ billing.label }}</span>
            </span>
            <span class="block text-sm text-ink-muted mt-2 ml-6.5">{{ billing.description }}</span>
          </button>
        </div>
        <p v-if="form.errors.billing_type" class="mt-1 text-sm text-danger">{{ form.errors.billing_type }}</p>

        <!-- Recurring details -->
        <div v-if="isRecurring" class="mt-3.5 bg-brand-subtle border border-brand-line rounded-lg p-4">
          <div class="flex gap-2.5">
            <Info class="w-4.5 h-4.5 text-brand-ink-strong shrink-0 mt-px" />
            <p class="text-sm text-brand-ink-strong leading-relaxed">
              <strong>Synchron zum Mitgliedsbeitrag.</strong> Die Leistung wird gemeinsam mit dem Beitrag zum
              selben Fälligkeitsdatum abgerechnet – so entstehen keine abweichenden Zeiträume oder
              Nachberechnungen bei Kündigung.
            </p>
          </div>

          <div class="flex flex-col gap-3 mt-3.5">
            <div class="flex items-start gap-2.5">
              <button
                type="button"
                role="switch"
                :aria-checked="form.trial_rest_of_month"
                class="w-10 h-5.5 rounded-full shrink-0 relative transition-colors mt-0.5"
                :class="form.trial_rest_of_month ? 'bg-brand' : 'bg-line-strong'"
                @click="form.trial_rest_of_month = !form.trial_rest_of_month"
              >
                <span
                  class="absolute top-0.5 left-0.5 w-4.5 h-4.5 rounded-full bg-surface transition-transform"
                  :class="form.trial_rest_of_month ? 'translate-x-4.5' : ''"
                ></span>
              </button>
              <div>
                <div class="text-sm font-semibold text-ink">Testphase: Restmonat kostenlos</div>
                <div class="text-sm text-ink-secondary">
                  Der restliche Monat wird gratis freigeschaltet, ab dem nächsten 01. beginnt die
                  kostenpflichtige Verlängerung.
                </div>
              </div>
            </div>

            <div class="flex items-start gap-2.5">
              <CheckCircle2 class="w-4.5 h-4.5 text-success shrink-0 mt-0.5" />
              <div>
                <div class="text-sm font-semibold text-ink">Monatlich kündbar</div>
                <div class="text-sm text-ink-secondary">
                  Mitglieder können das Modul jederzeit zum Monatsende kündigen.
                </div>
              </div>
            </div>
          </div>
        </div>
      </fieldset>

      <!-- Price and payment method -->
      <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label for="price" class="block text-sm font-medium text-ink-secondary mb-1.5">
            Preis (€) <span class="text-danger">*</span>
          </label>
          <input
            id="price"
            v-model="form.price"
            type="number"
            step="0.01"
            min="0"
            max="9999.99"
            class="field w-full px-3 py-2.5"
            :class="{ 'border-danger': form.errors.price }"
            placeholder="0.00"
            required
          />
          <p v-if="form.errors.price" class="mt-1 text-sm text-danger">{{ form.errors.price }}</p>
          <p class="mt-1.5 text-xs text-ink-muted">
            {{ isRecurring
              ? 'Monatliche Abrechnung, synchron zum Mitgliedsbeitrag'
              : 'Einmalige Abrechnung zum Vertragsstart' }}
          </p>
        </div>

        <div>
          <label for="payment_method" class="block text-sm font-medium text-ink-secondary mb-1.5">Zahlweise</label>
          <select
            id="payment_method"
            v-model="form.payment_method"
            class="field w-full px-3 py-2.5"
            :class="{ 'border-danger': form.errors.payment_method }"
          >
            <option value="">Standard-Zahlungsart des Mitglieds</option>
            <option v-for="option in paymentMethodOptions" :key="option.key" :value="option.key">
              {{ option.name }}
            </option>
          </select>
          <p v-if="form.errors.payment_method" class="mt-1 text-sm text-danger">{{ form.errors.payment_method }}</p>
          <p class="mt-1.5 text-xs text-ink-muted">Leer = vom Mitglied gewählte Zahlungsart</p>
        </div>
      </div>

      <!-- Price display in the widget (recurring add-ons only) -->
      <section
        v-if="isRecurring"
        aria-labelledby="price-display-heading"
        class="mt-5.5 border border-line rounded-lg p-4.5 bg-surface-muted"
      >
        <h3 id="price-display-heading" class="flex items-center gap-2 text-sm font-semibold text-ink">
          <Tag class="w-4 h-4 text-brand-ink" />
          Preisdarstellung im Widget
        </h3>

        <p class="text-sm text-ink-muted mt-1.5 mb-3">
          Betrifft nur die Anzeige bei der Auswahl. Abgerechnet wird immer monatlich, synchron zum
          Beitrag – im Checkout und auf der Rechnung erscheint stets der Monatspreis.
        </p>

        <div class="flex gap-2 flex-wrap">
          <button
            v-for="display in priceDisplays"
            :key="display.value"
            type="button"
            class="border rounded-lg px-4 py-2.5 text-sm font-semibold transition-colors"
            :class="form.price_display === display.value
              ? 'border-brand bg-brand-subtle text-brand-ink'
              : 'border-line-strong bg-surface text-ink-secondary hover:border-line-strong'"
            :aria-pressed="form.price_display === display.value"
            @click="form.price_display = display.value"
          >
            {{ display.label }}
          </button>
        </div>
        <p v-if="form.errors.price_display" class="mt-1 text-sm text-danger">{{ form.errors.price_display }}</p>

        <div class="flex gap-2 mt-2.5 text-xs text-ink-muted leading-relaxed">
          <Calculator class="w-3.5 h-3.5 text-ink-muted shrink-0 mt-0.5" />
          <span>
            Der Wochenpreis wird automatisch aus dem Monatspreis berechnet (× 12 ÷ 52 – ein Monat hat
            4,35 Wochen) und ist nicht frei eingebbar. Er gilt als Vergleichsangabe; der Monatsbetrag
            bleibt der Gesamtpreis und wird gleich sichtbar ausgewiesen.
          </span>
        </div>

        <!-- Live preview of the widget price line -->
        <div
          class="mt-3.5 bg-surface border border-line rounded-lg px-3.5 py-3 flex items-center justify-between gap-3 flex-wrap"
        >
          <span class="text-xs font-bold uppercase tracking-wider text-ink-muted">Vorschau Widget</span>
          <span class="text-right">
            <span class="block text-base font-bold text-ink">{{ pricePreview.main }}</span>
            <span class="block text-sm text-ink-secondary">{{ pricePreview.sub }}</span>
          </span>
        </div>
      </section>

      <!-- Usage and quota -->
      <section
        v-if="isUsageService"
        aria-labelledby="usage-quota-heading"
        class="mt-6 border border-line rounded-lg p-4.5 bg-surface-muted"
      >
        <h3 id="usage-quota-heading" class="flex items-center gap-2 text-sm font-semibold text-ink mb-3.5">
          <Gauge class="w-4 h-4 text-brand-ink" />
          Nutzung &amp; Kontingent
        </h3>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label for="usage_period" class="block text-sm font-medium text-ink-secondary mb-1.5">Nutzungszeitraum</label>
            <select
              id="usage_period"
              v-model="form.usage_period"
              class="field w-full px-3 py-2.5"
              :class="{ 'border-danger': form.errors.usage_period }"
            >
              <option v-for="period in usagePeriods" :key="period.value" :value="period.value">
                {{ period.label }}
              </option>
            </select>
            <p v-if="form.errors.usage_period" class="mt-1 text-sm text-danger">{{ form.errors.usage_period }}</p>
            <p class="mt-1.5 text-xs text-ink-muted">{{ usagePeriodHint }}</p>
          </div>

          <div v-if="hasFixedUsagePeriod">
            <label for="usage_duration" class="block text-sm font-medium text-ink-secondary mb-1.5">Dauer je Nutzung</label>
            <div class="flex gap-2">
              <input
                id="usage_duration"
                v-model="form.usage_duration"
                type="number"
                min="1"
                max="1000"
                class="field w-20 px-3 py-2.5"
                :class="{ 'border-danger': form.errors.usage_duration }"
              />
              <select
                v-model="form.usage_duration_unit"
                aria-label="Einheit der Dauer"
                class="field flex-1 px-3 py-2.5"
              >
                <option v-for="unit in durationUnits" :key="unit.value" :value="unit.value">{{ unit.label }}</option>
              </select>
            </div>
            <p v-if="form.errors.usage_duration" class="mt-1 text-sm text-danger">{{ form.errors.usage_duration }}</p>
            <p v-if="form.errors.usage_duration_unit" class="mt-1 text-sm text-danger">
              {{ form.errors.usage_duration_unit }}
            </p>
          </div>
        </div>

        <!-- Quota -->
        <div class="mt-4">
          <span class="block text-sm font-medium text-ink-secondary mb-2">Kontingent</span>
          <div class="flex gap-2.5 flex-wrap">
            <button
              type="button"
              class="border rounded-lg px-4 py-2.5 text-sm font-semibold transition-colors"
              :class="hasUnlimitedQuota
                ? 'border-brand bg-brand-subtle text-brand-ink'
                : 'border-line-strong bg-surface text-ink-secondary hover:border-line-strong'"
              :aria-pressed="hasUnlimitedQuota"
              @click="setUnlimitedQuota"
            >
              Unbegrenzt (Flatrate)
            </button>
            <button
              type="button"
              class="border rounded-lg px-4 py-2.5 text-sm font-semibold transition-colors"
              :class="!hasUnlimitedQuota
                ? 'border-brand bg-brand-subtle text-brand-ink'
                : 'border-line-strong bg-surface text-ink-secondary hover:border-line-strong'"
              :aria-pressed="!hasUnlimitedQuota"
              @click="setLimitedQuota"
            >
              Begrenztes Kontingent
            </button>
          </div>

          <div v-if="!hasUnlimitedQuota" class="flex items-center gap-2 mt-3 flex-wrap">
            <input
              v-model="form.quota_amount"
              type="number"
              min="1"
              max="100000"
              aria-label="Anzahl der Einheiten"
              class="field w-24 px-3 py-2.5"
              :class="{ 'border-danger': form.errors.quota_amount }"
            />
            <span class="text-sm text-ink-secondary">Einheiten je</span>
            <select
              v-model="form.quota_interval"
              aria-label="Zeitraum des Kontingents"
              class="field px-3 py-2.5"
            >
              <option v-for="interval in quotaIntervals" :key="interval.value" :value="interval.value">
                {{ interval.label }}
              </option>
            </select>
          </div>
          <p v-if="form.errors.quota_amount" class="mt-1 text-sm text-danger">{{ form.errors.quota_amount }}</p>
          <p v-if="form.errors.quota_interval" class="mt-1 text-sm text-danger">{{ form.errors.quota_interval }}</p>
        </div>

        <!-- Device settlement -->
        <div class="mt-4 bg-surface border border-line rounded-lg p-4">
          <div class="flex items-start gap-2.5">
            <button
              type="button"
              role="switch"
              :aria-checked="form.settled_via_device"
              class="w-10 h-5.5 rounded-full shrink-0 relative transition-colors mt-0.5"
              :class="form.settled_via_device ? 'bg-brand' : 'bg-line-strong'"
              @click="form.settled_via_device = !form.settled_via_device"
            >
              <span
                class="absolute top-0.5 left-0.5 w-4.5 h-4.5 rounded-full bg-surface transition-transform"
                :class="form.settled_via_device ? 'translate-x-4.5' : ''"
              ></span>
            </button>
            <div class="flex-1">
              <div class="text-sm font-semibold text-ink">Über Gerät verrechnen</div>
              <div class="text-sm text-ink-secondary">
                Die Nutzung erfolgt automatisch, zum Beispiel über den Getränkespender. Beim Zapfvorgang wird geprüft,
                ob ein verfügbares Kontingent vorhanden ist, und die Nutzung wird automatisch erfasst.
              </div>
              <Link
                v-if="form.settled_via_device"
                :href="route('access-control.index')"
                class="inline-flex items-center gap-1.5 mt-2 text-sm font-semibold text-brand-ink hover:text-brand-ink-strong"
              >
                In Zugangskontrolle anlegen
                <ArrowRight class="w-3.5 h-3.5" />
              </Link>
            </div>
          </div>
        </div>
      </section>

      <!-- Active -->
      <label class="flex items-center gap-2.5 mt-6 cursor-pointer">
        <input
          v-model="form.is_active"
          type="checkbox"
          class="w-4 h-4 text-brand-ink border-line-strong rounded"
        />
        <span class="text-sm text-ink-secondary">Add-on ist aktiv und kann im Widget angezeigt werden</span>
      </label>

      <!-- Actions -->
      <div class="flex gap-3 mt-6 flex-wrap">
        <button
          type="submit"
          :disabled="form.processing"
          class="bg-brand hover:bg-brand-strong disabled:bg-brand text-white px-5 py-3 rounded-lg text-sm font-semibold transition-colors flex items-center gap-2"
        >
          <Save class="w-4 h-4" />
          <span>{{ form.processing ? 'Speichern…' : submitLabel }}</span>
        </button>

        <Link
          :href="route('contracts.addons.index')"
          class="bg-surface border border-line-strong hover:bg-surface-muted text-ink-secondary px-5 py-3 rounded-lg text-sm font-semibold transition-colors flex items-center gap-2"
        >
          <X class="w-4 h-4" />
          <span>Abbrechen</span>
        </Link>
      </div>
    </div>

    <!-- Contract assignment sidebar -->
    <aside class="bg-surface rounded-xl shadow-sm border border-line p-5 lg:sticky lg:top-22">
      <h2 class="text-base font-semibold text-ink">Vertragszuordnung</h2>
      <p class="text-sm text-ink-muted mt-1 mb-3.5">
        Lege je Vertrag fest, ob das Add-on <strong>inklusive</strong> (vorausgewählt),
        <strong>optional</strong> (zubuchbar) oder nicht verfügbar ist.
      </p>

      <div v-if="membershipPlans.length === 0" class="text-sm text-ink-muted bg-surface-muted rounded-lg p-3">
        Es sind noch keine Verträge vorhanden, denen das Add-on zugeordnet werden könnte.
      </div>

      <template v-else>
        <div class="relative mb-2.5">
          <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-ink-muted pointer-events-none" />
          <input
            v-model="planSearch"
            type="search"
            aria-label="Vertrag suchen"
            placeholder="Vertrag suchen…"
            class="field w-full pl-9 pr-3 py-2.5"
          />
        </div>

        <div class="flex gap-1.5 mb-3 flex-wrap">
          <button
            v-for="option in planStatusFilters"
            :key="option.value"
            type="button"
            class="border rounded-full px-3.5 py-1.5 text-sm font-semibold transition-colors"
            :class="planStatusFilter === option.value
              ? 'border-brand bg-brand-subtle text-brand-ink'
              : 'border-line-strong bg-surface text-ink-secondary hover:border-line-strong'"
            :aria-pressed="planStatusFilter === option.value"
            @click="planStatusFilter = option.value"
          >
            {{ option.label }}
          </button>
        </div>

        <p class="text-xs text-ink-muted mb-2.5">
          {{ filteredPlans.length }} von {{ membershipPlans.length }} Verträgen
        </p>

        <div class="flex flex-col gap-2.5 max-h-110 overflow-y-auto">
          <div
            v-for="plan in filteredPlans"
            :key="plan.id"
            class="border border-line rounded-lg p-3"
          >
            <div class="flex items-center justify-between gap-2">
              <span class="font-semibold text-sm text-ink">{{ plan.name }}</span>
              <span
                class="text-xs font-semibold px-2 py-0.5 rounded-full shrink-0"
                :class="plan.is_active ? 'bg-success-soft text-success' : 'bg-fill text-ink-muted'"
              >
                {{ plan.is_active ? 'Aktiv' : 'Inaktiv' }}
              </span>
            </div>
            <select
              v-model="form.plan_modes[plan.id]"
              :aria-label="`Zuordnung für ${plan.name}`"
              class="field w-full mt-2 px-2.5 py-2"
            >
              <option :value="null">Nicht zugeordnet</option>
              <option value="optional">Optional</option>
              <option value="included">Inklusive</option>
            </select>
          </div>

          <p v-if="filteredPlans.length === 0" class="text-center py-6 text-sm text-ink-muted">
            Keine Verträge gefunden.
          </p>
        </div>
      </template>
    </aside>
  </form>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { Link } from '@inertiajs/vue3'
import {
  ArrowRight, BadgeCheck, Calculator, CheckCircle2, CupSoda, Gauge, Info, Save, Search, Tag, X
} from '@lucide/vue'
import { weeklyPriceOf } from '@/utils/addons'

const props = defineProps({
  form: { type: Object, required: true },
  membershipPlans: { type: Array, default: () => [] },
  paymentMethodOptions: { type: Array, default: () => [] },
  submitLabel: { type: String, default: 'Speichern' }
})

defineEmits(['submit'])

const serviceTypes = [
  {
    value: 'additional',
    label: 'Zusatzleistung',
    icon: BadgeCheck,
    description: 'Einmalige Leistung wie Trainereinweisung. Wird i.d.R. einmalig abgerechnet.'
  },
  {
    value: 'usage',
    label: 'Nutzungsleistung',
    icon: CupSoda,
    description: 'Getränke, Sauna & Co. mit Kontingent, verrechnet über ein Gerät.'
  }
]

const billingTypes = [
  { value: 'one_time', label: 'Einmalig', description: 'Einmalige Abrechnung zum Vertragsstart.' },
  {
    value: 'recurring',
    label: 'Wiederkehrend',
    description: 'Monatlich, synchron zum Mitgliedsbeitrag. Monatlich kündbar.'
  }
]

const usagePeriods = [
  { value: 'single', label: 'Einmalige Nutzung', hint: 'Wird pro Nutzung einmal verbraucht.' },
  { value: 'fixed_period', label: 'Fester Zeitraum', hint: 'Nutzung über einen definierten Zeitraum (z.B. 2 Stunden).' },
  { value: 'full_day', label: 'Ganzer Tag', hint: 'Ganztägige Nutzung – ideal für Getränke-Flatrate.' }
]

const durationUnits = [
  { value: 'hours', label: 'Stunden' },
  { value: 'days', label: 'Tage' },
  { value: 'weeks', label: 'Wochen' }
]

const priceDisplays = [
  { value: 'monthly', label: 'Monatspreis' },
  { value: 'weekly', label: 'Wochenpreis' }
]

const quotaIntervals = [
  { value: 'month', label: 'Monat' },
  { value: 'week', label: 'Woche' },
  { value: 'day', label: 'Tag' }
]

const planStatusFilters = [
  { value: 'active', label: 'Aktiv' },
  { value: 'all', label: 'Alle' },
  { value: 'inactive', label: 'Inaktiv' }
]

const isUsageService = computed(() => props.form.service_type === 'usage')
const isRecurring = computed(() => props.form.billing_type === 'recurring')
const hasFixedUsagePeriod = computed(() => props.form.usage_period === 'fixed_period')
const hasUnlimitedQuota = computed(() => props.form.quota_amount === null || props.form.quota_amount === '')

const usagePeriodHint = computed(
  () => usagePeriods.find((period) => period.value === props.form.usage_period)?.hint ?? ''
)

const formatEuro = (value) => `${value.toFixed(2).replace('.', ',')} €`

// Mirrors the widget's price line so the admin sees the effect of the choice.
const pricePreview = computed(() => {
  const price = Number.parseFloat(props.form.price) || 0

  if (props.form.price_display === 'weekly') {
    return {
      main: `${formatEuro(weeklyPriceOf(price))} / Woche`,
      sub: `${formatEuro(price)} · Abrechnung monatlich`
    }
  }

  return {
    main: `${formatEuro(price)} / Monat`,
    sub: 'Abrechnung monatlich'
  }
})

const setUnlimitedQuota = () => {
  props.form.quota_amount = null
  props.form.quota_interval = null
}

const setLimitedQuota = () => {
  if (hasUnlimitedQuota.value) {
    props.form.quota_amount = 10
    props.form.quota_interval = 'month'
  }
}

// Switching to a usage service needs a usage period; switching away clears the
// usage-only fields so no stale values are submitted.
watch(isUsageService, (usage) => {
  if (usage) {
    props.form.usage_period = props.form.usage_period ?? 'full_day'
    return
  }

  props.form.usage_period = null
  props.form.usage_duration = null
  props.form.usage_duration_unit = null
  props.form.quota_amount = null
  props.form.quota_interval = null
  props.form.settled_via_device = false
})

// A fixed usage period needs a duration; other periods must not keep one.
watch(hasFixedUsagePeriod, (fixed) => {
  if (fixed) {
    props.form.usage_duration = props.form.usage_duration ?? 2
    props.form.usage_duration_unit = props.form.usage_duration_unit ?? 'hours'
    return
  }

  props.form.usage_duration = null
  props.form.usage_duration_unit = null
})

// The trial and the weekly price display only apply to recurring billing.
watch(isRecurring, (recurring) => {
  if (!recurring) {
    props.form.trial_rest_of_month = false
    props.form.price_display = 'monthly'
  }
})

const planSearch = ref('')
const planStatusFilter = ref('active')

const filteredPlans = computed(() => {
  const query = planSearch.value.trim().toLowerCase()

  return props.membershipPlans.filter((plan) => {
    const matchesStatus = planStatusFilter.value === 'all'
      || (planStatusFilter.value === 'active' ? plan.is_active : !plan.is_active)

    return matchesStatus && (query === '' || plan.name.toLowerCase().includes(query))
  })
})
</script>
