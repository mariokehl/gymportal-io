<template>
  <teleport to="body">
    <div class="fixed inset-0 bg-gray-500/75 overflow-y-auto h-full w-full z-50" @click="emit('close')">
      <div
        class="relative top-20 mx-auto p-5 border border-gray-50 w-11/12 md:w-3/4 lg:w-1/3 shadow-lg rounded-md bg-white"
        @click.stop
      >
        <form @submit.prevent="submit">
          <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
            <div class="mb-4">
              <h3 class="text-lg font-medium text-gray-900">
                Add-on kündigen
              </h3>
              <p class="mt-2 text-sm text-gray-600">
                „{{ addon.name }}“ läuft bis zum angegebenen Datum und wird danach nicht mehr abgerechnet.
              </p>
            </div>

            <div>
              <label for="addon-cancellation-date" class="block text-sm font-medium text-gray-700 mb-2">
                Kündigungsdatum <span class="text-red-500">*</span>
              </label>
              <input
                id="addon-cancellation-date"
                v-model="form.cancellation_effective_at"
                type="date"
                :min="today"
                required
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
              />
              <p class="mt-1 text-sm text-gray-500">
                Vorbelegt ist das Ende des aktuellen Monats. Sie können ein abweichendes Datum wählen;
                eine Kündigungsfrist wird nicht geprüft.
              </p>
            </div>

            <div class="mt-4 p-3 bg-yellow-50 rounded-md">
              <div class="flex items-start">
                <AlertCircle class="w-5 h-5 text-yellow-600 mr-2 mt-0.5 flex-none" />
                <div class="text-sm text-yellow-800">
                  <p class="font-medium">Wichtiger Hinweis:</p>
                  <p class="mt-1">
                    Alle vorgemerkten Zahlungen für dieses Add-on nach dem Kündigungsdatum werden storniert.
                    Bereits ausgeführte Zahlungen bleiben bestehen.
                  </p>
                </div>
              </div>
            </div>

            <div v-if="Object.keys(form.errors).length > 0" class="mt-4 p-3 bg-red-50 rounded-md">
              <ul class="list-disc list-inside text-sm text-red-800">
                <li v-for="(error, field) in form.errors" :key="field">{{ error }}</li>
              </ul>
            </div>
          </div>

          <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
            <button
              type="submit"
              :disabled="form.processing"
              class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50"
            >
              {{ form.processing ? 'Wird gekündigt...' : 'Add-on kündigen' }}
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
import { AlertCircle } from 'lucide-vue-next'
import { todayInDisplayTimezone } from '@/utils/formatters'
import { endOfCurrentMonth } from '@/utils/addons'

const props = defineProps({
  memberId: { type: Number, required: true },
  // A booked add-on as built by MemberAddons' bookedAddons list.
  addon: { type: Object, required: true },
})

const emit = defineEmits(['close'])

const today = todayInDisplayTimezone()

const form = useForm({
  cancellation_effective_at: endOfCurrentMonth(),
})

const submit = () => {
  form.put(route('members.memberships.addons.toggle-cancellation', {
    member: props.memberId,
    membership: props.addon.membershipId,
    addon: props.addon.addonId,
  }), {
    preserveScroll: true,
    onSuccess: () => emit('close'),
  })
}
</script>
