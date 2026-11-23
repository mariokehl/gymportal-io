<template>
  <section class="rounded-lg border border-line bg-surface shadow-sm">
    <div class="flex flex-wrap items-center gap-2 px-3 py-2.5">
      <button
        type="button"
        :class="[
          'inline-flex h-[34px] items-center gap-1.5 rounded-md border pl-3 pr-2.5 text-sm font-medium',
          open || activeCount > 0
            ? 'border-brand-line bg-brand-subtle text-brand-ink-strong hover:bg-brand-soft'
            : 'border-line-strong bg-surface text-ink-secondary hover:bg-surface-muted'
        ]"
        :aria-expanded="open"
        @click="toggleOpen"
      >
        <Filter class="h-4 w-4" />
        Filter
        <span v-if="activeCount > 0" class="inline-flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-brand px-1.5 text-2xs font-semibold text-white">{{ activeCount }}</span>
        <ChevronDown :class="['h-3.5 w-3.5 opacity-60 transition-transform', open && 'rotate-180']" />
      </button>

      <div ref="savedMenuWrapper" class="relative">
        <button
          type="button"
          class="inline-flex h-[34px] max-w-[320px] items-center gap-1.5 rounded-md border border-line-strong bg-surface pl-3 pr-2.5 text-sm font-medium text-ink-secondary hover:bg-surface-muted"
          :aria-expanded="savedMenuOpen"
          @click="savedMenuOpen = !savedMenuOpen"
        >
          <Bookmark class="h-4 w-4 shrink-0" />
          <span class="truncate">{{ activeSaved ? activeSaved.name + (dirty ? ' (geändert)' : '') : 'Gespeicherte Filter' }}</span>
          <ChevronDown class="h-3.5 w-3.5 shrink-0 text-ink-muted" />
        </button>

        <div v-if="savedMenuOpen" class="absolute left-0 top-full z-40 mt-1 w-[320px] max-w-[calc(100vw-2rem)] rounded-lg border border-line bg-surface py-1 shadow-lg">
          <div class="px-3 pb-1 pt-2 text-2xs font-medium uppercase tracking-wider text-ink-muted">Gespeicherte Filter</div>
          <div class="max-h-72 overflow-y-auto">
            <div v-for="saved in savedFilters" :key="saved.id" class="flex items-center hover:bg-surface-muted">
              <button type="button" class="flex min-w-0 flex-1 items-center gap-2 px-3 py-2 text-left text-sm text-ink" @click="loadSaved(saved)">
                <span class="flex w-3.5 shrink-0 text-brand-ink"><Check v-if="saved.id === activeSavedId" class="h-3.5 w-3.5" /></span>
                <span class="truncate">{{ saved.name }}</span>
                <span v-if="saved.id === activeSavedId && dirty" class="whitespace-nowrap text-xs text-warning">· geändert</span>
              </button>
              <button
                type="button"
                title="Filter löschen"
                class="mr-1.5 rounded-md p-1.5 text-ink-muted hover:bg-danger-soft hover:text-danger-strong"
                @click="removeSaved(saved)"
              >
                <Trash2 class="h-3.5 w-3.5" />
              </button>
            </div>
          </div>
          <div v-if="savedFilters.length === 0" class="px-3 py-2 text-compact text-ink-muted">Noch keine Filter gespeichert.</div>
          <div class="my-1 h-px bg-fill" />
          <button
            type="button"
            class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm font-medium text-brand-ink hover:bg-brand-subtle disabled:cursor-not-allowed disabled:text-ink-muted disabled:hover:bg-transparent"
            :disabled="activeCount === 0"
            :title="activeCount === 0 ? 'Legen Sie zuerst mindestens eine Bedingung fest.' : undefined"
            @click="openSave"
          >
            <Plus class="h-3.5 w-3.5" />
            {{ activeSaved && dirty ? 'Änderungen speichern' : 'Filter speichern' }}
          </button>
        </div>
      </div>

      <div v-if="!open && activeCount > 0" class="flex min-w-0 flex-[1_1_280px] flex-wrap items-center gap-1.5 border-l border-line pl-2">
        <template v-for="(token, index) in chips" :key="index">
          <span v-if="token.kind === 'condition'" class="inline-flex max-w-[340px] items-center rounded-full border border-line bg-surface-muted">
            <button
              type="button"
              title="Filter bearbeiten"
              class="truncate py-[3px] pl-2.5 pr-1 text-compact text-ink-secondary"
              @click="open = true"
            >
              <span class="font-medium text-ink">{{ token.field }}</span> {{ [token.operator, token.value].filter(Boolean).join(' ') }}
            </button>
            <button
              type="button"
              title="Bedingung entfernen"
              class="flex py-[3px] pl-0.5 pr-2 text-ink-muted hover:text-danger-strong"
              @click="removeCondition(token.groupId, token.conditionId)"
            >
              <X class="h-3 w-3" />
            </button>
          </span>
          <span v-else-if="token.kind === 'logic'" class="text-2xs font-semibold tracking-wider text-brand-ink-strong">{{ token.text }}</span>
          <span v-else class="font-semibold text-ink-muted">{{ token.text }}</span>
        </template>
      </div>
      <span v-else-if="!open" class="flex-[1_1_240px] pl-1 text-compact text-ink-muted">
        Keine Filter aktiv – alle {{ subject }} werden angezeigt.
      </span>

      <div class="ml-auto flex items-center gap-2">
        <slot name="actions" />
        <button
          v-if="activeCount > 0 || activeSavedId"
          type="button"
          class="inline-flex h-[34px] items-center gap-1.5 rounded-md px-2.5 text-sm font-medium text-ink-muted hover:bg-fill hover:text-ink"
          @click="reset"
        >
          <RotateCcw class="h-4 w-4" />
          Zurücksetzen
        </button>
      </div>
    </div>

    <div v-if="open" class="border-t border-line-subtle">
      <form
        v-if="saving"
        class="flex flex-wrap items-center gap-2 border-b border-line-subtle bg-brand-subtle px-3 py-2.5"
        @submit.prevent="confirmSave"
      >
        <label for="saved-filter-name" class="text-sm font-medium text-brand-ink-strong">Name des Filters</label>
        <input
          id="saved-filter-name"
          ref="saveNameInput"
          v-model="saveName"
          type="text"
          maxlength="100"
          placeholder="z. B. Zahlungsprobleme Q4"
          class="field h-[34px] w-[280px] max-w-full px-2.5"
          @keydown.esc="saving = false"
        />
        <button
          type="submit"
          :disabled="!saveName.trim() || isSaving"
          class="h-[34px] rounded-md bg-brand px-3.5 text-sm font-medium text-white hover:bg-brand-strong disabled:opacity-50"
        >
          Speichern
        </button>
        <button type="button" class="h-[34px] rounded-md px-3 text-sm font-medium text-brand-ink-strong" @click="saving = false">Abbrechen</button>
        <span v-if="saveError" class="text-xs text-danger">{{ saveError }}</span>
        <span v-else-if="overwrites" class="text-xs text-brand-ink">Vorhandener Filter wird überschrieben.</span>
      </form>

      <FilterBuilder
        :model-value="modelValue"
        :fields="fields"
        :fields-by-key="fieldsByKey"
        :subject="subject"
        @update:model-value="emit('update:modelValue', $event)"
      />
    </div>
  </section>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { Bookmark, Check, ChevronDown, Filter, Plus, RotateCcw, Trash2, X } from '@lucide/vue'
