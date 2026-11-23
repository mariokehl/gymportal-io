<template>
  <teleport to="body">
    <div class="fixed inset-0 bg-gray-500/75 overflow-y-auto h-full w-full z-50" @click="emit('close')">
      <div
        class="relative top-20 mx-auto p-5 border border-line-subtle w-11/12 md:w-3/4 lg:w-1/3 shadow-lg rounded-md bg-surface"
        @click.stop
      >
        <form @submit.prevent="submit">
          <div class="bg-surface px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
            <div class="mb-4">
              <h3 class="text-lg font-semibold text-ink">
                Add-on kündigen
              </h3>
              <p class="mt-2 text-sm text-ink-secondary">
                „{{ addon.name }}“ läuft bis zum angegebenen Datum und wird danach nicht mehr abgerechnet.
              </p>
            </div>

            <div>
              <label for="addon-cancellation-date" class="block text-sm font-medium text-ink-secondary mb-2">
                Kündigungsdatum <span class="text-danger">*</span>
              </label>
              <input
                id="addon-cancellation-date"
                v-model="form.cancellation_effective_at"
                type="date"
                :min="today"
                required
                class="field w-full px-3 py-2"
              />
              <p class="mt-1 text-sm text-ink-muted">
                Vorbelegt ist das Ende des aktuellen Monats. Sie können ein abweichendes Datum wählen;
                eine Kündigungsfrist wird nicht geprüft.
              </p>
            </div>

            <div class="mt-4 p-3 bg-warning-subtle rounded-md">
              <div class="flex items-start">
                <AlertCircle class="w-5 h-5 text-warning mr-2 mt-0.5 flex-none" />
                <div class="text-sm text-warning-strong">
                  <p class="font-medium">Wichtiger Hinweis:</p>
                  <p class="mt-1">
                    Alle vorgemerkten Zahlungen für dieses Add-on nach dem Kündigungsdatum werden storniert.
                    Bereits ausgeführte Zahlungen bleiben bestehen.
                  </p>
                </div>
              </div>
            </div>

            <div v-if="Object.keys(form.errors).length > 0" class="mt-4 p-3 bg-danger-subtle rounded-md">
              <ul class="list-disc list-inside text-sm text-danger-strong">
                <li v-for="(error, field) in form.errors" :key="field">{{ error }}</li>
              </ul>
            </div>
          </div>

          <div class="bg-surface-muted px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
            <button
              type="submit"
              :disabled="form.processing"
              class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-danger-solid text-base font-medium text-white hover:bg-danger-solid-strong sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50"
            >
              {{ form.processing ? 'Wird gekündigt...' : 'Add-on kündigen' }}
            </button>
            <button
              type="button"
              @click="emit('close')"
              class="mt-3 w-full inline-flex justify-center rounded-md border border-line-strong shadow-sm px-4 py-2 bg-surface text-base font-medium text-ink-secondary hover:bg-surface-muted sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
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
import { AlertCircle } from '@lucide/vue'
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
