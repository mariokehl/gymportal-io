<template>
  <tr
    v-for="row in rows"
    :key="row"
    aria-hidden="true"
    :style="rowHeight ? { height: `${rowHeight}px` } : null"
  >
    <td v-for="column in columns" :key="column" :class="cellClass">
      <div
        class="rounded bg-fill motion-safe:animate-pulse"
        :class="barHeight"
        :style="{ width: barWidth(row, column) }"
      />
    </td>
  </tr>
</template>

<script setup>
// Placeholder rows shown while a table page is loading. Bar widths vary per
// cell so the skeleton reads as content rather than a striped block.
defineProps({
  rows: { type: Number, default: 10 },
  columns: { type: Number, required: true },
  // Measured height of the real rows; the cell padding is only the fallback
  // when nothing could be measured.
  rowHeight: { type: Number, default: null },
  cellClass: { type: String, default: 'px-3 py-3.5' },
  barHeight: { type: String, default: 'h-3.5' }
})

const WIDTHS = ['70%', '45%', '85%', '55%', '35%', '65%']

const barWidth = (row, column) => WIDTHS[(row * 3 + column) % WIDTHS.length]
</script>
