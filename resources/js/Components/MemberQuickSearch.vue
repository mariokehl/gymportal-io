<template>
  <div ref="root" class="relative">
    <div class="relative flex items-center">
      <Search class="absolute left-2.5 h-4 w-4 text-gray-400 pointer-events-none" aria-hidden="true" />
      <input
        ref="input"
        v-model="query"
        type="search"
        role="combobox"
        autocomplete="off"
        enterkeyhint="search"
        placeholder="Mitglied suchen…"
        aria-label="Mitglied suchen"
        aria-autocomplete="list"
        :aria-expanded="open"
        :aria-controls="listId"
        :aria-activedescendant="open && rows[activeIndex] ? optionId(activeIndex) : undefined"
        class="w-full h-10 lg:h-9 pl-8 pr-8 rounded-md border border-gray-300 bg-gray-50 text-base lg:text-sm text-gray-900 placeholder:text-gray-400 outline-none transition-colors focus:bg-white focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 [&::-webkit-search-cancel-button]:hidden"
        @focus="openDropdown"
        @blur="onBlur"
        @input="onInput"
        @keydown="onKeydown"
      >
      <button
        v-if="query"
        type="button"
        class="absolute right-1.5 p-1 rounded text-gray-400 hover:text-gray-600"
        aria-label="Suche leeren"
        @mousedown.prevent
        @click="clear"
      >
        <X class="h-4 w-4" />
      </button>
      <kbd
        v-else
        class="hidden lg:inline absolute right-2 px-1.5 rounded border border-gray-200 font-sans text-[11px] leading-4 text-gray-400 pointer-events-none"
      >/</kbd>
    </div>

    <div
      v-if="open"
      class="absolute left-0 right-0 lg:right-auto lg:w-[25rem] top-full mt-2 z-30 py-2 bg-white border border-gray-200 rounded-lg shadow-lg"
    >
      <div class="px-4 py-1.5 flex items-center justify-between text-xs font-medium uppercase tracking-wider text-gray-500">
        <span>{{ hasQuery ? 'Mitglieder' : 'Zuletzt angesehen' }}</span>
        <span v-if="hasQuery && !loading" class="normal-case tracking-normal text-gray-400">
          {{ rows.length === 1 ? '1 Treffer' : `${rows.length} Treffer` }}
        </span>
        <Loader2 v-if="loading" class="h-3.5 w-3.5 animate-spin text-gray-400" aria-hidden="true" />
      </div>

      <ul :id="listId" role="listbox" aria-label="Mitglieder" class="max-h-[60vh] overflow-y-auto">
        <li
          v-for="(member, index) in rows"
          :id="optionId(index)"
          :key="member.id"
          role="option"
          :aria-selected="index === activeIndex"
          class="flex items-center gap-3 px-4 py-2 cursor-pointer"
          :class="index === activeIndex ? 'bg-gray-100' : 'bg-white'"
          @mousedown.prevent
          @mouseenter="activeIndex = index"
          @click="select(member)"
        >
          <div class="w-8 h-8 flex-none rounded-full bg-indigo-600 text-white text-xs font-medium flex items-center justify-center">
            {{ initials(member.name) }}
          </div>
          <div class="flex-1 min-w-0">
            <div class="text-sm font-medium text-gray-900 truncate">{{ member.name }}</div>
            <div class="text-xs text-gray-500 truncate">
              {{ [member.member_number, member.email].filter(Boolean).join(' · ') }}
            </div>
          </div>
          <span
            v-if="hasQuery"
            class="flex-none text-xs font-medium px-2 py-0.5 rounded-full"
            :class="getStatusBadgeClass(member.status)"
          >{{ getStatusText(member.status) }}</span>
          <span v-else-if="member.viewedAt" class="flex-none text-xs text-gray-400 whitespace-nowrap">
            {{ formatViewedAt(member.viewedAt) }}
          </span>
        </li>
      </ul>

      <div v-if="!loading && rows.length === 0" class="px-4 py-4 text-sm text-gray-500">
        {{ hasQuery
          ? 'Keine Mitglieder gefunden. Prüfen Sie die Schreibweise oder suchen Sie nach der Mitgliedsnummer.'
          : 'Noch keine Mitglieder angesehen.' }}
      </div>

      <div class="hidden lg:flex mt-1 px-4 pt-2 pb-0.5 border-t border-gray-100 gap-3 text-xs text-gray-400">
        <span>↑↓ auswählen</span>
        <span>↵ öffnen</span>
        <span>Esc schließen</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onBeforeUnmount, useId } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'
