<template>
  <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="emit('close')">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6 space-y-4">
      <div class="flex items-center justify-between">
        <h3 class="font-semibold text-gray-900">Sperre aufheben</h3>
        <button @click="emit('close')" class="text-gray-400 hover:text-gray-600">
          <X :size="20" />
        </button>
      </div>

      <p class="text-sm text-gray-600">
        Mitglied <strong>{{ entry.member ? entry.member.first_name + ' ' + entry.member.last_name : 'Unbekannt' }}</strong> wird entsperrt.
        Bitte begründe die Entsperrung (wird im Audit-Log gespeichert).
      </p>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Begründung *</label>
        <textarea
          v-model="form.unblock_reason"
          rows="3"
          placeholder="z.B. Zahlung eingegangen, Missverständnis geklärt..."
          class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm
                 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
        />
        <p v-if="form.errors.unblock_reason" class="text-red-600 text-xs mt-1">
          {{ form.errors.unblock_reason }}
        </p>
      </div>

      <div class="flex gap-3 justify-end">
        <button
          @click="emit('close')"
          class="px-4 py-2 text-sm border border-gray-300 rounded-lg hover:bg-gray-50"
        >
          Abbrechen
        </button>
        <button
          @click="submit"
          :disabled="form.processing || form.unblock_reason.length < 10"
          class="px-4 py-2 text-sm bg-indigo-600 text-white rounded-lg hover:bg-indigo-700
                 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          Entsperren bestätigen
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3'
import { X } from 'lucide-vue-next'

const props = defineProps({ entry: Object })
const emit  = defineEmits(['close'])

const form = useForm({ unblock_reason: '' })

const submit = () => {
  form.post(route('blocklist.unblock', { entry: props.entry.id }), {
    onSuccess: () => emit('close'),
  })
}
</script>
