<template>
  <teleport to="body">
    <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="emit('close')">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6 space-y-4">
        <div class="flex items-center justify-between">
          <h3 class="font-semibold text-gray-900">Mitglied auf Sperrliste setzen</h3>
          <button @click="emit('close')" class="text-gray-400 hover:text-gray-600">
            <X class="w-5 h-5" />
          </button>
        </div>

        <p class="text-sm text-gray-600">
          <strong>{{ member.first_name }} {{ member.last_name }}</strong> wird gesperrt.
          Alle Identifikatoren (IBAN, Telefon, Adresse, Name) werden gehashed und
          bei zukünftigen Registrierungen abgeglichen.
        </p>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Grund *</label>
          <select v-model="form.reason" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            <option value="payment_failed">Zahlungsausfall</option>
            <option value="chargeback">Rückbuchung</option>
            <option value="fraud">Betrugsverdacht</option>
            <option value="manual">Manuell</option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Begründung * (min. 10 Zeichen)</label>
          <textarea
            v-model="form.notes"
            rows="3"
            placeholder="z.B. Mehrfache SEPA-Rücklastschriften, kein Kontakt möglich..."
            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
          />
          <p v-if="form.errors.notes" class="text-red-600 text-xs mt-1">{{ form.errors.notes }}</p>
        </div>

        <div class="flex gap-3 justify-end">
          <button @click="emit('close')" class="px-4 py-2 text-sm border border-gray-300 rounded-lg hover:bg-gray-50">
            Abbrechen
          </button>
          <button
            @click="submit"
            :disabled="form.processing || form.notes.length < 10"
            class="px-4 py-2 text-sm bg-red-600 text-white rounded-lg hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed"
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
import { X } from 'lucide-vue-next'

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
