<template>
  <div class="absolute left-0 top-full z-40 mt-1 w-[340px] max-w-[calc(100vw-2rem)] overflow-hidden rounded-lg border border-line bg-surface shadow-lg">
    <div class="flex items-center gap-2 border-b border-line-subtle px-3 py-2 text-ink-muted">
      <Search class="h-4 w-4 shrink-0" />
      <input
        ref="searchInput"
        v-model="query"
        type="text"
        placeholder="Feld oder Bereich suchen …"
        class="h-7 flex-1 border-0 bg-transparent p-0 text-sm text-ink placeholder-ink-muted"
        @keydown.esc="emit('close')"
        @keydown.enter.prevent="selectFirst"
      />
    </div>
    <div class="max-h-[340px] overflow-y-auto pb-1.5 pt-1">
      <template v-for="group in groups" :key="group.entity">
        <div class="px-3 pb-1 pt-2.5 text-2xs font-medium uppercase tracking-wider text-ink-muted">{{ group.entity }}</div>
        <button
          v-for="field in group.fields"
          :key="field.key"
          type="button"
          class="flex w-full items-center justify-between gap-3 px-3 py-[7px] text-left text-sm text-ink hover:bg-brand-subtle hover:text-brand-ink-strong"
          @click="emit('select', field)"
        >
          <span>{{ field.label }}</span>
          <span class="text-xs text-ink-muted">{{ TYPE_LABELS[field.type] }}</span>
        </button>
      </template>
      <div v-if="groups.length === 0" class="px-3 py-4 text-compact text-ink-muted">Kein passendes Feld gefunden.</div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { Search } from '@lucide/vue'
import { TYPE_LABELS } from '@/utils/filterBuilder'

const props = defineProps({
  fields: { type: Array, required: true },
})

const emit = defineEmits(['select', 'close'])

const query = ref('')
const searchInput = ref(null)

// Fields grouped by entity, in the order the backend lists them.
const groups = computed(() => {
  const search = query.value.trim().toLowerCase()
  const byEntity = new Map()

  props.fields
    .filter(field => !search || `${field.label} ${field.entity}`.toLowerCase().includes(search))
    .forEach(field => {
      if (!byEntity.has(field.entity)) byEntity.set(field.entity, [])
      byEntity.get(field.entity).push(field)
    })

  return [...byEntity].map(([entity, fields]) => ({ entity, fields }))
})

const selectFirst = () => {
  const first = groups.value[0]?.fields[0]
  if (first) emit('select', first)
}

onMounted(() => searchInput.value?.focus())
</script>