import FilterBuilder from '@/Components/Filters/FilterBuilder.vue'
import {
  activeConditionCount, describeDefinition, emptyDefinition, normalizeDefinition, sameDefinition, serializeDefinition,
} from '@/utils/filterBuilder'

const props = defineProps({
  modelValue: { type: Object, required: true },
  fields: { type: Array, required: true },
  savedFilters: { type: Array, default: () => [] },
  // Saved filter context of the list, e.g. "members".
  context: { type: String, required: true },
  // Plural noun of the filtered records, e.g. "Mitglieder".
  subject: { type: String, required: true },
  activeSavedId: { type: Number, default: null },
})

const emit = defineEmits(['update:modelValue', 'update:activeSavedId'])

const open = ref(false)
const savedMenuOpen = ref(false)
const savedMenuWrapper = ref(null)
const saving = ref(false)
const isSaving = ref(false)
const saveName = ref('')
const saveError = ref('')
const saveNameInput = ref(null)

const fieldsByKey = computed(() => Object.fromEntries(props.fields.map(field => [field.key, field])))
const activeCount = computed(() => activeConditionCount(props.modelValue, fieldsByKey.value))
const chips = computed(() => describeDefinition(props.modelValue, fieldsByKey.value))
const activeSaved = computed(() => props.savedFilters.find(saved => saved.id === props.activeSavedId) ?? null)
const dirty = computed(() => !!activeSaved.value && !sameDefinition(activeSaved.value.definition, props.modelValue))
const overwrites = computed(() => {
  const name = saveName.value.trim().toLowerCase()
  return !!name && props.savedFilters.some(saved => saved.name.toLowerCase() === name)
})

