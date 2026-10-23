<template>
  <div class="flex flex-wrap items-start gap-2 rounded-lg border border-gray-200 bg-white py-2 pl-2.5 pr-2">
    <div class="w-[52px] shrink-0 pt-1.5">
      <span v-if="isFirst" class="text-[13px] font-medium text-gray-500">Wenn</span>
      <button
        v-else
        type="button"
        title="UND / ODER umschalten"
        class="rounded-full border border-indigo-200 bg-indigo-50 px-2 py-0.5 text-[11px] font-semibold tracking-wider text-indigo-700 hover:bg-indigo-100"
        @click="emit('toggle-logic')"
      >
        {{ LOGIC_LABELS[logic] }}
      </button>
    </div>

    <div ref="pickerWrapper" class="relative max-w-full">
      <button
        type="button"
        class="inline-flex h-[34px] max-w-full items-center gap-1.5 rounded-md border border-gray-300 bg-white px-2.5 text-sm hover:border-indigo-300"
        @click="pickerOpen = !pickerOpen"
      >
        <template v-if="field">
          <span class="text-xs text-gray-500">{{ field.entity }}</span>
          <span class="text-gray-300">/</span>
          <span class="font-medium text-gray-900">{{ field.label }}</span>
        </template>
        <span v-else class="text-gray-500">Feld auswählen …</span>
        <ChevronDown class="h-3.5 w-3.5 text-gray-400" />
      </button>
      <FilterFieldPicker v-if="pickerOpen" :fields="fields" @select="selectField" @close="pickerOpen = false" />
    </div>

    <select
      v-if="field"
      :value="condition.op"
      class="h-[34px] rounded-md border border-gray-300 bg-white py-0 pl-2 pr-8 text-sm text-gray-900 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200"
      @change="changeOperator($event.target.value)"
    >
      <option v-for="option in operatorOptions(field.type)" :key="option.value" :value="option.value">{{ option.label }}</option>
    </select>

    <template v-if="field && needsValue(field.type, condition.op)">
      <input
        v-if="field.type === 'text'"
        :value="condition.value"
        type="text"
        maxlength="100"
        :placeholder="field.placeholder || 'Wert eingeben'"
        :class="[inputClass, 'w-56']"
        @input="update({ value: $event.target.value })"
      />

      <div v-else-if="field.type === 'date' && !DAY_OPERATORS.includes(condition.op)" class="flex items-center gap-1.5">
        <input :value="condition.value" type="date" :class="inputClass" @input="update({ value: $event.target.value })" />
        <template v-if="condition.op === 'between'">
          <span class="text-[13px] text-gray-500">und</span>
          <input :value="condition.value2" type="date" :class="inputClass" @input="update({ value2: $event.target.value })" />
        </template>
      </div>

      <div v-else class="flex items-center gap-1.5">
        <input
          :value="condition.value"
          type="number"
          :min="field.type === 'number' ? undefined : 0"
          :step="field.type === 'number' ? 'any' : 1"
          :class="[inputClass, condition.op === 'between' ? 'w-[84px]' : 'w-24']"
          @input="update({ value: $event.target.value })"
        />
        <template v-if="condition.op === 'between'">
          <span class="text-[13px] text-gray-500">und</span>
          <input
            :value="condition.value2"
            type="number"
            :min="field.type === 'number' ? undefined : 0"
            :step="field.type === 'number' ? 'any' : 1"
            :class="[inputClass, 'w-[84px]']"
            @input="update({ value2: $event.target.value })"
          />
        </template>
        <span class="text-[13px] text-gray-500">{{ field.type === 'date' ? 'Tagen' : field.unit }}</span>
      </div>
    </template>

    <div v-if="field?.type === 'enum'" class="flex max-w-[560px] flex-wrap gap-1.5 pt-[3px]">
      <button
        v-for="option in field.options"
        :key="option.value"
        type="button"
        :class="[
          'inline-flex items-center gap-1 rounded-full border px-2.5 py-[3px] text-[13px] font-medium',
          isSelected(option.value)
            ? 'border-indigo-600 bg-indigo-600 text-white'
            : 'border-gray-300 bg-white text-gray-700 hover:border-indigo-300 hover:text-indigo-700'
        ]"
        @click="toggleOption(option.value)"
      >
        <Check v-if="isSelected(option.value)" class="h-3 w-3" :stroke-width="3" />
        {{ option.label }}
      </button>
      <span v-if="field.options.length === 0" class="pt-1 text-[13px] text-gray-500">Keine Werte vorhanden.</span>
    </div>

    <div class="ml-auto flex items-center gap-0.5 pt-0.5">
      <span v-if="field && !complete" class="mr-1.5 rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-700">Unvollständig · wird ignoriert</span>
      <button type="button" title="Duplizieren" class="rounded-md p-1.5 text-gray-500 hover:bg-gray-100 hover:text-gray-900" @click="emit('duplicate')">
        <Copy class="h-4 w-4" />
      </button>
      <button type="button" title="Bedingung entfernen" class="rounded-md p-1.5 text-gray-500 hover:bg-red-100 hover:text-red-600" @click="emit('remove')">
        <X class="h-4 w-4" />
      </button>
    </div>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { Check, ChevronDown, Copy, X } from 'lucide-vue-next'
