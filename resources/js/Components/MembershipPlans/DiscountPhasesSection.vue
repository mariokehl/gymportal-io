<template>
  <div class="bg-white rounded-lg shadow-sm border border-gray-200">
    <!-- Header with enable toggle -->
    <div class="p-5 border-b border-gray-100 flex items-start gap-3">
      <div class="w-10 h-10 flex-none rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center">
        <Tags class="w-5 h-5" />
      </div>
      <div class="flex-1 min-w-0">
        <h3 class="text-base font-semibold text-gray-900">Rabatte</h3>
        <p class="text-xs text-gray-500 leading-relaxed mt-0.5">
          Aktionspreise überschreiben Preis &amp; UVP für die ersten Monate. Danach gilt der reguläre Preis.
        </p>
      </div>
      <button
        type="button"
        role="switch"
        :aria-checked="enabled && supportsBillingCycle"
        :disabled="!supportsBillingCycle"
        aria-label="Rabatte aktivieren"
        class="w-10 h-6 flex-none rounded-full p-0.5 transition-colors mt-0.5 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 disabled:cursor-not-allowed disabled:opacity-50"
        :class="enabled && supportsBillingCycle ? 'bg-indigo-600' : 'bg-gray-300'"
        @click="toggleEnabled"
      >
        <span
          class="block w-5 h-5 rounded-full bg-white shadow transition-transform"
          :class="enabled && supportsBillingCycle ? 'translate-x-4' : 'translate-x-0'"
        />
      </button>
    </div>

    <div class="p-5">
      <!-- Billing cycle does not support phases -->
      <div v-if="!supportsBillingCycle" class="flex gap-2.5 px-3 py-2.5 rounded-lg bg-amber-50 border border-amber-200">
        <AlertTriangle class="w-4 h-4 flex-none text-amber-600 mt-px" />
        <p class="text-xs text-amber-700 leading-relaxed">
          Rabattphasen laufen in Monaten und lassen sich nur für den Abrechnungszyklus
          „Monatlich“ hinterlegen. Stellen Sie den Zyklus um, um Aktionspreise zu nutzen.
        </p>
      </div>

      <!-- Disabled state -->
      <div v-else-if="!enabled" class="text-center py-6 px-2 text-gray-400">
        <p class="text-sm leading-relaxed">
          Rabatte sind für diesen Vertrag deaktiviert.<br />
          Aktivieren Sie den Schalter oben, um Aktionspreise zu hinterlegen.
        </p>
      </div>

      <template v-else>
        <!-- Empty state with templates -->
        <template v-if="phases.length === 0">
          <div class="border border-dashed border-gray-300 rounded-lg py-6 px-4 text-center mb-4">
            <div class="w-10 h-10 mx-auto mb-2.5 rounded-full bg-gray-100 text-gray-400 flex items-center justify-center">
              <Tag class="w-5 h-5" />
            </div>
            <p class="text-sm font-semibold text-gray-900">Noch keine Rabattphasen</p>
            <p class="text-xs text-gray-500 mt-0.5 leading-relaxed">
              Legen Sie Aktionspreise für die ersten Monate der Laufzeit fest.
            </p>
          </div>

          <p class="text-xs font-medium uppercase tracking-wide text-gray-500 mb-2">Vorlagen</p>
          <div class="flex flex-col gap-2 mb-4">
            <button
              v-for="template in templates"
              :key="template.key"
              type="button"
              class="text-left text-sm px-3 py-2.5 border border-gray-200 rounded-lg bg-white text-gray-900 hover:border-indigo-300 hover:bg-indigo-50 transition-colors flex justify-between items-center gap-3"
              @click="applyTemplate(template)"
            >
              <span class="font-semibold">{{ template.label }}</span>
              <span class="text-gray-500 text-xs text-right">{{ template.summary }}</span>
            </button>
          </div>
        </template>

        <!-- Phase list -->
        <div v-else class="flex flex-col gap-3">
          <div
            v-for="(phase, index) in phases"
            :key="index"
            class="border border-gray-200 rounded-lg bg-gray-50 p-3.5"
          >
            <div class="flex items-center justify-between mb-3">
              <div class="flex items-center gap-2">
                <span class="text-sm font-semibold text-gray-900">{{ index + 1 }}. Phase</span>
                <span
                  v-if="rangeLabel(index)"
                  class="text-xs font-medium text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded-full"
                >
                  {{ rangeLabel(index) }}
                </span>
              </div>
              <button
                type="button"
                title="Phase entfernen"
                :aria-label="`Phase ${index + 1} entfernen`"
                class="text-gray-400 hover:text-red-600 transition-colors p-0.5 rounded"
                @click="removePhase(index)"
              >
                <Trash2 class="w-4 h-4" />
              </button>
            </div>

            <div class="grid grid-cols-3 gap-3">
              <div>
                <label :for="`phase_duration_${index}`" class="block text-xs font-medium text-gray-500 mb-1">
                  Dauer (Mon.)
                </label>
                <input
                  :id="`phase_duration_${index}`"
                  v-model="phase.duration_months"
                  type="number"
                  min="1"
                  max="120"
                  step="1"
                  class="w-full text-sm border border-gray-300 rounded-md px-2.5 py-2 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600"
                  :class="{ 'border-red-500': phaseError(index, 'duration_months') }"
                  placeholder="3"
                  @input="emitChange"
                />
              </div>
              <div>
                <label :for="`phase_price_${index}`" class="block text-xs font-medium text-gray-500 mb-1">
                  Preis (€)
                </label>
                <input
                  :id="`phase_price_${index}`"
                  v-model="phase.price"
                  type="number"
                  min="0"
                  max="9999.99"
                  step="0.01"
                  class="w-full text-sm border border-gray-300 rounded-md px-2.5 py-2 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600"
                  :class="{ 'border-red-500': phaseError(index, 'price') }"
                  placeholder="0.00"
                  @input="emitChange"
                />
              </div>
              <div>
                <label :for="`phase_uvp_${index}`" class="block text-xs font-medium text-gray-500 mb-1">
                  UVP (€)
                </label>
                <input
                  :id="`phase_uvp_${index}`"
                  v-model="phase.original_price"
                  type="number"
                  min="0"
                  max="9999.99"
                  step="0.01"
                  class="w-full text-sm border border-gray-300 rounded-md px-2.5 py-2 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600"
                  :class="{ 'border-red-500': phaseError(index, 'original_price') }"
                  :placeholder="originalPrice || '0.00'"
                  @input="emitChange"
                />
              </div>
            </div>

            <p v-if="phaseError(index)" class="mt-2 text-xs text-red-600">
              {{ phaseError(index) }}
            </p>
            <p
              v-else-if="phaseSavings(phase) > 0"
              class="mt-2 text-xs text-green-700 flex items-center gap-1.5"
            >
              <Check class="w-3.5 h-3.5" />
              {{ formatCents(phaseSavings(phase)) }} günstiger pro Monat
            </p>
          </div>
        </div>

        <!-- Add phase -->
        <button
          type="button"
          class="w-full text-sm font-medium py-2.5 border border-dashed border-indigo-300 rounded-lg bg-indigo-50 text-indigo-600 hover:bg-indigo-100 transition-colors flex items-center justify-center gap-1.5"
          :class="phases.length > 0 ? 'mt-3' : ''"
          @click="addPhase"
        >
          <Plus class="w-4 h-4" />
          {{ phases.length === 0 ? 'Rabattphase hinzufügen' : 'Weitere Rabattphase' }}
        </button>

        <!-- Warning: phases outrun the minimum commitment -->
        <div
          v-if="projection.exceedsTerm"
          class="mt-3.5 flex gap-2.5 px-3 py-2.5 rounded-lg bg-amber-50 border border-amber-200"
        >
          <AlertTriangle class="w-4 h-4 flex-none text-amber-600 mt-px" />
          <p class="text-xs text-amber-700 leading-relaxed">
            Die Rabattphasen umfassen {{ projection.discountedMonths }} Monate und überschreiten damit die
            Mindestlaufzeit von {{ commitmentMonths }} Monaten. Mitglieder könnten kündigen, bevor der
            reguläre Preis greift.
          </p>
        </div>

        <!-- Preisverlauf -->
        <div v-if="phases.length > 0" class="mt-5 pt-4 border-t border-gray-100">
          <p class="text-xs font-medium uppercase tracking-wide text-gray-500 mb-2.5">Preisverlauf</p>

          <div class="flex gap-0.5 h-13 rounded-md overflow-hidden" style="height: 52px">
            <div
              v-for="(segment, index) in projection.segments"
              :key="index"
              class="flex flex-col items-center justify-center overflow-hidden px-0.5"
              :class="segment.isDiscounted ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700'"
              :style="{ width: segmentWidth(segment) }"
            >
              <span class="text-xs font-bold whitespace-nowrap">{{ formatCents(segment.priceCents) }}</span>
              <span class="text-[9px] opacity-85 whitespace-nowrap mt-px">
                {{ segmentRangeLabel(segment) }}
              </span>
            </div>
          </div>

          <dl class="mt-4 bg-gray-50 rounded-lg px-4 py-3.5">
            <div class="flex justify-between text-sm mb-2">
              <dt class="text-gray-600">Betrachtete Laufzeit</dt>
              <dd class="text-gray-900 font-medium">{{ projection.termMonths }} Monate</dd>
            </div>
            <div class="flex justify-between text-sm mb-2">
              <dt class="text-gray-600">Regulär (ohne Rabatt)</dt>
              <dd class="text-gray-900 font-medium">{{ formatCents(projection.regularTotalCents) }}</dd>
            </div>
            <div class="flex justify-between text-sm mb-2.5">
              <dt class="text-gray-600">Mit Rabatt</dt>
              <dd class="text-gray-900 font-medium">{{ formatCents(projection.discountedTotalCents) }}</dd>
            </div>
            <div class="flex justify-between text-sm pt-2.5 border-t border-gray-200">
              <dt class="text-gray-900 font-semibold">Ersparnis für Mitglied</dt>
              <dd class="text-green-700 font-bold">{{ formatCents(projection.savingsCents) }}</dd>
            </div>
          </dl>

          <p class="mt-2.5 text-xs text-gray-500 leading-relaxed">
            Vorschau auf Basis der Mindestlaufzeit. Aktivierungsgebühren sind nicht enthalten.
          </p>
        </div>
      </template>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { AlertTriangle, Check, Plus, Tag, Tags, Trash2 } from 'lucide-vue-next'