const toggleOpen = () => {
  open.value = !open.value
  if (!open.value) saving.value = false
}

const reset = () => {
  emit('update:modelValue', emptyDefinition())
  emit('update:activeSavedId', null)
  saving.value = false
}

const removeCondition = (groupId, conditionId) => emit('update:modelValue', {
  ...props.modelValue,
  groups: props.modelValue.groups.map(group => (group.id === groupId
    ? { ...group, conditions: group.conditions.filter(condition => condition.id !== conditionId) }
    : group)),
})

const loadSaved = saved => {
  savedMenuOpen.value = false
  emit('update:modelValue', normalizeDefinition(saved.definition))
  emit('update:activeSavedId', saved.id)
}

// Flash messages arrive with every response, independent of "only".
const reloadOptions = { preserveScroll: true, preserveState: true, only: ['savedFilters'] }

const removeSaved = saved => {
  if (!confirm(`Möchten Sie den Filter „${saved.name}“ wirklich löschen? Er wird für alle Mitarbeiter entfernt.`)) return

  router.delete(route('saved-filters.destroy', saved.id), {
    ...reloadOptions,
    onSuccess: () => {
      if (props.activeSavedId === saved.id) emit('update:activeSavedId', null)
    },
  })
}

const openSave = async () => {
  savedMenuOpen.value = false
  open.value = true
  saving.value = true
  saveError.value = ''
  saveName.value = activeSaved.value?.name ?? ''
  await nextTick()
  saveNameInput.value?.focus()
}

const confirmSave = () => {
  const name = saveName.value.trim()
  if (!name || isSaving.value) return

  const definition = JSON.parse(serializeDefinition(props.modelValue) ?? '{"rootLogic":"AND","groups":[]}')
  isSaving.value = true

  router.post(route('saved-filters.store'), { context: props.context, name, definition }, {
    ...reloadOptions,
    onSuccess: page => {
      const saved = page.props.savedFilters.find(filter => filter.name.toLowerCase() === name.toLowerCase())
      emit('update:activeSavedId', saved?.id ?? null)
      saving.value = false
    },
    onError: errors => {
      saveError.value = errors.name ?? errors.definition ?? 'Der Filter konnte nicht gespeichert werden.'
    },
    onFinish: () => {
      isSaving.value = false
    },
  })
}

const closeOnOutsideClick = event => {
  if (savedMenuOpen.value && !savedMenuWrapper.value?.contains(event.target)) savedMenuOpen.value = false
}

onMounted(() => document.addEventListener('mousedown', closeOnOutsideClick))
onBeforeUnmount(() => document.removeEventListener('mousedown', closeOnOutsideClick))
</script>
