<template>
  <span
    v-if="provider"
    :title="`${provider.name} · ${aggregatorStatusLabel(status)}`"
    :aria-label="`${provider.name} · ${aggregatorStatusLabel(status)}`"
    class="inline-block w-7 flex-none rounded-full py-px text-center text-[10px] font-bold leading-4 tracking-[0.04em]"
    :style="aggregatorBadgeStyle(provider, status)"
  >
    {{ provider.short }}
  </span>
</template>

<script setup>
import { computed } from 'vue'
import { aggregatorBadgeStyle, aggregatorStatusLabel, findAggregator } from '@/utils/aggregators'

const props = defineProps({
  // Provider key, e.g. "wellpass"
  aggregator: {
    type: String,
    default: null,
  },
  // "pending" or "linked"
  status: {
    type: String,
    default: 'pending',
  },
})

const provider = computed(() => findAggregator(props.aggregator))
</script>
