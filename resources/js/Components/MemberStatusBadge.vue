<template>
  <span
    :class="[
      'inline-flex px-2 py-1 text-xs font-semibold rounded-full',
      `bg-${color}-100`,
      `text-${color}-700`
    ]"
  >
    {{ text }}
  </span>
</template>

<script setup>
const props = defineProps({
  status: String,
})

const statusColorMap = {
  active: 'green',
  inactive: 'gray',
  paused: 'yellow',
  overdue: 'red',
  pending: 'orange'
}

const textMap = {
  active: 'Aktiv',
  inactive: 'Inaktiv',
  paused: 'Pausiert',
  overdue: 'Überfällig',
  pending: 'Ausstehend'
}

const color = statusColorMap[props.status] ?? 'gray'
const text = textMap[props.status] ?? props.status
</script>
