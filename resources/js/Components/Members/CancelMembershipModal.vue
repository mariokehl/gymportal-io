<template>
  <teleport to="body">
    <div class="fixed inset-0 bg-gray-500/75 overflow-y-auto h-full w-full z-50" @click="emit('close')">
      <div class="relative top-20 mx-auto p-5 border border-line-subtle w-11/12 md:w-3/4 lg:w-1/3 shadow-lg rounded-md bg-surface" @click.stop>
        <form @submit.prevent="submit">
          <div class="bg-surface px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
            <div class="mb-4">
              <h3 class="text-lg font-semibold text-ink">
                Mitgliedschaft kündigen
              </h3>
              <p class="mt-2 text-sm text-ink-secondary">
                Die Mitgliedschaft wird zum angegebenen Datum beendet.
              </p>
            </div>

            <div class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-ink-secondary mb-2">
                  Art der Kündigung <span class="text-danger">*</span>
                </label>
                <div class="space-y-2">
                  <label class="flex items-start">
                    <input
                      v-model="form.cancellation_type"
                      type="radio"
                      value="ordinary"
                      class="mt-0.5 border-line-strong text-brand"
                    />
                    <span class="ml-2 text-sm text-ink-secondary">
                      Ordentliche Kündigung
                      <span class="text-ink-muted">(Standard)</span>
                      <span class="block text-xs text-ink-muted">
                        Zum nächstmöglichen Termin unter Einhaltung der Kündigungsfrist.
                      </span>
                    </span>
                  </label>
                  <label class="flex items-start">
                    <input
                      v-model="form.cancellation_type"
                      type="radio"
                      value="extraordinary"
                      class="mt-0.5 border-line-strong text-danger"
                    />
                    <span class="ml-2 text-sm text-ink-secondary">
                      Außerordentliche Kündigung
                      <span class="block text-xs text-ink-muted">
                        Freies Kündigungsdatum, umgeht Mindestlaufzeit und Kündigungsfrist.
                      </span>
                    </span>
                  </label>
                </div>
              </div>

              <div>
                <label class="block text-sm font-medium text-ink-secondary mb-2">
                  Kündigungsdatum <span class="text-danger">*</span>
                </label>
                <input
                  v-model="form.cancellation_date"
                  type="date"
                  :min="isExtraordinary ? today : (form.min_cancellation_date || today)"
                  :readonly="!isExtraordinary"
                  :class="[
                    'field w-full px-3 py-2',
                    !isExtraordinary ? 'cursor-not-allowed' : '',
                  ]"
                  required
                />
                <p class="mt-1 text-sm text-ink-muted">
                  <template v-if="isExtraordinary">
                    Die Mitgliedschaft endet zu diesem Datum.
                  </template>
                  <template v-else>
                    Nächstmöglicher Termin. Für ein abweichendes Datum die außerordentliche Kündigung wählen.
                  </template>
                </p>
                <p v-if="membership?.membership_plan?.commitment_months" class="mt-1 text-sm text-warning">
                  <AlertCircle class="w-3 h-3 inline mr-1" />
                  Mindestlaufzeit: {{ membership.membership_plan?.commitment_months }} Monate ab {{ formatDate(membership.start_date) }}
                </p>
                <p v-if="membership?.membership_plan?.cancellation_period" class="mt-1 text-sm text-info">
                  <Clock class="w-3 h-3 inline mr-1" />
                  Kündigungsfrist: {{ membership.membership_plan?.formatted_cancellation_period }}
                </p>
              </div>

              <div>
                <label class="flex items-center">
                  <input
                    v-model="form.immediate"
                    type="checkbox"
                    class="rounded border-line-strong text-danger"
                  />
                  <span class="ml-2 text-sm text-ink-secondary">
                    Sofort kündigen (zum heutigen Tag)
                    <span v-if="membership?.membership_plan?.commitment_months" class="text-ink-muted">
                      - umgeht die Mindestlaufzeit
                    </span>
                  </span>
                </label>
              </div>

              <div>
                <label class="block text-sm font-medium text-ink-secondary mb-2">
                  Kündigungsgrund <span class="text-danger">*</span>
                </label>
                <select
                  v-model="form.cancellation_reason"
                  class="field w-full px-3 py-2"
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

                <div v-if="form.cancellation_reason === 'other'" class="mt-2">
                  <input
                    v-model="form.cancellation_reason_note"
                    type="text"
                    maxlength="255"
                    placeholder="z. B. Kündigung wurde erst nach der Frist gelesen"
                    class="field w-full px-3 py-2"
                  />
                  <p class="mt-1 text-sm text-ink-muted">
                    Optional. Wird beim Kündigungsgrund in Klammern vermerkt.
                  </p>
                </div>
              </div>

              <div>
                <label class="flex items-center">
                  <input
                    v-model="form.send_confirmation"
                    type="checkbox"
                    class="rounded border-line-strong text-brand"
                  />
                  <span class="ml-2 text-sm text-ink-secondary">
                    Bestätigungsmail an das Mitglied senden
                  </span>
                </label>
              </div>
            </div>

            <div class="mt-4 p-3 bg-warning-subtle rounded-md">
              <div class="flex items-start">
                <AlertCircle class="w-5 h-5 text-warning mr-2 mt-0.5" />
                <div class="text-sm text-warning-strong">
                  <p class="font-medium">Wichtiger Hinweis:</p>
                  <p class="mt-1">
                    Diese Aktion kann rückgängig gemacht werden, solange das Kündigungsdatum noch nicht erreicht wurde.
                  </p>
                </div>
              </div>
            </div>

            <div v-if="form.errors && Object.keys(form.errors).length > 0" class="mt-4 p-3 bg-danger-subtle rounded-md">
              <div class="text-sm text-danger-strong">
                <ul class="list-disc list-inside">
                  <li v-for="(error, field) in form.errors" :key="field">{{ error }}</li>
                </ul>
              </div>
            </div>
          </div>

          <div class="bg-surface-muted px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
            <button
              type="submit"
              :disabled="form.processing"
              class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-danger-solid text-base font-medium text-white hover:bg-danger-solid-strong sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50"
            >
              {{ form.processing ? 'Wird gekündigt...' : 'Mitgliedschaft kündigen' }}
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
import { computed, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { AlertCircle, Clock } from '@lucide/vue'
import { formatDate, formatDateForInput } from '@/utils/formatters'

const props = defineProps({
  memberId: { type: Number, required: true },
  membership: { type: Object, required: true },
})

const emit = defineEmits(['close'])

const today = new Date().toISOString().split('T')[0]

// Date an ordinary cancellation takes effect: the end of the term the member is
// still bound to. The backend resolves it and falls back to the notice period
// when the contract has no term end left.
const ordinaryCancellationDate = formatDateForInput(
  props.membership.next_possible_cancellation_date
    ?? props.membership.projected_end_date
    ?? props.membership.default_cancellation_date
)

const form = useForm({
  cancellation_type: 'ordinary',
  cancellation_date: ordinaryCancellationDate,
  cancellation_reason: '',
  cancellation_reason_note: '',
  send_confirmation: true,
  immediate: false,
  min_cancellation_date: formatDateForInput(props.membership.min_cancellation_date),
})

const isExtraordinary = computed(() => form.cancellation_type === 'extraordinary')

// The immediate-cancellation checkbox implies an extraordinary cancellation
// that takes effect today.
watch(() => form.immediate, (immediate) => {
  if (immediate) {
    form.cancellation_type = 'extraordinary'
    form.cancellation_date = today
  }
})

// Switching back to an ordinary cancellation restores the calculated date and
// locks the field again; the immediate flag cannot survive that switch.
// A confirmation mail is the norm for an ordinary cancellation, whereas an
// extraordinary one is usually agreed on separately — so the default follows
// the type and stays overridable afterwards.
watch(() => form.cancellation_type, (type) => {
  if (type === 'ordinary') {
    form.immediate = false
    form.cancellation_date = ordinaryCancellationDate
  }

  form.send_confirmation = type === 'ordinary'
})

// A note only belongs to "Sonstiges"; drop it when the reason changes.
watch(() => form.cancellation_reason, (reason) => {
  if (reason !== 'other') {
    form.cancellation_reason_note = ''
  }
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
