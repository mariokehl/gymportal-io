<template>
  <teleport to="body">
    <div
      class="fixed inset-0 bg-gray-500/75 flex items-center justify-center z-50 p-6 overflow-y-auto"
      @click.self="$emit('close')"
    >
      <div class="bg-white rounded-lg shadow-lg w-full max-w-3xl max-h-[90vh] flex flex-col">
        <!-- Header -->
        <div class="px-6 pt-6 pb-4 border-b border-gray-200">
          <h2 class="text-lg font-semibold text-gray-900">Inkassolauf erstellen</h2>
          <p class="text-sm text-gray-500 mt-1">Übergabe offener Forderungen an DIAGONAL Inkasso</p>
        </div>

        <!-- Body -->
        <div class="p-6 overflow-y-auto space-y-5">
          <!-- Step indicator -->
          <div class="flex gap-2">
            <div v-for="(label, index) in STEPS" :key="label" class="flex-1 flex flex-col gap-1.5">
              <div
                :class="index < step ? 'bg-indigo-600' : 'bg-gray-200'"
                class="h-1 rounded-full"
              />
              <div
                :class="[
                  index < step ? 'text-indigo-700' : 'text-gray-400',
                  index === step - 1 ? 'font-semibold' : 'font-normal',
                ]"
                class="text-xs"
              >
                {{ index + 1 }}. {{ label }}
              </div>
            </div>
          </div>

          <!-- Step 1: scope -->
          <template v-if="step === 1">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <div class="text-sm font-medium text-gray-700 mb-1.5">Inkassopartner</div>
                <input
                  value="DIAGONAL Inkasso"
                  disabled
                  class="w-full px-3 py-2.5 border border-gray-300 rounded-md text-sm bg-gray-50 text-gray-500"
                >
                <p class="text-xs text-gray-400 mt-1.5">Aktiver Partner für diese Organisation</p>
              </div>
              <div>
                <div class="text-sm font-medium text-gray-700 mb-1.5">Mindestbetrag</div>
                <input
                  :value="formatCurrency(settings.min_amount)"
                  disabled
                  class="w-full px-3 py-2.5 border border-gray-300 rounded-md text-sm bg-gray-50 text-gray-500"
                >
                <p class="text-xs text-gray-400 mt-1.5">Aus den Einstellungen übernommen.</p>
              </div>
            </div>

            <div
              v-if="blockedMembers.length"
              class="bg-amber-100 border border-amber-200 rounded-lg px-4 py-3.5 text-amber-700"
            >
              <div class="font-semibold text-sm mb-1.5">Ausgeschlossen in diesem Lauf</div>
              <div class="space-y-1">
                <div v-for="member in blockedMembers" :key="member.id" class="text-sm">
                  · {{ member.first_name }} {{ member.last_name }} — {{ member.block }}
                </div>
              </div>
            </div>
          </template>

          <!-- Step 2: preview and selection -->
          <template v-else-if="step === 2">
            <div class="border border-gray-200 rounded-lg overflow-hidden">
              <div class="grid grid-cols-[28px_1fr_90px_110px] gap-3 px-4 py-2.5 bg-gray-50 text-xs font-medium uppercase tracking-wider text-gray-500">
                <span />
                <span>Mitglied</span>
                <span>Stufe</span>
                <span class="text-right">Offen</span>
              </div>
              <div
                v-for="member in members"
                :key="member.id"
                :class="{ 'opacity-50': excluded[member.id] }"
                class="grid grid-cols-[28px_1fr_90px_110px] gap-3 px-4 py-3 border-t border-gray-100 items-center cursor-pointer"
                @click="toggle(member.id)"
              >
                <span
                  :class="excluded[member.id] ? 'bg-white border-gray-300' : 'bg-indigo-600 border-indigo-600 text-white'"
                  class="w-[18px] h-[18px] rounded border flex items-center justify-center text-xs"
                >
                  <Check v-if="!excluded[member.id]" class="w-3 h-3" />
                </span>
                <span class="text-sm">
                  <span class="font-medium text-gray-900">{{ member.first_name }} {{ member.last_name }}</span>
                  <span class="text-gray-400 ml-2 text-xs">{{ member.member_number }}</span>
                </span>
                <StatusPill :label="`Stufe ${member.level}`" color="orange" />
                <span class="text-right text-sm font-semibold text-gray-900">
                  {{ formatCurrency(member.open_amount) }}
                </span>
              </div>
            </div>

            <div class="grid grid-cols-3 gap-px bg-gray-200 border border-gray-200 rounded-lg overflow-hidden">
              <div class="bg-gray-50 p-3.5">
                <div class="text-xs uppercase tracking-wider text-gray-500">Mitglieder</div>
                <div class="text-lg font-bold text-gray-900 mt-1">{{ selected.length }}</div>
              </div>
              <div class="bg-gray-50 p-3.5">
                <div class="text-xs uppercase tracking-wider text-gray-500">Hauptforderung</div>
                <div class="text-lg font-bold text-gray-900 mt-1">{{ formatCurrency(principal) }}</div>
              </div>
              <div class="bg-gray-50 p-3.5">
                <div class="text-xs uppercase tracking-wider text-gray-500">Übergabepauschalen</div>
                <div class="text-lg font-bold text-gray-900 mt-1">{{ formatCurrency(flatTotal) }}</div>
              </div>
            </div>
          </template>

          <!-- Step 3: confirmation -->
          <template v-else>
            <div class="border border-gray-200 rounded-lg p-4">
              <div class="text-2xl font-bold text-gray-900">{{ formatCurrency(principal + flatTotal) }}</div>
              <div class="text-sm text-gray-500 mt-1">
                {{ selected.length }} Mitglieder · DIAGONAL Inkasso
              </div>
            </div>

            <div class="bg-amber-100 border border-amber-200 rounded-lg px-4 py-3.5 text-amber-700">
              <div class="font-semibold text-sm mb-1.5">Mit der Übergabe passiert Folgendes</div>
              <div class="space-y-1 text-sm">
                <div>· Die betroffenen Mitglieder erhalten Mahnstufe 4 (Inkasso) und eine Zugangssperre.</div>
                <div>· Offene Forderungen werden als „Inkasso“ markiert und im Mitgliederkonto storniert.</div>
                <div>· Je Mitglied wird eine Übergabepauschale von {{ formatCurrency(settings.handover_flat_fee) }} gebucht.</div>
                <div>· Die Mahnstufe kann während des Inkassos nicht verändert werden.</div>
              </div>
            </div>

            <p v-if="form.errors.member_ids" class="text-sm text-red-600">{{ form.errors.member_ids }}</p>
          </template>
        </div>

        <!-- Footer -->
        <div class="px-6 py-4 border-t border-gray-200 bg-gray-50 flex justify-end gap-2.5">
          <button
            type="button"
            class="px-4 py-2.5 bg-white border border-gray-300 text-gray-700 rounded-md text-sm font-medium hover:bg-gray-50 transition-colors"
            @click="step === 1 ? $emit('close') : step--"
          >
            {{ step === 1 ? 'Abbrechen' : 'Zurück' }}
          </button>
          <button
            v-if="step < 3"
            type="button"
            :disabled="step === 2 && selected.length === 0"
            class="px-4 py-2.5 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors disabled:bg-gray-100 disabled:text-gray-400 disabled:cursor-not-allowed"
            @click="step++"
          >
            Weiter
          </button>
          <button
            v-else
            type="button"
            :disabled="form.processing"
            class="px-4 py-2.5 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors disabled:opacity-60"
            @click="submit"
          >
            {{ form.processing ? 'Wird übergeben …' : 'Inkassolauf starten' }}
          </button>
        </div>
      </div>
    </div>
  </teleport>
</template>

<script setup>
import { computed, reactive, ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { Check } from 'lucide-vue-next'
import StatusPill from '@/Components/Inkasso/StatusPill.vue'
import { formatCurrency } from '@/utils/formatters'

const props = defineProps({
  members: { type: Array, default: () => [] },
  blockedMembers: { type: Array, default: () => [] },
  settings: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['close'])

const STEPS = ['Umfang', 'Vorschau', 'Übergabe']

const step = ref(1)
const excluded = reactive({})

const form = useForm({ member_ids: [] })

const toggle = memberId => {
  excluded[memberId] = !excluded[memberId]
}

const selected = computed(() => props.members.filter(member => !excluded[member.id]))

const principal = computed(() =>
  selected.value.reduce((total, member) => total + Number(member.open_amount || 0), 0)
)

const flatTotal = computed(() =>
  selected.value.length * Number(props.settings.handover_flat_fee || 0)
)

const submit = () => {
  form.member_ids = selected.value.map(member => member.id)
  form.post(route('finances.inkasso.store'), {
    onSuccess: () => emit('close'),
  })
}
</script>
