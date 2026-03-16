<template>
  <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 overflow-y-auto py-8" @click.self="emit('close')">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-lg mx-4 p-6 space-y-4">
      <div class="flex items-center justify-between">
        <h3 class="font-semibold text-gray-900">Manuell zur Sperrliste hinzufügen</h3>
        <button @click="emit('close')" class="text-gray-400 hover:text-gray-600">
          <X :size="20" />
        </button>
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-medium text-gray-700 mb-1">Vorname</label>
          <input v-model="form.first_name" type="text" class="input-field" />
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-700 mb-1">Nachname *</label>
          <input v-model="form.last_name" type="text" class="input-field" />
          <p v-if="form.errors.last_name" class="text-red-600 text-xs mt-1">{{ form.errors.last_name }}</p>
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-700 mb-1">Geburtsdatum</label>
          <input v-model="form.birth_date" type="date" class="input-field" />
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-700 mb-1">Mobilnummer</label>
          <input v-model="form.phone" type="tel" placeholder="+49 151 ..." class="input-field" />
        </div>
        <div class="col-span-2">
          <label class="block text-xs font-medium text-gray-700 mb-1">IBAN</label>
          <input v-model="form.iban" type="text" placeholder="DE89 ..." class="input-field" />
        </div>
        <div class="col-span-2">
          <label class="block text-xs font-medium text-gray-700 mb-1">Adresse</label>
          <input v-model="form.address" type="text" class="input-field" />
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-700 mb-1">PLZ</label>
          <input v-model="form.postal_code" type="text" class="input-field" />
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-700 mb-1">Stadt</label>
          <input v-model="form.city" type="text" class="input-field" />
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-700 mb-1">Grund *</label>
          <select v-model="form.reason" class="input-field">
            <option value="manual">Manuell</option>
            <option value="payment_failed">Zahlungsausfall</option>
            <option value="chargeback">Rückbuchung</option>
            <option value="fraud">Betrug</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-700 mb-1">Gesperrt bis</label>
          <input v-model="form.blocked_until" type="date" class="input-field" />
          <p class="text-xs text-gray-400 mt-0.5">Leer = permanent</p>
        </div>
        <div class="col-span-2">
          <label class="block text-xs font-medium text-gray-700 mb-1">Begründung * (min. 10 Zeichen)</label>
          <textarea v-model="form.notes" rows="3" class="input-field"
            placeholder="z.B. Mehrfache Rückbuchungen, Kontakt verweigert..." />
          <p v-if="form.errors.notes" class="text-red-600 text-xs mt-1">{{ form.errors.notes }}</p>
        </div>
      </div>

      <div class="flex gap-3 justify-end pt-2">
        <button @click="emit('close')"
          class="px-4 py-2 text-sm border border-gray-300 rounded-lg hover:bg-gray-50">
          Abbrechen
        </button>
        <button @click="submit" :disabled="form.processing"
          class="px-4 py-2 text-sm bg-red-600 text-white rounded-lg hover:bg-red-700 disabled:opacity-50">
          Sperren
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3'
import { X } from 'lucide-vue-next'

const emit = defineEmits(['close'])

const form = useForm({
  first_name:    '',
  last_name:     '',
  birth_date:    '',
  iban:          '',
  phone:         '',
  address:       '',
  postal_code:   '',
  city:          '',
  reason:        'manual',
  notes:         '',
  blocked_until: '',
  member_id:     null,
})

const submit = () => {
  form.post(route('blocklist.store-manual'), {
    onSuccess: () => emit('close'),
  })
}
</script>

<style scoped>
@reference "tailwindcss";

.input-field {
  @apply w-full border border-gray-300 rounded-lg px-3 py-2 text-sm
         focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500;
}
</style>
