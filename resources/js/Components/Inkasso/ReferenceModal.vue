<template>
  <teleport to="body">
    <div
      class="fixed inset-0 bg-gray-500/75 flex items-center justify-center z-50 p-6"
      @click.self="$emit('close')"
    >
      <div class="bg-white rounded-lg shadow-lg w-full max-w-lg">
        <div class="px-6 pt-6 pb-4 border-b border-gray-200">
          <h2 class="text-lg font-semibold text-gray-900">Aktenzeichen bearbeiten</h2>
          <p class="text-sm text-gray-500 mt-1">Fälle mit demselben Aktenzeichen werden gruppiert.</p>
        </div>

        <div class="p-6">
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Aktenzeichen des Partners</label>
          <input
            v-model="form.partner_reference"
            type="text"
            maxlength="36"
            class="w-full px-3 py-2.5 border border-gray-300 rounded-md text-sm focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600"
          >
          <p class="text-xs text-gray-400 mt-1.5">Vom Inkassopartner je Übergabe bereitgestellt</p>
          <p v-if="form.errors.partner_reference" class="mt-1 text-sm text-red-600">
            {{ form.errors.partner_reference }}
          </p>
        </div>

        <div class="px-6 py-4 border-t border-gray-200 bg-gray-50 flex justify-end gap-2.5">
          <button type="button" class="btn-secondary" @click="$emit('close')">Abbrechen</button>
          <button type="button" :disabled="form.processing" class="btn-primary" @click="submit">
            Speichern
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

const form = useForm({
  partner_reference: props.collectionCase.partner_reference ?? '',
})

const submit = () => {
  form.put(route('inkasso.cases.reference.update', props.collectionCase.id), {
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
