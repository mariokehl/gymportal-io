<template>
  <teleport to="body">
    <div class="fixed inset-0 bg-gray-500/75 overflow-y-auto h-full w-full z-50" @click="emit('close')">
      <div class="relative top-20 mx-auto p-5 border border-gray-50 w-11/12 md:w-3/4 lg:w-1/3 shadow-lg rounded-md bg-white" @click.stop>
        <form @submit.prevent="submit">
          <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
            <div class="mb-4">
              <h3 class="text-lg font-medium text-gray-900">
                Mitgliedschaft stilllegen
              </h3>
              <p class="mt-2 text-sm text-gray-600">
                Die Mitgliedschaft wird für den angegebenen Zeitraum pausiert.
                Der Vertrag verlängert sich entsprechend.
              </p>
            </div>

            <div class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Pausierung beginnt am <span class="text-red-500">*</span>
                </label>
                <input
                  v-model="form.pause_start_date"
                  type="date"
                  :min="today"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
                  required
                />
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Pausierung endet am <span class="text-red-500">*</span>
                </label>
                <input
                  v-model="form.pause_end_date"
                  type="date"
                  :min="form.pause_start_date"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
                  required
                />
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Grund (optional)
                </label>
                <textarea
                  v-model="form.reason"
                  rows="3"
                  placeholder="z.B. Urlaub, Verletzung, etc."
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
                ></textarea>
              </div>
            </div>

            <div v-if="form.errors && Object.keys(form.errors).length > 0" class="mt-4 p-3 bg-red-50 rounded-md">
              <div class="text-sm text-red-800">
                <ul class="list-disc list-inside">
                  <li v-for="(error, field) in form.errors" :key="field">{{ error }}</li>
                </ul>
              </div>
            </div>
          </div>

          <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
            <button
              type="submit"
              :disabled="form.processing"
              class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-yellow-600 text-base font-medium text-white hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50"
            >
              {{ form.processing ? 'Wird pausiert...' : 'Mitgliedschaft pausieren' }}
            </button>
            <button
              type="button"
              @click="emit('close')"
              class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
            >
              Abbrechen
            </button>
          </div>
        </form>
      </div>
    </div>
  </teleport>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3'

const props = defineProps({
  memberId: { type: Number, required: true },
  membership: { type: Object, required: true },
})

const emit = defineEmits(['close'])

const today = new Date().toISOString().split('T')[0]

// Default to a one-month pause starting today.
const defaultEndDate = () => {
  const endDate = new Date()
  endDate.setMonth(endDate.getMonth() + 1)
  return endDate.toISOString().split('T')[0]
}

const form = useForm({
  pause_start_date: today,
  pause_end_date: defaultEndDate(),
  reason: '',
})

const submit = () => {
  form.put(route('members.memberships.pause', {
    member: props.memberId,
    membership: props.membership.id,
  }), {
    preserveScroll: true,
    onSuccess: () => emit('close'),
  })
}
</script>
