<template>
  <teleport to="body">
    <div
      class="fixed inset-0 bg-gray-500/75 flex items-center justify-center z-50 p-6 overflow-y-auto"
      @click.self="$emit('close')"
    >
      <div class="bg-white rounded-lg shadow-lg w-full max-w-3xl max-h-[90vh] flex flex-col">
        <div class="px-6 pt-6 pb-4 border-b border-gray-200">
          <h2 class="text-lg font-semibold text-gray-900">Akte aktualisieren · Eine neue Zahlung buchen</h2>
          <p class="text-sm text-gray-500 mt-1">
            Aktenzeichen {{ collectionCase.partner_reference || collectionCase.case_number }}
          </p>
        </div>

        <div class="p-6 overflow-y-auto space-y-5">
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1.5">Zahlungsbetrag *</label>
              <input v-model="amount" type="number" step="0.01" min="0" class="input-field" >
              <p class="text-xs text-gray-400 mt-1.5">Betrag laut Meldung des Partners</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1.5">Buchungsdatum *</label>
              <input v-model="bookedAt" type="date" class="input-field" >
            </div>
          </div>

          <div class="space-y-2.5">
            <div class="text-sm font-medium text-gray-700">Verteilungsmethode</div>
            <RadioCard
              :selected="mode === 'auto'"
              title="Automatische Verteilung"
              description="Verteilung von der ältesten zur neuesten Forderung"
              @select="mode = 'auto'"
            />
            <RadioCard
              :selected="mode === 'manual'"
              title="Manuelle Verteilung"
              description="Beträge je Forderung selbst festlegen"
              @select="switchToManual"
            />
          </div>

          <!-- Allocation table -->
          <div class="border border-gray-200 rounded-lg overflow-hidden">
            <div class="grid grid-cols-[1fr_110px_120px] gap-3 px-3.5 py-2.5 bg-gray-50 text-xs font-medium uppercase tracking-wider text-gray-500">
              <span>Forderung</span>
              <span class="text-right">Offen</span>
              <span class="text-right">Zahlung</span>
            </div>
            <div
              v-for="claim in openClaims"
              :key="claim.id"
              class="grid grid-cols-[1fr_110px_120px] gap-3 px-3.5 py-3 border-t border-gray-100 items-center"
            >
              <div>
                <div class="text-sm text-gray-900">{{ claim.description }}</div>
                <div class="text-xs text-gray-400">fällig {{ formatDate(claim.due_date) }}</div>
              </div>
              <div class="text-right text-sm text-gray-500">{{ formatCurrency(claim.open_amount) }}</div>
              <div class="text-right">
                <span
                  v-if="mode === 'auto'"
                  :class="autoAllocation[claim.id] ? 'text-green-700' : 'text-gray-300'"
                  class="text-sm font-semibold"
                >
                  {{ formatCurrency(autoAllocation[claim.id] || 0) }}
                </span>
                <input
                  v-else
                  v-model="manualAllocation[claim.id]"
                  type="number"
                  step="0.01"
                  min="0"
                  placeholder="0,00"
                  class="w-full px-2.5 py-1.5 border border-gray-300 rounded-md text-sm text-right"
                >
              </div>
            </div>
            <div
              :class="matches ? 'bg-green-100 text-green-800' : 'bg-gray-50 text-gray-700'"
              class="grid grid-cols-[1fr_110px_120px] gap-3 px-3.5 py-3 border-t border-gray-200 text-sm font-semibold"
            >
              <span>{{ matches ? 'Verteilung stimmt mit dem Zahlungsbetrag überein' : 'Summe der Verteilung' }}</span>
              <span />
              <span class="text-right">{{ formatCurrency(allocationSum) }}</span>
            </div>
          </div>

          <div class="flex gap-2.5 items-center cursor-pointer" @click="closeCase = !closeCase">
            <span
              :class="closeCase ? 'bg-indigo-600 border-indigo-600 text-white' : 'bg-white border-gray-300'"
              class="w-[18px] h-[18px] rounded border flex items-center justify-center flex-none"
            >
              <Check v-if="closeCase" class="w-3 h-3" />
            </span>
            <span class="text-sm text-gray-700">
              Inkassofall mit dieser Zahlung schließen (setzt die Mahnstufe zurück)
            </span>
          </div>

          <p v-if="form.errors.amount" class="text-sm text-red-600">{{ form.errors.amount }}</p>
        </div>

        <div class="px-6 py-4 border-t border-gray-200 bg-gray-50 flex justify-end gap-2.5 items-center">
          <span class="mr-auto text-xs" :class="matches ? 'text-green-700' : 'text-gray-400'">
            <template v-if="targetAmount > 0 && !matches">
              Differenz: {{ formatCurrency(targetAmount - allocationSum) }}
            </template>
            <template v-else-if="targetAmount <= 0">Zahlungsbetrag erfassen</template>
          </span>
          <button type="button" class="btn-secondary" @click="$emit('close')">Abbrechen</button>
          <button
            type="button"
            :disabled="!matches || form.processing"
            class="btn-primary"
            @click="submit"
          >
            {{ form.processing ? 'Bucht …' : 'Zahlung buchen' }}
          </button>
        </div>
      </div>
    </div>
  </teleport>
