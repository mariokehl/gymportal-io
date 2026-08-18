<template>
  <teleport to="body">
    <div class="fixed inset-0 bg-gray-500/75 overflow-y-auto h-full w-full z-50" @click="emit('close')">
      <div class="relative top-20 mx-auto p-5 border border-gray-50 w-11/12 md:w-3/4 lg:w-1/3 shadow-lg rounded-md bg-white" @click.stop>
        <form @submit.prevent="submit">
          <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
            <div class="mb-4">
              <h3 class="text-lg font-medium text-gray-900">
                Mitgliedschaft kündigen
              </h3>
              <p class="mt-2 text-sm text-gray-600">
                Die Mitgliedschaft wird zum angegebenen Datum beendet.
              </p>
            </div>

            <div class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Kündigungsdatum <span class="text-red-500">*</span>
                </label>
                <input
                  v-model="form.cancellation_date"
                  type="date"
                  :min="form.min_cancellation_date || today"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
                  required
                />
                <p class="mt-1 text-sm text-gray-500">
                  Die Mitgliedschaft endet zu diesem Datum.
                </p>
                <p v-if="membership?.membership_plan?.commitment_months" class="mt-1 text-sm text-yellow-600">
                  <AlertCircle class="w-3 h-3 inline mr-1" />
                  Mindestlaufzeit: {{ membership.membership_plan?.commitment_months }} Monate ab {{ formatDate(membership.start_date) }}
                </p>
                <p v-if="membership?.membership_plan?.cancellation_period" class="mt-1 text-sm text-blue-600">
                  <Clock class="w-3 h-3 inline mr-1" />
                  Kündigungsfrist: {{ membership.membership_plan?.formatted_cancellation_period }}
                </p>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Kündigungsgrund <span class="text-red-500">*</span>
                </label>
                <select
                  v-model="form.cancellation_reason"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
                  required
                >
                  <option value="">Bitte wählen...</option>
                  <option value="move">Umzug</option>
                  <option value="financial">Finanzielle Gründe</option>
                  <option value="health">Gesundheitliche Gründe</option>
                  <option value="dissatisfied">Unzufriedenheit</option>
                  <option value="no_time">Zeitmangel</option>
                  <option value="other">Sonstiges</option>
                </select>
              </div>

              <div>
                <label class="flex items-center">
                  <input
                    v-model="form.immediate"
                    type="checkbox"
                    class="rounded border-gray-300 text-red-600 focus:ring-red-500"
                  />
                  <span class="ml-2 text-sm text-gray-700">
                    Sofort kündigen (außerordentliche Kündigung)
                    <span v-if="membership?.membership_plan?.commitment_months" class="text-gray-500">
                      - umgeht die Mindestlaufzeit
                    </span>
                  </span>
                </label>
              </div>
            </div>

            <div class="mt-4 p-3 bg-yellow-50 rounded-md">
              <div class="flex items-start">
                <AlertCircle class="w-5 h-5 text-yellow-600 mr-2 mt-0.5" />
                <div class="text-sm text-yellow-800">
                  <p class="font-medium">Wichtiger Hinweis:</p>
                  <p class="mt-1">
                    Diese Aktion kann rückgängig gemacht werden, solange das Kündigungsdatum noch nicht erreicht wurde.
                  </p>
                </div>
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
              class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50"
            >
              {{ form.processing ? 'Wird gekündigt...' : 'Mitgliedschaft kündigen' }}
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
import { AlertCircle, Clock } from 'lucide-vue-next'
import { formatDate, formatDateForInput } from '@/utils/formatters'

const props = defineProps({
  memberId: { type: Number, required: true },
  membership: { type: Object, required: true },
})

const emit = defineEmits(['close'])

const today = new Date().toISOString().split('T')[0]

const form = useForm({
  cancellation_date: formatDateForInput(props.membership.default_cancellation_date),
  cancellation_reason: '',
  immediate: false,
  min_cancellation_date: formatDateForInput(props.membership.min_cancellation_date),
})

const submit = () => {
  form.put(route('members.memberships.cancel', {
    member: props.memberId,
    membership: props.membership.id,
  }), {
    preserveScroll: true,
    onSuccess: () => emit('close'),
  })
}
</script>
