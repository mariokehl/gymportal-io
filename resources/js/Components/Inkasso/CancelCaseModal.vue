<template>
  <teleport to="body">
    <div
      class="fixed inset-0 bg-gray-500/75 flex items-center justify-center z-50 p-6 overflow-y-auto"
      @click.self="$emit('close')"
    >
      <div class="bg-white rounded-lg shadow-lg w-full max-w-xl max-h-[90vh] flex flex-col">
        <div class="px-6 pt-6 pb-4 border-b border-gray-200">
          <h2 class="text-lg font-semibold text-gray-900">Inkassofall stornieren</h2>
          <p class="text-sm text-gray-500 mt-1">
            Aktenzeichen {{ collectionCase.partner_reference || collectionCase.case_number }}
          </p>
        </div>

        <div class="p-6 overflow-y-auto space-y-4">
          <div class="bg-red-100 border border-red-200 rounded-lg px-4 py-3.5 text-red-800">
            <div class="font-semibold text-sm mb-1.5">Was passiert bei der Stornierung</div>
            <div class="space-y-1 text-sm">
              <div>· Das Inkasso wird vollständig gestoppt.</div>
              <div>· Die Forderungen werden zur erneuten Bearbeitung oder Übergabe freigegeben.</div>
              <div>· Bereits verbuchte Zahlungen bleiben im Mitgliederkonto erhalten.</div>
              <div>· Du bist verpflichtet, den Inkassopartner über die Stornierung zu informieren.</div>
            </div>
          </div>

          <p class="text-sm text-gray-700">
            Wird eine Akte innerhalb von 14 Tagen nach Übergabe zurückgezogen, ohne bearbeitet worden
            zu sein, entfällt die Vergütungspflicht des Partners.
          </p>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Kommentar zur Akte</label>
            <textarea
              v-model="form.comment"
              rows="2"
              class="w-full px-3 py-2.5 border border-gray-300 rounded-md text-sm resize-y focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600"
            />
          </div>
        </div>

        <div class="px-6 py-4 border-t border-gray-200 bg-gray-50 flex justify-end gap-2.5">
          <button type="button" class="btn-secondary" @click="$emit('close')">Abbrechen</button>
          <button type="button" :disabled="form.processing" class="btn-danger" @click="submit">
            {{ form.processing ? 'Storniert …' : 'Fall stornieren' }}
          </button>
        </div>
      </div>
    </div>
  </teleport>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3'

const props = defineProps({
  collectionCase: { type: Object, required: true },
})

const emit = defineEmits(['close', 'done'])

const form = useForm({ comment: '' })

const submit = () => {
  form.post(route('inkasso.cases.cancel', props.collectionCase.id), {
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

.btn-danger {
  @apply px-4 py-2.5 bg-red-600 text-white rounded-md text-sm font-medium hover:bg-red-700 transition-colors disabled:opacity-60;
}
</style>
