<template>
  <teleport to="body">
    <div
      class="fixed inset-0 bg-gray-500/75 flex items-center justify-center z-50 p-6 overflow-y-auto"
      @click.self="$emit('close')"
    >
      <div class="bg-white rounded-lg shadow-lg w-full max-w-xl max-h-[90vh] flex flex-col">
        <div class="px-6 pt-6 pb-4 border-b border-gray-200">
          <h2 class="text-lg font-semibold text-gray-900">Inkasso abgeschlossen</h2>
          <p class="text-sm text-gray-500 mt-1">Der Fall wird dauerhaft geschlossen.</p>
        </div>

        <div class="p-6 overflow-y-auto space-y-4">
          <p class="text-sm text-gray-700">
            Wähle diese Option, wenn der Inkassopartner die Bemühungen beendet hat – unabhängig davon,
            ob sie erfolgreich waren.
          </p>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Kommentar zur Akte</label>
            <textarea
              v-model="form.comment"
              rows="3"
              placeholder="z. B. Rückmeldung des Partners, Vereinbarung, Restbetrag"
              class="w-full px-3 py-2.5 border border-gray-300 rounded-md text-sm resize-y focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600"
            />
          </div>

          <div class="bg-amber-100 border border-amber-200 rounded-lg px-4 py-3.5 text-amber-700">
            <div class="font-semibold text-sm mb-1.5">Restforderungen</div>
            <div class="space-y-1 text-sm">
              <div v-if="writeOff">
                · Einstellung „Immer ausbuchen“: verbleibende offene Beträge werden ausgebucht.
              </div>
              <div v-else>
                · Einstellung „Nach Entscheidung des Inkassopartners“: Ausbuchung nur bei entsprechender Rückmeldung.
              </div>
              <div>· Diese Aktion kann nicht rückgängig gemacht werden.</div>
            </div>
          </div>
        </div>

        <div class="px-6 py-4 border-t border-gray-200 bg-gray-50 flex justify-end gap-2.5">
          <button type="button" class="btn-secondary" @click="$emit('close')">Abbrechen</button>
          <button type="button" :disabled="form.processing" class="btn-primary" @click="submit">
            {{ form.processing ? 'Schließt …' : 'Fall abschließen' }}
          </button>
        </div>
      </div>
    </div>
  </teleport>
</template>

<script setup>
import { computed } from 'vue'
import { useForm } from '@inertiajs/vue3'

const props = defineProps({
  collectionCase: { type: Object, required: true },
  settings: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['close', 'done'])

const form = useForm({ comment: '' })

const writeOff = computed(() =>
  (props.settings.residual_handling ?? 'always_write_off') === 'always_write_off'
)

const submit = () => {
  form.post(route('inkasso.cases.close', props.collectionCase.id), {
    preserveScroll: true,
    onSuccess: () => emit('done'),
  })
}
</script>

<style scoped>
@reference "tailwindcss";

.btn-secondary {
  @apply px-4 py-2.5 bg-white border border-gray-300 text-gray-700 rounded-md text-sm font-medium hover:bg-gray-50 transition-colors;
}

.btn-primary {
  @apply px-4 py-2.5 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors disabled:opacity-60;
}
</style>