import { formatCents, projectDiscounts, toCents } from '@/utils/discountProjection'

const props = defineProps({
  modelValue: { type: Array, default: () => [] },
  enabled: { type: Boolean, default: false },
  price: { type: [String, Number], default: '' },
  originalPrice: { type: [String, Number], default: '' },
  commitmentMonths: { type: [String, Number], default: '' },
  billingCycle: { type: String, default: '' },
  errors: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['update:modelValue', 'update:enabled'])

const phases = computed(() => props.modelValue)

// Phases are expressed in months and only line up with a monthly cycle.
const supportsBillingCycle = computed(() => props.billingCycle === 'monthly')

const templates = [
  {
    key: 'two_phase',
    label: 'Zwei Aktionsphasen',
    summary: '3 Mon. 19,95 € · 9 Mon. 34,95 €',
    phases: [
      { duration_months: 3, price: '19.95', original_price: '' },
      { duration_months: 9, price: '34.95', original_price: '' },
    ],
  },
  {
    key: 'one_euro',
    label: '1 € Startaktion',
    summary: '1. Monat für 1,00 €',
    phases: [{ duration_months: 1, price: '1.00', original_price: '' }],
  },
  {
    key: 'three_free',
    label: '3 Monate gratis',
    summary: '3 Mon. für 0,00 €',
    phases: [{ duration_months: 3, price: '0.00', original_price: '' }],
  },
]

const projection = computed(() =>
  projectDiscounts({
    price: props.price,
    commitmentMonths: props.commitmentMonths,
    phases: phases.value,
  })
)

const toggleEnabled = () => {
  if (!supportsBillingCycle.value) {
    return
  }

  emit('update:enabled', !props.enabled)
}

const emitChange = () => {
  emit('update:modelValue', [...phases.value])
}

const addPhase = () => {
  emit('update:modelValue', [
    ...phases.value,
    { duration_months: '', price: '', original_price: '' },
  ])
}

const removePhase = (index) => {
  emit(
    'update:modelValue',
    phases.value.filter((_, i) => i !== index)
  )
}

const applyTemplate = (template) => {
  emit('update:modelValue', template.phases.map((phase) => ({ ...phase })))
}

/**
 * Month range a phase covers, accumulated from the phases before it.
 */
const rangeLabel = (index) => {
  let start = 1

  for (let i = 0; i < index; i += 1) {
    start += parseInt(phases.value[i].duration_months, 10) || 0
  }

  const duration = parseInt(phases.value[index].duration_months, 10) || 0

  if (duration <= 0) {
    return ''
  }

  const end = start + duration - 1

  return start === end ? `Monat ${start}` : `Monat ${start}–${end}`
}

const segmentRangeLabel = (segment) =>
  segment.startMonth === segment.endMonth
    ? `Monat ${segment.startMonth}`
    : `Monat ${segment.startMonth}–${segment.endMonth}`

const segmentWidth = (segment) =>
  `${(segment.durationMonths / projection.value.termMonths) * 100}%`

/**
 * Per-month saving of a phase against the regular plan price.
 */
const phaseSavings = (phase) => Math.max(0, toCents(props.price) - toCents(phase.price))

/**
 * Server-side validation error for a phase row, keyed like `discount_phases.0.price`.
 */
const phaseError = (index, field = null) => {
  if (field) {
    return props.errors[`discount_phases.${index}.${field}`]
  }

  return (
    props.errors[`discount_phases.${index}.duration_months`] ||
    props.errors[`discount_phases.${index}.price`] ||
    props.errors[`discount_phases.${index}.original_price`]
  )
}
</script>
