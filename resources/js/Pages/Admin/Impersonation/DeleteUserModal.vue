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
      aria-labelledby="delete-user-title"
    >
      <div class="flex items-center justify-between">
        <h3 id="delete-user-title" class="font-semibold text-ink">Benutzer löschen</h3>
        <button @click="emit('close')" type="button" class="touch-target relative text-ink-muted hover:text-ink" aria-label="Schließen">
          <X :size="20" />
        </button>
      </div>

      <div class="bg-danger-subtle border border-danger-line p-3 rounded-lg text-sm text-danger">
        <p v-if="user.owned_gyms_count > 0">
          Zuerst werden {{ user.owned_gyms_count === 1 ? 'das Studio' : `alle ${user.owned_gyms_count} Studios` }}
          dieses Benutzers inklusive aller Mitglieder, Verträge und Zahlungsdaten entfernt.
          Danach wird der Benutzer gelöscht.
        </p>
        <p v-else>
          Der Benutzer besitzt keine Studios und wird direkt gelöscht.
        </p>
        <p class="mt-1 font-medium">Diese Aktion kann nicht rückgängig gemacht werden.</p>
      </div>

      <div>
        <label for="delete-user-confirmation" class="block text-sm font-medium text-ink-secondary mb-1">
          Geben Sie zur Bestätigung <strong>{{ user.email }}</strong> ein
        </label>
        <input
          id="delete-user-confirmation"
          ref="field"
          v-model="form.confirmation"
          type="text"
          autocomplete="off"
          class="field w-full px-3 py-2"
        />
        <p v-if="form.errors.confirmation" class="text-danger text-xs mt-1">{{ form.errors.confirmation }}</p>
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
          :disabled="form.processing || form.confirmation !== user.email"
          class="px-4 py-2 text-sm bg-danger-solid text-white rounded-lg hover:bg-danger-solid-strong disabled:opacity-50 disabled:cursor-not-allowed"
        >
          Endgültig löschen
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

const form = useForm({ confirmation: '' })

const submit = () => {
  form.delete(route('admin.users.destroy', { user: props.user.id }), {
    preserveScroll: true,
    onSuccess: () => emit('close'),
  })
}

const field = useTemplateRef('field')
onMounted(() => field.value?.focus())
</script>
