<template>
  <span
    :class="[
      'inline-flex items-center gap-1 px-2 py-1 text-xs font-semibold rounded-full',
      statusClasses
    ]"
  >
    <component v-if="showIcon" :is="statusIcon" class="w-3 h-3" />
    {{ statusText }}
  </span>
</template>

<script setup>
import { computed } from 'vue'
import { getStatusText, getStatusBadgeClass, getStatusIcon } from '@/utils/memberStatus'

const props = defineProps({
  status: String,
  showIcon: {
    type: Boolean,
    default: false
  }
})

const statusClasses = computed(() => getStatusBadgeClass(props.status))
const statusText = computed(() => getStatusText(props.status))
const statusIcon = computed(() => getStatusIcon(props.status))
</script>
