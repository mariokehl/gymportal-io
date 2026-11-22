<template>
  <div class="flex flex-wrap items-start gap-2 rounded-lg border border-line bg-surface py-2 pl-2.5 pr-2">
    <div class="w-[52px] shrink-0 pt-1.5">
      <span v-if="isFirst" class="text-compact font-medium text-ink-muted">Wenn</span>
      <button
        v-else
        type="button"
        title="UND / ODER umschalten"
        class="rounded-full border border-brand-line bg-brand-subtle px-2 py-0.5 text-2xs font-semibold tracking-wider text-brand-strong hover:bg-brand-soft"
        @click="emit('toggle-logic')"
      >
        {{ LOGIC_LABELS[logic] }}
      </button>
    </div>

    <div ref="pickerWrapper" class="relative max-w-full">
      <button
        type="button"
        class="inline-flex h-[34px] max-w-full items-center gap-1.5 rounded-md border border-line-strong bg-surface px-2.5 text-sm hover:border-brand-line"
        @click="pickerOpen = !pickerOpen"
      >
        <template v-if="field">
          <span class="text-xs text-ink-muted">{{ field.entity }}</span>
          <span class="text-ink-disabled">/</span>
          <span class="font-medium text-ink">{{ field.label }}</span>
        </template>
        <span v-else class="text-ink-muted">Feld auswählen …</span>
        <ChevronDown class="h-3.5 w-3.5 text-ink-muted" />
      </button>
      <FilterFieldPicker v-if="pickerOpen" :fields="fields" @select="selectField" @close="pickerOpen = false" />
    </div>

    <select
      v-if="field"
      :value="condition.op"
      class="field h-[34px] py-0 pl-2 pr-8"
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
          <span class="text-compact text-ink-muted">und</span>
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
          <span class="text-compact text-ink-muted">und</span>
          <input
            :value="condition.value2"
            type="number"
            :min="field.type === 'number' ? undefined : 0"
            :step="field.type === 'number' ? 'any' : 1"
            :class="[inputClass, 'w-[84px]']"
            @input="update({ value2: $event.target.value })"
          />
        </template>
        <span class="text-compact text-ink-muted">{{ field.type === 'date' ? 'Tagen' : field.unit }}</span>
      </div>
    </template>

    <div v-if="field?.type === 'enum'" class="flex max-w-[560px] flex-wrap gap-1.5 pt-[3px]">
      <button
        v-for="option in field.options"
        :key="option.value"
        type="button"
        :class="[
          'inline-flex items-center gap-1 rounded-full border px-2.5 py-[3px] text-compact font-medium',
          isSelected(option.value)
            ? 'border-brand bg-brand text-white'
            : 'border-line-strong bg-surface text-ink-secondary hover:border-brand-line hover:text-brand-strong'
        ]"
        @click="toggleOption(option.value)"
      >
        <Check v-if="isSelected(option.value)" class="h-3 w-3" :stroke-width="3" />
        {{ option.label }}
      </button>
      <span v-if="field.options.length === 0" class="pt-1 text-compact text-ink-muted">Keine Werte vorhanden.</span>
    </div>

    <div class="ml-auto flex items-center gap-0.5 pt-0.5">
      <span v-if="field && !complete" class="mr-1.5 rounded-full bg-warning-soft px-2 py-0.5 text-xs text-warning">Unvollständig · wird ignoriert</span>
      <button type="button" title="Duplizieren" class="rounded-md p-1.5 text-ink-muted hover:bg-fill hover:text-ink" @click="emit('duplicate')">
        <Copy class="h-4 w-4" />
      </button>
      <button type="button" title="Bedingung entfernen" class="rounded-md p-1.5 text-ink-muted hover:bg-danger-soft hover:text-danger-strong" @click="emit('remove')">
        <X class="h-4 w-4" />
      </button>
    </div>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { Check, ChevronDown, Copy, X } from '@lucide/vue'
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

const inputClass = 'field h-[34px] px-2.5'

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
