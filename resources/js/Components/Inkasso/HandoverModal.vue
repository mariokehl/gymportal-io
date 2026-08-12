<template>
  <teleport to="body">
    <div
      class="fixed inset-0 bg-gray-500/75 flex items-center justify-center z-50 p-6 overflow-y-auto"
      @click.self="$emit('close')"
    >
      <div class="bg-white rounded-lg shadow-lg w-full max-w-2xl max-h-[90vh] flex flex-col">
        <div class="px-6 pt-6 pb-4 border-b border-gray-200">
          <h2 class="text-lg font-semibold text-gray-900">Zum Inkasso übergeben</h2>
          <p class="text-sm text-gray-500 mt-1">
            {{ member.first_name }} {{ member.last_name }} · {{ member.member_number }}
          </p>
        </div>

        <div class="p-6 overflow-y-auto space-y-5">
          <div>
            <div class="text-sm font-medium text-gray-700 mb-2.5">Mahngebühr bei Übergabe</div>
            <div class="flex flex-col gap-2.5">
              <RadioCard
                :selected="feeMode === 'level'"
                :title="`Gebühr aus Mahnstufe 3 (${formatCurrency(levelThreeFee)})`"
                description="Aus den Einstellungen dieser Organisation"
                @select="feeMode = 'level'"
              />
              <RadioCard
                :selected="feeMode === 'none'"
                title="Keine Mahngebühr"
                description="Nur Hauptforderung und Übergabepauschale"
                @select="feeMode = 'none'"
              />
            </div>
          </div>

          <div class="bg-amber-100 border border-amber-200 rounded-lg px-4 py-3.5 text-amber-700">
            <div class="font-semibold text-sm mb-1.5">Diese Aktion wirkt sofort</div>
            <div class="space-y-1 text-sm">
              <div>· Mitglied erhält Mahnstufe 4 (Inkasso) und eine Zugangssperre.</div>
              <div>· Die Mahnstufe kann während des Inkassos nicht verändert werden.</div>
              <div>· Je Mitglied wird eine Übergabepauschale von {{ formatCurrency(settings.handover_flat_fee) }} gebucht.</div>
            </div>
          </div>

          <p v-if="form.errors.inkasso" class="text-sm text-red-600">{{ form.errors.inkasso }}</p>
        </div>

        <div class="px-6 py-4 border-t border-gray-200 bg-gray-50 flex justify-end gap-2.5">
          <button type="button" class="btn-secondary" @click="$emit('close')">Abbrechen</button>
          <button type="button" :disabled="form.processing" class="btn-primary" @click="submit">
            {{ form.processing ? 'Wird übergeben …' : 'Übergeben' }}
          </button>
        </div>
      </div>
    </div>
  </teleport>
</template>

<script setup>
import { computed, ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import RadioCard from '@/Components/Inkasso/RadioCard.vue'
import { formatCurrency } from '@/utils/formatters'

const props = defineProps({
  member: { type: Object, required: true },
  settings: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['close', 'done'])

const feeMode = ref('level')

const levelThreeFee = computed(() => {
  const level = (props.settings.levels ?? []).find(entry => Number(entry.level) === 3)

  return Number(level?.fee ?? 0)
})

const form = useForm({ dunning_fee: null })

const submit = () => {
  form.dunning_fee = feeMode.value === 'level' ? levelThreeFee.value : 0
  form.post(route('members.inkasso.handover', props.member.id), {
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
