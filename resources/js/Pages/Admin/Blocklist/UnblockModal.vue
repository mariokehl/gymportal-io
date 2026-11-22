<template>
  <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="emit('close')">
    <div class="bg-surface rounded-xl shadow-xl w-full max-w-md p-6 space-y-4">
      <div class="flex items-center justify-between">
        <h3 class="font-semibold text-ink">Sperre aufheben</h3>
        <button @click="emit('close')" class="text-ink-muted hover:text-ink">
          <X :size="20" />
        </button>
      </div>

      <p class="text-sm text-ink-secondary">
        Mitglied <strong>{{ entry.member ? entry.member.first_name + ' ' + entry.member.last_name : 'Unbekannt' }}</strong> wird entsperrt.
        Bitte begründe die Entsperrung (wird im Audit-Log gespeichert).
      </p>

      <div>
        <label class="block text-sm font-medium text-ink-secondary mb-1">Begründung *</label>
        <textarea
          v-model="form.unblock_reason"
          rows="3"
          placeholder="z.B. Zahlung eingegangen, Missverständnis geklärt..."
          class="field w-full px-3 py-2"
        />
        <p v-if="form.errors.unblock_reason" class="text-danger text-xs mt-1">
          {{ form.errors.unblock_reason }}
        </p>
      </div>

      <div class="flex gap-3 justify-end">
        <button
          @click="emit('close')"
          class="px-4 py-2 text-sm border border-line-strong rounded-lg hover:bg-surface-muted"
        >
          Abbrechen
        </button>
        <button
          @click="submit"
          :disabled="form.processing || form.unblock_reason.length < 10"
          class="px-4 py-2 text-sm bg-brand text-white rounded-lg hover:bg-brand-strong disabled:opacity-50 disabled:cursor-not-allowed"
        >
          Entsperren bestätigen
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3'
import { X } from '@lucide/vue'

const props = defineProps({ entry: Object })
const emit  = defineEmits(['close'])

const form = useForm({ unblock_reason: '' })

const submit = () => {
  form.post(route('blocklist.unblock', { entry: props.entry.id }), {
    onSuccess: () => emit('close'),
  })
}
</script>