import { Search, X, Loader2 } from '@lucide/vue'
import { getStatusBadgeClass, getStatusText } from '@/utils/memberStatus'
import { formatViewedAt } from '@/utils/memberSearchHistory'
import { useMemberSearchHistory } from '@/composables/useMemberSearchHistory'

const DEBOUNCE_MS = 200

const history = useMemberSearchHistory()

const listId = `member-search-${useId()}`
const input = ref(null)
const query = ref('')
const open = ref(false)
const loading = ref(false)
const results = ref([])
const recent = ref([])
const activeIndex = ref(0)

let debounceTimer = null
let abortController = null

const hasQuery = computed(() => query.value.trim() !== '')
const rows = computed(() => (hasQuery.value ? results.value : recent.value))

const optionId = index => `${listId}-option-${index}`

function initials(name) {
  return (name || '')
    .split(/\s+/)
    .filter(Boolean)
    .map(part => part[0])
    .join('')
    .slice(0, 2)
    .toUpperCase()
}

async function fetchMembers(params) {
  abortController?.abort()
  abortController = new AbortController()
  loading.value = true

  try {
    const { data } = await axios.get(route('members.quick-search'), {
      params,
      signal: abortController.signal,
    })
    return data.members ?? []
  } catch (error) {
    if (axios.isCancel(error)) throw error
    return []
  } finally {
    loading.value = false
  }
}

async function loadRecent() {
  const entries = history.read()
  if (entries.length === 0) {
    recent.value = []
    return
  }

  try {
    const members = await fetchMembers({ ids: entries.map(entry => entry.id) })
    const viewedAt = new Map(entries.map(entry => [entry.id, entry.viewedAt]))
    recent.value = members.map(member => ({ ...member, viewedAt: viewedAt.get(member.id) }))
  } catch {
    // Superseded by a newer request.
  }
}

async function search() {
  try {
    results.value = await fetchMembers({ q: query.value.trim() })
  } catch {
    // Superseded by a newer request.
  }
}

function openDropdown() {
  open.value = true
  activeIndex.value = 0
  if (!hasQuery.value) loadRecent()
}

function close() {
  open.value = false
  clearTimeout(debounceTimer)
}

function onBlur() {
  close()
}

function onInput() {
  open.value = true
  activeIndex.value = 0
  clearTimeout(debounceTimer)

  if (!hasQuery.value) {
    abortController?.abort()
    results.value = []
    loadRecent()
    return
  }

  loading.value = true
  debounceTimer = setTimeout(search, DEBOUNCE_MS)
}

function clear() {
  query.value = ''
  onInput()
  input.value?.focus()
}

function select(member) {
  history.remember(member.id)
  query.value = ''
  results.value = []
  close()
  input.value?.blur()
  router.visit(route('members.show', member.id))
}

function onKeydown(event) {
  if (event.key === 'ArrowDown') {
    event.preventDefault()
    if (!open.value) openDropdown()
    activeIndex.value = Math.min(activeIndex.value + 1, Math.max(rows.value.length - 1, 0))
  } else if (event.key === 'ArrowUp') {
    event.preventDefault()
    activeIndex.value = Math.max(activeIndex.value - 1, 0)
  } else if (event.key === 'Enter') {
    event.preventDefault()
    const member = rows.value[activeIndex.value]
    if (open.value && member && !loading.value) select(member)
  } else if (event.key === 'Escape') {
    query.value = ''
    close()
    input.value?.blur()
  }
}

function focus() {
  input.value?.focus({ preventScroll: true })
}

onBeforeUnmount(() => {
  clearTimeout(debounceTimer)
  abortController?.abort()
})

defineExpose({ focus })
</script>
