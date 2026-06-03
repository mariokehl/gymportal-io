<template>
  <span
    :class="[
      'inline-flex items-center gap-1.5 px-2 py-1 text-xs font-semibold rounded-full',
      statusClasses
    ]"
  >
    <span
      v-if="showIcon"
      class="w-2 h-2 shrink-0 rounded-full border border-current"
      :class="filled ? 'bg-current' : 'bg-transparent'"
    />
    {{ statusText }}
  </span>
</template>

<script setup>
import { computed } from 'vue'
import { getStatusText, getStatusBadgeClass, isStatusFilled } from '@/utils/paymentStatus'

const props = defineProps({
  status: {
    type: String,
    required: true
  },
  // payment | chargeback | refund
  type: {
    type: String,
    default: 'payment'
  },
  showIcon: {
    type: Boolean,
    default: true
  }
})

const statusClasses = computed(() => getStatusBadgeClass(props.status, props.type))
const statusText = computed(() => getStatusText(props.status, props.type))
const filled = computed(() => isStatusFilled(props.status, props.type))
</script>
