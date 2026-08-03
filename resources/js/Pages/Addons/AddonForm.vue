<template>
  <form class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_360px] gap-5 items-start" @submit.prevent="$emit('submit')">
    <!-- Main column -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 sm:p-7">
      <!-- Name -->
      <div>
        <label for="name" class="block text-sm font-medium text-gray-700 mb-1.5">
          Name des Add-ons <span class="text-red-600">*</span>
        </label>
        <input
          id="name"
          v-model="form.name"
          type="text"
          class="w-full border border-gray-300 rounded-md px-3 py-2.5 text-sm focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600"
          :class="{ 'border-red-500': form.errors.name }"
          placeholder="z.B. Getränke-Flatrate"
          required
        />
        <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
      </div>

      <!-- Description -->
      <div class="mt-4.5">
        <label for="description" class="block text-sm font-medium text-gray-700 mb-1.5">Beschreibung</label>
        <textarea
          id="description"
          v-model="form.description"
          rows="3"
          class="w-full border border-gray-300 rounded-md px-3 py-2.5 text-sm focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600"
          :class="{ 'border-red-500': form.errors.description }"
          placeholder="Optionale Beschreibung, die im Widget angezeigt wird…"
        ></textarea>
        <p v-if="form.errors.description" class="mt-1 text-sm text-red-600">{{ form.errors.description }}</p>
      </div>

      <!-- Service type -->
      <fieldset class="mt-6">
        <legend class="text-sm font-semibold text-gray-900">Leistungstyp</legend>
        <p class="text-sm text-gray-500 mt-1 mb-3">Bestimmt, wie die Leistung genutzt und abgerechnet wird.</p>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <button
            v-for="type in serviceTypes"
            :key="type.value"
            type="button"
            class="text-left border-2 rounded-lg p-4 transition-colors"
            :class="form.service_type === type.value
              ? 'border-indigo-600 bg-violet-50'
              : 'border-gray-200 bg-white hover:border-gray-300'"
            :aria-pressed="form.service_type === type.value"
            @click="form.service_type = type.value"
          >
            <span class="flex items-center gap-2.5">
              <span
                class="w-8.5 h-8.5 rounded-lg flex items-center justify-center shrink-0"
                :class="form.service_type === type.value ? 'bg-indigo-100 text-indigo-600' : 'bg-gray-100 text-gray-500'"
              >
                <component :is="type.icon" class="w-4.5 h-4.5" />
              </span>
              <span class="font-semibold text-gray-900">{{ type.label }}</span>
            </span>
            <span class="block text-sm text-gray-500 mt-2.5">{{ type.description }}</span>
          </button>
        </div>
        <p v-if="form.errors.service_type" class="mt-1 text-sm text-red-600">{{ form.errors.service_type }}</p>
      </fieldset>

      <!-- Billing type -->
      <fieldset class="mt-6">
        <legend class="text-sm font-semibold text-gray-900 mb-3">Abrechnungsart</legend>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <button
            v-for="billing in billingTypes"
            :key="billing.value"
            type="button"
            class="text-left border-2 rounded-lg px-4 py-3.5 transition-colors"
            :class="form.billing_type === billing.value
              ? 'border-indigo-600 bg-violet-50'
              : 'border-gray-200 bg-white hover:border-gray-300'"
            :aria-pressed="form.billing_type === billing.value"
            @click="form.billing_type = billing.value"
          >
            <span class="flex items-center gap-2">
              <span
                class="w-4.5 h-4.5 rounded-full border-2 shrink-0"
                :class="form.billing_type === billing.value
                  ? 'border-indigo-600 ring-3 ring-inset ring-indigo-600 bg-white'
                  : 'border-gray-400'"
              ></span>
              <span class="font-semibold text-gray-900">{{ billing.label }}</span>
            </span>
            <span class="block text-sm text-gray-500 mt-2 ml-6.5">{{ billing.description }}</span>
          </button>
        </div>
        <p v-if="form.errors.billing_type" class="mt-1 text-sm text-red-600">{{ form.errors.billing_type }}</p>

        <!-- Recurring details -->
        <div v-if="isRecurring" class="mt-3.5 bg-indigo-50 border border-indigo-200 rounded-lg p-4">
          <div class="flex gap-2.5">
            <Info class="w-4.5 h-4.5 text-indigo-700 shrink-0 mt-px" />
            <p class="text-sm text-indigo-900 leading-relaxed">
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
                :class="form.trial_rest_of_month ? 'bg-indigo-600' : 'bg-gray-300'"
                @click="form.trial_rest_of_month = !form.trial_rest_of_month"
              >
                <span
                  class="absolute top-0.5 left-0.5 w-4.5 h-4.5 rounded-full bg-white transition-transform"
                  :class="form.trial_rest_of_month ? 'translate-x-4.5' : ''"
                ></span>
              </button>
              <div>
                <div class="text-sm font-semibold text-gray-900">Testphase: Restmonat kostenlos</div>
                <div class="text-sm text-gray-600">
                  Der restliche Monat wird gratis freigeschaltet, ab dem nächsten 01. beginnt die
                  kostenpflichtige Verlängerung.
                </div>
              </div>
            </div>

            <div class="flex items-start gap-2.5">
              <CheckCircle2 class="w-4.5 h-4.5 text-green-700 shrink-0 mt-0.5" />
              <div>
                <div class="text-sm font-semibold text-gray-900">Monatlich kündbar</div>
                <div class="text-sm text-gray-600">
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
          <label for="price" class="block text-sm font-medium text-gray-700 mb-1.5">
            Preis (€) <span class="text-red-600">*</span>
          </label>
          <input
            id="price"
            v-model="form.price"
            type="number"
            step="0.01"
            min="0"
            max="9999.99"
            class="w-full border border-gray-300 rounded-md px-3 py-2.5 text-sm focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600"
            :class="{ 'border-red-500': form.errors.price }"
            placeholder="0.00"
            required
          />
          <p v-if="form.errors.price" class="mt-1 text-sm text-red-600">{{ form.errors.price }}</p>
          <p class="mt-1.5 text-xs text-gray-500">
            {{ isRecurring
              ? 'Monatliche Abrechnung, synchron zum Mitgliedsbeitrag'
              : 'Einmalige Abrechnung zum Vertragsstart' }}
          </p>
        </div>

        <div>
          <label for="payment_method" class="block text-sm font-medium text-gray-700 mb-1.5">Zahlweise</label>
          <select
            id="payment_method"
            v-model="form.payment_method"
            class="w-full border border-gray-300 rounded-md px-3 py-2.5 text-sm bg-white focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600"
            :class="{ 'border-red-500': form.errors.payment_method }"
          >
            <option value="">Standard-Zahlungsart des Mitglieds</option>
            <option v-for="option in paymentMethodOptions" :key="option.key" :value="option.key">
              {{ option.name }}
            </option>
          </select>
          <p v-if="form.errors.payment_method" class="mt-1 text-sm text-red-600">{{ form.errors.payment_method }}</p>
          <p class="mt-1.5 text-xs text-gray-500">Leer = vom Mitglied gewählte Zahlungsart</p>
        </div>
      </div>

      <!-- Usage and quota -->
      <fieldset v-if="isUsageService" class="mt-6 border border-gray-200 rounded-lg p-4.5 bg-gray-50">
        <legend class="flex items-center gap-2 text-sm font-semibold text-gray-900 px-1">
          <Gauge class="w-4 h-4 text-indigo-600" />
          Nutzung &amp; Kontingent
        </legend>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-2">
          <div>
            <label for="usage_period" class="block text-sm font-medium text-gray-700 mb-1.5">Nutzungszeitraum</label>
            <select
              id="usage_period"
              v-model="form.usage_period"
              class="w-full border border-gray-300 rounded-md px-3 py-2.5 text-sm bg-white focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600"
              :class="{ 'border-red-500': form.errors.usage_period }"
            >
              <option v-for="period in usagePeriods" :key="period.value" :value="period.value">
                {{ period.label }}
              </option>
            </select>
            <p v-if="form.errors.usage_period" class="mt-1 text-sm text-red-600">{{ form.errors.usage_period }}</p>
            <p class="mt-1.5 text-xs text-gray-500">{{ usagePeriodHint }}</p>
          </div>

          <div v-if="hasFixedUsagePeriod">
            <label for="usage_duration" class="block text-sm font-medium text-gray-700 mb-1.5">Dauer je Nutzung</label>
            <div class="flex gap-2">
              <input
                id="usage_duration"
                v-model="form.usage_duration"
                type="number"
                min="1"
                max="1000"
                class="w-20 border border-gray-300 rounded-md px-3 py-2.5 text-sm focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600"
                :class="{ 'border-red-500': form.errors.usage_duration }"
              />
              <select
                v-model="form.usage_duration_unit"
                aria-label="Einheit der Dauer"
                class="flex-1 border border-gray-300 rounded-md px-3 py-2.5 text-sm bg-white focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600"
              >
                <option v-for="unit in durationUnits" :key="unit.value" :value="unit.value">{{ unit.label }}</option>
              </select>
            </div>
            <p v-if="form.errors.usage_duration" class="mt-1 text-sm text-red-600">{{ form.errors.usage_duration }}</p>
            <p v-if="form.errors.usage_duration_unit" class="mt-1 text-sm text-red-600">
              {{ form.errors.usage_duration_unit }}
            </p>
          </div>
        </div>

        <!-- Quota -->
        <div class="mt-4">
          <span class="block text-sm font-medium text-gray-700 mb-2">Kontingent</span>
          <div class="flex gap-2.5 flex-wrap">
            <button
              type="button"
              class="border rounded-lg px-4 py-2.5 text-sm font-semibold transition-colors"
              :class="hasUnlimitedQuota
                ? 'border-indigo-600 bg-indigo-50 text-indigo-600'
                : 'border-gray-300 bg-white text-gray-700 hover:border-gray-400'"
              :aria-pressed="hasUnlimitedQuota"
              @click="setUnlimitedQuota"
            >
              Unbegrenzt (Flatrate)
            </button>
            <button
              type="button"
              class="border rounded-lg px-4 py-2.5 text-sm font-semibold transition-colors"
              :class="!hasUnlimitedQuota
                ? 'border-indigo-600 bg-indigo-50 text-indigo-600'
                : 'border-gray-300 bg-white text-gray-700 hover:border-gray-400'"
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
              class="w-24 border border-gray-300 rounded-md px-3 py-2.5 text-sm focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600"
              :class="{ 'border-red-500': form.errors.quota_amount }"
            />
            <span class="text-sm text-gray-700">Einheiten je</span>
            <select
              v-model="form.quota_interval"
              aria-label="Zeitraum des Kontingents"
              class="border border-gray-300 rounded-md px-3 py-2.5 text-sm bg-white focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600"
            >
              <option v-for="interval in quotaIntervals" :key="interval.value" :value="interval.value">
                {{ interval.label }}
              </option>
            </select>
          </div>
          <p v-if="form.errors.quota_amount" class="mt-1 text-sm text-red-600">{{ form.errors.quota_amount }}</p>
          <p v-if="form.errors.quota_interval" class="mt-1 text-sm text-red-600">{{ form.errors.quota_interval }}</p>
        </div>

        <!-- Device settlement -->
        <div class="mt-4 bg-white border border-gray-200 rounded-lg p-4">
          <div class="flex items-start gap-2.5">
            <button
              type="button"
              role="switch"
              :aria-checked="form.settled_via_device"
              class="w-10 h-5.5 rounded-full shrink-0 relative transition-colors mt-0.5"
              :class="form.settled_via_device ? 'bg-indigo-600' : 'bg-gray-300'"
              @click="form.settled_via_device = !form.settled_via_device"
            >
              <span
                class="absolute top-0.5 left-0.5 w-4.5 h-4.5 rounded-full bg-white transition-transform"
                :class="form.settled_via_device ? 'translate-x-4.5' : ''"
              ></span>
            </button>
            <div class="flex-1">
              <div class="text-sm font-semibold text-gray-900">Über Gerät verrechnen</div>
              <div class="text-sm text-gray-600">
                Die Nutzung erfolgt automatisch, zum Beispiel über den Getränkespender. Beim Zapfvorgang wird geprüft,
                ob ein verfügbares Kontingent vorhanden ist, und die Nutzung wird automatisch erfasst.
              </div>
              <Link
                v-if="form.settled_via_device"
                :href="route('access-control.index')"
                class="inline-flex items-center gap-1.5 mt-2 text-sm font-semibold text-indigo-600 hover:text-indigo-800"
              >
                In Zugangskontrolle anlegen
                <ArrowRight class="w-3.5 h-3.5" />
              </Link>
            </div>
          </div>
        </div>
      </fieldset>

      <!-- Active -->
      <label class="flex items-center gap-2.5 mt-6 cursor-pointer">
        <input
          v-model="form.is_active"
          type="checkbox"
          class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500"
        />
        <span class="text-sm text-gray-700">Add-on ist aktiv und kann im Widget angezeigt werden</span>
      </label>

      <!-- Actions -->
      <div class="flex gap-3 mt-6 flex-wrap">
        <button
          type="submit"
          :disabled="form.processing"
          class="bg-indigo-600 hover:bg-indigo-700 disabled:bg-indigo-400 text-white px-5 py-3 rounded-lg text-sm font-semibold transition-colors flex items-center gap-2"
        >
          <Save class="w-4 h-4" />
          <span>{{ form.processing ? 'Speichern…' : submitLabel }}</span>
        </button>

        <Link
          :href="route('contracts.addons.index')"
          class="bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 px-5 py-3 rounded-lg text-sm font-semibold transition-colors flex items-center gap-2"
        >
          <X class="w-4 h-4" />
          <span>Abbrechen</span>
        </Link>
      </div>
    </div>

    <!-- Contract assignment sidebar -->
    <aside class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 lg:sticky lg:top-22">
      <h2 class="text-base font-semibold text-gray-900">Vertragszuordnung</h2>
      <p class="text-sm text-gray-500 mt-1 mb-3.5">
        Lege je Vertrag fest, ob das Add-on <strong>inklusive</strong> (vorausgewählt),
        <strong>optional</strong> (zubuchbar) oder nicht verfügbar ist.
      </p>

      <div v-if="membershipPlans.length === 0" class="text-sm text-gray-500 bg-gray-50 rounded-lg p-3">
        Es sind noch keine Verträge vorhanden, denen das Add-on zugeordnet werden könnte.
      </div>

      <template v-else>
        <div class="relative mb-2.5">
          <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" />
          <input
            v-model="planSearch"
            type="search"
            aria-label="Vertrag suchen"
            placeholder="Vertrag suchen…"
            class="w-full border border-gray-300 rounded-md pl-9 pr-3 py-2.5 text-sm focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600"
          />
        </div>

        <div class="flex gap-1.5 mb-3 flex-wrap">
          <button
            v-for="option in planStatusFilters"
            :key="option.value"
            type="button"
            class="border rounded-full px-3.5 py-1.5 text-sm font-semibold transition-colors"
            :class="planStatusFilter === option.value
              ? 'border-indigo-600 bg-indigo-50 text-indigo-600'
              : 'border-gray-300 bg-white text-gray-700 hover:border-gray-400'"
            :aria-pressed="planStatusFilter === option.value"
            @click="planStatusFilter = option.value"
          >
            {{ option.label }}
          </button>
        </div>

        <p class="text-xs text-gray-400 mb-2.5">
          {{ filteredPlans.length }} von {{ membershipPlans.length }} Verträgen
        </p>

        <div class="flex flex-col gap-2.5 max-h-110 overflow-y-auto">
          <div
            v-for="plan in filteredPlans"
            :key="plan.id"
            class="border border-gray-200 rounded-lg p-3"
          >
            <div class="flex items-center justify-between gap-2">
              <span class="font-semibold text-sm text-gray-900">{{ plan.name }}</span>
              <span
                class="text-xs font-semibold px-2 py-0.5 rounded-full shrink-0"
                :class="plan.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
              >
                {{ plan.is_active ? 'Aktiv' : 'Inaktiv' }}
              </span>
            </div>
            <select
              v-model="form.plan_modes[plan.id]"
              :aria-label="`Zuordnung für ${plan.name}`"
              class="w-full mt-2 border border-gray-300 rounded-md px-2.5 py-2 text-sm bg-white focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600"
            >
              <option :value="null">Nicht zugeordnet</option>
              <option value="optional">Optional</option>
              <option value="included">Inklusive</option>
            </select>
          </div>

          <p v-if="filteredPlans.length === 0" class="text-center py-6 text-sm text-gray-400">
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
  ArrowRight, BadgeCheck, CheckCircle2, CupSoda, Gauge, Info, Save, Search, X
} from 'lucide-vue-next'

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

// The trial only applies to recurring billing.
watch(isRecurring, (recurring) => {
  if (!recurring) {
    props.form.trial_rest_of_month = false
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
