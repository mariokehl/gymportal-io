<template>
  <div class="space-y-6">
    <!-- Mitgliedschaftsplan Auswahl -->
    <div v-if="!membershipPlans || membershipPlans.length === 0" class="p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
      <div class="flex">
        <div class="flex-shrink-0">
          <AlertTriangle class="h-5 w-5 text-yellow-400" />
        </div>
        <div class="ml-3">
          <p class="text-sm text-yellow-800">
            Keine Mitgliedschaftspläne verfügbar. Bitte zunächst
            <Link :href="route('contracts.index')" class="font-medium underline hover:text-yellow-900">Verträge</Link>
            anlegen.
          </p>
        </div>
      </div>
    </div>

    <div v-else>
      <!-- Plan Selection -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-3">
          Mitgliedschaftsplan auswählen <span class="text-red-500">*</span>
        </label>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          <div
            v-for="plan in membershipPlans"
            :key="plan.id"
            class="border rounded-lg p-4 cursor-pointer transition-all hover:shadow-md"
            :class="modelValue.membership_plan_id === plan.id ? 'border-indigo-500 bg-indigo-50 ring-2 ring-indigo-500' : 'border-gray-300'"
            @click="selectPlan(plan.id)"
          >
            <div class="flex items-center mb-3">
              <input
                type="radio"
                :id="`plan_${plan.id}`"
                :value="plan.id"
                :checked="modelValue.membership_plan_id === plan.id"
                class="w-4 h-4 text-indigo-600 focus:ring-indigo-500"
                @change="selectPlan(plan.id)"
              />
              <label :for="`plan_${plan.id}`" class="ml-2 font-medium text-gray-900">
                {{ plan.name }}
              </label>
            </div>
            <p v-if="plan.description" class="text-sm text-gray-600 mb-3">{{ plan.description }}</p>
            <div class="flex justify-between items-center">
              <span class="text-lg font-bold text-indigo-600">
                {{ formatCurrency(plan.price) }}
              </span>
              <span class="text-sm text-gray-500">
                / {{ getBillingCycleText(plan.billing_cycle) }}
              </span>
            </div>
            <div class="mt-2 text-xs text-gray-500">
              <span v-if="plan.commitment_months > 0">
                Mindestlaufzeit: {{ plan.commitment_months }} Monate
              </span>
              <span v-else>Monatlich kündbar</span>
            </div>
            <div v-if="plan.setup_fee && plan.setup_fee > 0" class="mt-2 text-xs text-orange-600">
              Aktivierungsgebühr: {{ formatCurrency(plan.setup_fee) }}
            </div>
          </div>
        </div>
        <p v-if="errors.membership_plan_id" class="mt-2 text-sm text-red-600">
          {{ errors.membership_plan_id }}
        </p>
      </div>

      <!-- Date Selection -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
        <!-- Allow Past Start Date Checkbox -->
        <div class="md:col-span-2">
          <label class="flex items-center">
            <input
              type="checkbox"
              :checked="modelValue.allow_past_start_date"
              @change="updateField('allow_past_start_date', $event.target.checked)"
              class="w-4 h-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
            />
            <span class="ml-2 text-sm text-gray-700">
              Mitgliedschaft in der Vergangenheit beginnen lassen
            </span>
          </label>
        </div>

        <!-- Start Date -->
        <div>
          <label for="start_date" class="block text-sm font-medium text-gray-700 mb-2">
            Startdatum der Mitgliedschaft <span class="text-red-500">*</span>
          </label>
          <input
            id="start_date"
            type="date"
            :value="modelValue.start_date"
            @input="updateField('start_date', $event.target.value)"
            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
            :class="{ 'border-red-500': errors.start_date }"
            :min="modelValue.allow_past_start_date ? null : today"
          />
          <p v-if="errors.start_date" class="mt-1 text-sm text-red-600">
            {{ errors.start_date }}
          </p>
        </div>

        <!-- End Date (calculated) -->
        <div v-if="selectedPlan">
          <label class="block text-sm font-medium text-gray-700 mb-2">
            Vertragslaufzeit bis
          </label>
          <div class="w-full px-3 py-2 bg-gray-50 border border-gray-300 rounded-md text-gray-600">
            {{ calculatedEndDate }}
          </div>
        </div>
      </div>

      <!-- Billing Anchor Date -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
        <div>
          <label for="billing_anchor_date" class="block text-sm font-medium text-gray-700 mb-2">
            Erste Abrechnung am (optional)
          </label>
          <input
            id="billing_anchor_date"
            type="date"
            :value="modelValue.billing_anchor_date"
            @input="updateField('billing_anchor_date', $event.target.value)"
            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
            :class="{ 'border-red-500': errors.billing_anchor_date }"
            :min="today"
          />
          <p v-if="errors.billing_anchor_date" class="mt-1 text-sm text-red-600">
            {{ errors.billing_anchor_date }}
          </p>
          <p class="mt-1 text-xs text-gray-500">
            Wenn angegeben, wird die erste Abrechnung zu diesem Datum erstellt.
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { AlertTriangle } from 'lucide-vue-next'
import { formatCurrency } from '@/utils/formatters'

const props = defineProps({
  modelValue: {
    type: Object,
    required: true
  },
  membershipPlans: {
    type: Array,
    default: () => []
  },
  errors: {
    type: Object,
    default: () => ({})
  }
})

const emit = defineEmits(['update:modelValue'])

const today = computed(() => {
  return new Date().toISOString().split('T')[0]
})

const selectedPlan = computed(() => {
  if (!props.membershipPlans || !props.modelValue.membership_plan_id) {
    return null
  }
  return props.membershipPlans.find(plan => plan.id === props.modelValue.membership_plan_id)
})

const calculatedEndDate = computed(() => {
  if (!selectedPlan.value || !props.modelValue.start_date) {
    return 'Bitte Startdatum wählen'
  }

  if (!selectedPlan.value.commitment_months || selectedPlan.value.commitment_months === 0) {
    return 'Unbefristet'
  }

  const startDate = new Date(props.modelValue.start_date)
  const todayDate = new Date()
  todayDate.setHours(0, 0, 0, 0)

  let multiplier = 1
  let endDate

  while (true) {
    const targetMonth = startDate.getMonth() + (selectedPlan.value.commitment_months * multiplier)
    const targetYear = startDate.getFullYear() + Math.floor(targetMonth / 12)
    const adjustedMonth = targetMonth % 12

    const tempDate = new Date(targetYear, adjustedMonth, startDate.getDate())

    if (tempDate.getMonth() !== adjustedMonth) {
      endDate = new Date(targetYear, adjustedMonth + 1, 0)
    } else {
      endDate = new Date(tempDate)
      endDate.setDate(endDate.getDate() - 1)
    }

    if (endDate >= todayDate) break
    multiplier++
  }

  return endDate.toLocaleDateString('de-DE')
})

const updateField = (field, value) => {
  emit('update:modelValue', {
    ...props.modelValue,
    [field]: value
  })
}

const selectPlan = (planId) => {
  updateField('membership_plan_id', planId)
}

const getBillingCycleText = (cycle) => {
  const cycles = {
    'monthly': 'Monat',
    'quarterly': 'Quartal',
    'yearly': 'Jahr'
  }
  return cycles[cycle] || cycle
}
</script>
