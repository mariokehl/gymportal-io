<template>
  <div v-if="badges.length" class="flex items-center gap-1.5">
    <span
      v-for="badge in badges"
      :key="badge.key"
      :class="['inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold tabular-nums', badge.classes]"
      :title="badge.label"
    >
      <component :is="badge.icon" class="h-3 w-3" aria-hidden="true" />
      <span aria-hidden="true">{{ badge.count }}</span>
      <span class="sr-only">{{ badge.label }}</span>
    </span>
  </div>
  <span v-else class="text-sm text-ink-muted">
    <span aria-hidden="true">–</span>
    <span class="sr-only">Keine Studios</span>
  </span>
</template>

<script setup>
import { computed } from 'vue'
import { CalendarX, CreditCard, Hourglass } from '@lucide/vue'

const props = defineProps({ user: Object })

const plural = (count, one, many) => (count === 1 ? `1 ${one}` : `${count} ${many}`)

const badges = computed(() => [
  {
    key: 'paid',
    count: props.user.paid_gyms_count,
    icon: CreditCard,
    classes: 'bg-success-soft text-success',
    label: plural(props.user.paid_gyms_count, 'bezahltes Abo', 'bezahlte Abos'),
  },
  {
    key: 'trial',
    count: props.user.trial_gyms_count,
    icon: Hourglass,
    classes: 'bg-info-soft text-info',
    label: plural(props.user.trial_gyms_count, 'Studio in aktiver Testphase', 'Studios in aktiver Testphase'),
  },
  {
    key: 'expired',
    count: props.user.expired_gyms_count,
    icon: CalendarX,
    classes: 'bg-danger-soft text-danger',
    label: plural(props.user.expired_gyms_count, 'Studio mit abgelaufener Testphase', 'Studios mit abgelaufener Testphase'),
  },
].filter(badge => badge.count > 0))
</script>
