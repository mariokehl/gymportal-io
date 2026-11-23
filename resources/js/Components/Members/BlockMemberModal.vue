<template>
  <teleport to="body">
    <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="emit('close')">
      <div class="bg-surface rounded-xl shadow-xl w-full max-w-md p-6 space-y-4">
        <div class="flex items-center justify-between">
          <h3 class="font-semibold text-ink">Mitglied auf Sperrliste setzen</h3>
          <button @click="emit('close')" class="text-ink-muted hover:text-ink">
            <X class="w-5 h-5" />
          </button>
        </div>

        <p class="text-sm text-ink-secondary">
          <strong>{{ member.first_name }} {{ member.last_name }}</strong> wird gesperrt.
          Alle Identifikatoren (IBAN, Telefon, Adresse, Name) werden gehashed und
          bei zukünftigen Registrierungen abgeglichen.
        </p>

        <div>
          <label for="block-member-modal-form-reason" class="block text-sm font-medium text-ink-secondary mb-1">Grund *</label>
          <select id="block-member-modal-form-reason" v-model="form.reason" class="field w-full px-3 py-2">
            <option value="payment_failed">Zahlungsausfall</option>
            <option value="chargeback">Rückbuchung</option>
            <option value="fraud">Betrugsverdacht</option>
            <option value="manual">Manuell</option>
          </select>
        </div>

        <div>
          <label for="block-member-modal-form-notes" class="block text-sm font-medium text-ink-secondary mb-1">Begründung * (min. 10 Zeichen)</label>
          <textarea id="block-member-modal-form-notes"
            v-model="form.notes"
            rows="3"
            placeholder="z.B. Mehrfache SEPA-Rücklastschriften, kein Kontakt möglich..."
            class="field w-full px-3 py-2"
          />
          <p v-if="form.errors.notes" class="text-danger text-xs mt-1">{{ form.errors.notes }}</p>
        </div>

        <div class="flex gap-3 justify-end">
          <button @click="emit('close')" class="px-4 py-2 text-sm border border-line-strong rounded-lg hover:bg-surface-muted">
            Abbrechen
          </button>
          <button
            @click="submit"
            :disabled="form.processing || form.notes.length < 10"
            class="px-4 py-2 text-sm bg-danger-solid text-white rounded-lg hover:bg-danger-solid-strong disabled:opacity-50 disabled:cursor-not-allowed"
          >
            Auf Sperrliste setzen
          </button>
        </div>
      </div>
    </div>
  </teleport>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3'
import { X } from '@lucide/vue'

const props = defineProps({
  member: { type: Object, required: true },
})

const emit = defineEmits(['close'])

const form = useForm({
  reason: 'payment_failed',
  notes: '',
})

const submit = () => {
  form.post(route('blocklist.block-member', { member: props.member.id }), {
    onSuccess: () => emit('close'),
  })
}
</script>
