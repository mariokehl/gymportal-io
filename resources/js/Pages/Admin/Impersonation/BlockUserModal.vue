<template>
  <div
    class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 px-4"
    @click.self="emit('close')"
    @keydown.esc="emit('close')"
  >
    <div
      class="bg-surface rounded-xl shadow-xl w-full max-w-md p-6 space-y-4"
      role="dialog"
      aria-modal="true"
      aria-labelledby="block-user-title"
    >
      <div class="flex items-center justify-between">
        <h3 id="block-user-title" class="font-semibold text-ink">Benutzer blockieren</h3>
        <button @click="emit('close')" type="button" class="touch-target relative text-ink-muted hover:text-ink" aria-label="Schließen">
          <X :size="20" />
        </button>
      </div>

      <p class="text-sm text-ink-secondary">
        <strong>{{ user.first_name }} {{ user.last_name }}</strong> ({{ user.email }}) wird blockiert
        und beim nächsten Seitenaufruf abgemeldet. Eine Anmeldung ist danach nicht mehr möglich.
      </p>

      <div>
        <label for="block-user-reason" class="block text-sm font-medium text-ink-secondary mb-1">
          Grund (optional, wird dem Benutzer angezeigt)
        </label>
        <textarea
          id="block-user-reason"
          ref="field"
          v-model="form.reason"
          rows="3"
          maxlength="500"
          placeholder="z.B. Zahlungsrückstand, Verstoß gegen die AGB..."
          class="field w-full px-3 py-2"
        />
        <p v-if="form.errors.reason" class="text-danger text-xs mt-1">{{ form.errors.reason }}</p>
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
          :disabled="form.processing"
          class="px-4 py-2 text-sm bg-warning-solid text-white rounded-lg hover:bg-warning-solid-strong disabled:opacity-50 disabled:cursor-not-allowed"
        >
          Blockieren
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, useTemplateRef } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { X } from '@lucide/vue'

const props = defineProps({ user: Object })
const emit = defineEmits(['close'])

const form = useForm({ reason: '' })

const submit = () => {
  form.post(route('admin.users.block', { user: props.user.id }), {
    preserveScroll: true,
    onSuccess: () => emit('close'),
  })
}

const field = useTemplateRef('field')
onMounted(() => field.value?.focus())
</script>