import FilterFieldPicker from '@/Components/Filters/FilterFieldPicker.vue'
import {
  DAY_OPERATORS, LOGIC_LABELS, applyField, isComplete, isIsoDate, needsValue, operatorOptions,
} from '@/utils/filterBuilder'

const props = defineProps({
  condition: { type: Object, required: true },
  fields: { type: Array, required: true },
  fieldsByKey: { type: Object, required: true },
  isFirst: { type: Boolean, default: false },
  logic: { type: String, default: 'AND' },
  // Opens the field picker right away, e.g. for a freshly added condition.
  autoOpen: { type: Boolean, default: false },
})

const emit = defineEmits(['update', 'toggle-logic', 'duplicate', 'remove'])

const inputClass = 'h-[34px] rounded-md border border-gray-300 px-2.5 text-sm text-gray-900 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200'

const pickerOpen = ref(props.autoOpen && !props.condition.field)
const pickerWrapper = ref(null)

const field = computed(() => props.fieldsByKey[props.condition.field] ?? null)
const complete = computed(() => isComplete(field.value, props.condition))

const update = patch => emit('update', { ...props.condition, ...patch })

const selectField = selected => {
  pickerOpen.value = false
  emit('update', applyField(props.condition, selected))
}

const isoDaysAgo = days => {
  const date = new Date()
  date.setDate(date.getDate() - days)
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
}

// Date operators switch between "days" and calendar dates; keep the inputs
// meaningful instead of carrying "30" into a date field and vice versa.
const changeOperator = op => {
  const patch = { op }

  if (field.value.type === 'date') {
    if (DAY_OPERATORS.includes(op) && isIsoDate(props.condition.value)) {
      patch.value = '30'
    } else if (['before', 'after', 'between'].includes(op) && !isIsoDate(props.condition.value)) {
      patch.value = isoDaysAgo(30)
      patch.value2 = isoDaysAgo(0)
    }
  }

  update(patch)
}

const isSelected = value => props.condition.values.includes(value)

const toggleOption = value => update({
  values: isSelected(value) ? props.condition.values.filter(selected => selected !== value) : [...props.condition.values, value],
})

const closeOnOutsideClick = event => {
  if (pickerOpen.value && !pickerWrapper.value?.contains(event.target)) pickerOpen.value = false
}

onMounted(() => document.addEventListener('mousedown', closeOnOutsideClick))
onBeforeUnmount(() => document.removeEventListener('mousedown', closeOnOutsideClick))
</script>
