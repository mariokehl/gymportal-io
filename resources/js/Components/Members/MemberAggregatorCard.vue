<template>
  <div class="flex flex-col gap-3">
    <h3 class="text-lg font-bold text-gray-900">Firmenfitness / Aggregator</h3>

    <div class="rounded-lg border border-gray-200 bg-white shadow-sm p-4 flex flex-col gap-4">
      <p class="text-sm text-gray-500">
        Tragen Sie die Account-ID des Anbieters ein. Das Mitglied wird beim nächsten Check-in automatisch verknüpft.
      </p>

      <!-- Stored account -->
      <div
        v-if="provider && !editing"
        class="flex flex-wrap items-center gap-x-3.5 gap-y-2 rounded-lg border border-gray-200 px-4 py-3.5"
      >
        <span
          class="w-10 h-10 rounded-lg flex items-center justify-center text-xs font-bold flex-none"
          :style="{ background: provider.bg, color: provider.fg }"
        >
          {{ provider.short }}
        </span>
        <div class="flex-1 min-w-0">
          <p class="text-sm font-semibold text-gray-900">{{ provider.name }}</p>
          <p class="text-sm text-gray-500 truncate">
            Account-ID <span class="font-mono text-gray-700">{{ config.aggregator_account_id }}</span>
          </p>
        </div>
        <span
          class="px-2.5 py-0.5 rounded-full text-xs font-medium"
          :class="isLinked ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700'"
        >
          {{ aggregatorStatusLabel(config.aggregator_status) }}
        </span>
        <div class="flex items-center gap-1 flex-none">
          <button
            type="button"
            title="Bearbeiten"
            aria-label="Aggregator bearbeiten"
            class="p-1.5 rounded-md text-indigo-600 hover:bg-indigo-50"
            @click="startEdit"
          >
            <Pencil class="w-4 h-4" />
          </button>
          <button
            type="button"
            title="Entfernen"
            aria-label="Aggregator entfernen"
            class="p-1.5 rounded-md text-red-600 hover:bg-red-50"
            @click="confirmingRemoval = true"
          >
            <Trash2 class="w-4 h-4" />
          </button>
        </div>
      </div>

      <!-- Form (no account yet, or editing) -->
      <form v-else class="flex flex-col gap-2" @submit.prevent="save">
        <div class="grid grid-cols-1 sm:grid-cols-[220px_1fr_auto] gap-3 sm:items-end">
          <label class="flex flex-col gap-1.5 text-sm font-medium text-gray-700">
            Anbieter
            <select
              v-model="form.aggregator"
              class="p-2 border border-gray-300 rounded-md bg-white text-gray-900 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
            >
              <option v-for="option in aggregators" :key="option.key" :value="option.key">
                {{ option.name }}
              </option>
            </select>
          </label>
          <label class="flex flex-col gap-1.5 text-sm font-medium text-gray-700">
            Account-ID
            <input
              v-model="form.aggregator_account_id"
              type="text"
              maxlength="100"
              autocomplete="off"
              placeholder="Account-ID des Anbieters"
              class="p-2 border border-gray-300 rounded-md font-mono text-gray-900 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
            >
          </label>
          <div class="flex gap-2">
            <button
              v-if="editing"
              type="button"
              class="px-4 py-2 rounded-md border border-gray-300 text-gray-700 text-sm hover:bg-gray-50"
              @click="cancelEdit"
            >
              Abbrechen
            </button>
            <button
              type="submit"
              :disabled="!canSave"
              class="px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-1.5"
            >
              <Loader2 v-if="form.processing" class="w-4 h-4 animate-spin" />
              Speichern
            </button>
          </div>
        </div>
        <p v-if="form.errors.aggregator || form.errors.aggregator_account_id" class="text-sm text-red-600">
          {{ form.errors.aggregator || form.errors.aggregator_account_id }}
        </p>
      </form>
    </div>

    <!-- Removal confirmation -->
    <div
      v-if="confirmingRemoval"
      class="fixed inset-0 z-50 flex items-center justify-center bg-gray-500/75 p-4"
      @click.self="confirmingRemoval = false"
    >
      <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-lg flex flex-col gap-4" role="dialog" aria-modal="true">
        <div class="flex gap-3.5">
          <span class="w-10 h-10 rounded-full bg-red-100 text-red-600 flex items-center justify-center flex-none">
            <AlertTriangle class="w-5 h-5" />
          </span>
          <div class="flex flex-col gap-1.5">
            <h4 class="text-lg font-semibold text-gray-900">Verknüpfung entfernen?</h4>
            <p class="text-sm text-gray-600 leading-relaxed">
              Die Account-ID <b class="font-mono">{{ config.aggregator_account_id }}</b> ({{ provider?.name }})
              wird von {{ memberName }} entfernt. Check-ins über {{ provider?.name }} werden diesem Mitglied
              danach nicht mehr zugeordnet.
            </p>
          </div>
        </div>
        <div class="flex justify-end gap-2">
          <button
            type="button"
            class="px-4 py-2 rounded-md border border-gray-300 text-gray-700 text-sm hover:bg-gray-50"
            @click="confirmingRemoval = false"
          >
            Abbrechen
          </button>
          <button
            type="button"
            :disabled="removing"
            class="px-4 py-2 rounded-md bg-red-600 text-white text-sm font-medium hover:bg-red-700 disabled:opacity-50 flex items-center gap-1.5"
            @click="remove"
          >
            <Loader2 v-if="removing" class="w-4 h-4 animate-spin" />
            Entfernen
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import { AlertTriangle, Loader2, Pencil, Trash2 } from 'lucide-vue-next'
import {
  AGGREGATOR_STATUS_LINKED, aggregators, aggregatorStatusLabel, findAggregator,
} from '@/utils/aggregators'

const props = defineProps({
  member: {
    type: Object,
    required: true,
  },
})

const config = computed(() => props.member.access_config ?? {})
const provider = computed(() => findAggregator(config.value.aggregator))
const isLinked = computed(() => config.value.aggregator_status === AGGREGATOR_STATUS_LINKED)
const memberName = computed(() => [props.member.first_name, props.member.last_name].filter(Boolean).join(' '))

const editing = ref(false)
const confirmingRemoval = ref(false)
const removing = ref(false)

const form = useForm({
  aggregator: config.value.aggregator || aggregators[0].key,
  aggregator_account_id: config.value.aggregator_account_id || '',
})

const canSave = computed(() => !form.processing && form.aggregator_account_id.trim() !== '')

const startEdit = () => {
  form.clearErrors()
  form.aggregator = config.value.aggregator || aggregators[0].key
  form.aggregator_account_id = config.value.aggregator_account_id || ''
  editing.value = true
}

const cancelEdit = () => {
  form.clearErrors()
  editing.value = false
}

const save = () => {
  if (!canSave.value) return

  form.put(route('members.access.aggregator.update', props.member.id), {
    preserveScroll: true,
    onSuccess: () => {
      editing.value = false
    },
  })
}

const remove = () => {
  removing.value = true

  router.delete(route('members.access.aggregator.destroy', props.member.id), {
    preserveScroll: true,
    onSuccess: () => {
      confirmingRemoval.value = false
      form.aggregator = aggregators[0].key
      form.aggregator_account_id = ''
    },
    onFinish: () => {
      removing.value = false
    },
  })
}
</script>
