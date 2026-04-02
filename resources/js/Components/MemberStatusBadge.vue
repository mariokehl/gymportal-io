<template>
  <span class="inline-flex items-center gap-1">
    <span
      :class="[
        'inline-flex items-center gap-1 px-2 py-1 text-xs font-semibold rounded-full',
        statusClasses
      ]"
    >
      <component v-if="showIcon" :is="statusIcon" class="w-3 h-3" />
      {{ statusText }}
    </span>
    <Tooltip v-if="outstandingBalance" position="top" :text="tooltipText">
      <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-amber-100 text-amber-600 cursor-default">
        <AlertTriangle class="w-3 h-3" />
      </span>
    </Tooltip>
  </span>
</template>

<script setup>
import { computed } from 'vue'
import { AlertTriangle } from 'lucide-vue-next'
import { getStatusText, getStatusBadgeClass, getStatusIcon } from '@/utils/memberStatus'
import Tooltip from '@/Components/Tooltip.vue'

const props = defineProps({
  status: String,
  showIcon: {
    type: Boolean,
    default: false
  },
  outstandingBalance: {
    type: Number,
    default: null
  }
})

const statusClasses = computed(() => getStatusBadgeClass(props.status))
const statusText = computed(() => getStatusText(props.status))
const statusIcon = computed(() => getStatusIcon(props.status))
const tooltipText = computed(() => {
  if (!props.outstandingBalance) return ''
  const formatted = props.outstandingBalance.toLocaleString('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
  return `Offene Posten: ${formatted} €`
})
</script>
