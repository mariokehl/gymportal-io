<template>
  <Tooltip v-if="discount" :text="tooltip">
    <span
      class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-green-50 text-green-700 border border-green-200 align-middle"
      aria-label="Rabattierte Zahlung"
    >
      <Percent class="w-3 h-3" />
    </span>
  </Tooltip>
</template>

<script setup>
import { computed } from 'vue'
import { Percent } from 'lucide-vue-next'
import Tooltip from '@/Components/Tooltip.vue'
import { formatCurrency } from '@/utils/formatters'

const props = defineProps({
  /** The `discount` block a discounted payment carries in its metadata. */
  discount: { type: Object, default: null },
})

/**
 * Spells out what the reduced amount replaced, so the operator can see at a
 * glance why this charge differs from the contract's regular price.
 */
const tooltip = computed(() => {
  if (!props.discount) {
    return ''
  }

  const { period_start_month: start, period_end_month: end, regular_price: regular, savings } = props.discount

  const period = start === end ? `Monat ${start}` : `Monat ${start}–${end}`

  return `Rabatt (${period}): statt ${formatCurrency(regular)} · ${formatCurrency(savings)} gespart`
})
</script>