</template>

<script setup>
import { computed, reactive, ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { Check } from 'lucide-vue-next'
import RadioCard from '@/Components/Inkasso/RadioCard.vue'
import { formatCurrency, formatDate, todayInDisplayTimezone } from '@/utils/formatters'

const props = defineProps({
  collectionCase: { type: Object, required: true },
})

const emit = defineEmits(['close', 'done'])

const EPSILON = 0.005

const amount = ref('')
const bookedAt = ref(todayInDisplayTimezone())
const mode = ref('auto')
const closeCase = ref(false)
const manualAllocation = reactive({})

const form = useForm({
  amount: 0,
  booked_at: '',
  allocation_mode: 'auto',
  allocation: {},
  close_case: false,
})

const round = value => Math.round(value * 100) / 100

// Claims that still carry an open amount, oldest first.
const openClaims = computed(() =>
  [...(props.collectionCase.claims ?? [])]
    .filter(claim => !claim.written_off && Number(claim.open_amount) > EPSILON)
    .sort((a, b) => String(a.due_date ?? '').localeCompare(String(b.due_date ?? '')))
)

const targetAmount = computed(() => round(Number(amount.value) || 0))

// Mirrors the server side allocator: fill the oldest claim first.
const autoAllocation = computed(() => {
  let remaining = targetAmount.value
  const result = {}

  for (const claim of openClaims.value) {
    if (remaining <= EPSILON) break

    const take = round(Math.min(remaining, Number(claim.open_amount)))

    if (take > 0) {
      result[claim.id] = take
      remaining = round(remaining - take)
    }
  }

  return result
})

const currentAllocation = computed(() => {
  if (mode.value === 'auto') return autoAllocation.value

  return Object.fromEntries(
    Object.entries(manualAllocation)
      .map(([claimId, value]) => [claimId, round(Number(value) || 0)])
      .filter(([, value]) => value > 0)
  )
})

const allocationSum = computed(() =>
  round(Object.values(currentAllocation.value).reduce((total, value) => total + value, 0))
)

const matches = computed(() =>
  targetAmount.value > 0 && Math.abs(allocationSum.value - targetAmount.value) < EPSILON
)

const switchToManual = () => {
  mode.value = 'manual'
  Object.keys(manualAllocation).forEach(key => delete manualAllocation[key])
}

const submit = () => {
  form.amount = targetAmount.value
  form.booked_at = bookedAt.value
  form.allocation_mode = mode.value
  form.allocation = currentAllocation.value
  form.close_case = closeCase.value

  form.post(route('inkasso.cases.payments.store', props.collectionCase.id), {
    preserveScroll: true,
    onSuccess: () => emit('done'),
  })
}
</script>

<style scoped>
@reference "tailwindcss";

.input-field {
  @apply w-full px-3 py-2.5 border border-gray-300 rounded-md text-sm focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600;
}

.btn-secondary {
  @apply px-4 py-2.5 bg-white border border-gray-300 text-gray-700 rounded-md text-sm font-medium hover:bg-gray-50 transition-colors;
}

.btn-primary {
  @apply px-4 py-2.5 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors disabled:bg-gray-100 disabled:text-gray-400 disabled:cursor-not-allowed;
}
</style>
