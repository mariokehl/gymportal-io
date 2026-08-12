<template>
  <AppLayout title="Mahn- & Inkassoläufe">
    <div class="max-w-7xl mx-auto space-y-6">
      <!-- Partner header -->
      <div class="bg-white rounded-lg shadow-sm p-6 flex flex-wrap items-center justify-between gap-5">
        <div class="flex items-center gap-4">
          <div class="w-11 h-11 rounded-lg bg-gray-900 text-white flex items-center justify-center font-bold tracking-tight">
            DG
          </div>
          <div>
            <div class="flex items-center gap-2">
              <span class="text-lg font-semibold text-gray-900">DIAGONAL Inkasso</span>
              <span
                :class="settings.active
                  ? 'bg-green-100 text-green-700'
                  : 'bg-gray-100 text-gray-700'"
                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold"
              >
                {{ settings.active ? 'Aktiv' : 'Inaktiv' }}
              </span>
            </div>
            <div class="text-sm text-gray-500">
              <template v-if="settings.tenant_id">Mandanten-ID {{ settings.tenant_id }} · </template>
              <template v-if="settings.active">Schnittstelle verbunden</template>
              <template v-else>Kein Inkassopartner aktiv</template>
            </div>
          </div>
        </div>
        <Link :href="route('settings.index')" class="text-sm text-indigo-600 font-semibold hover:text-indigo-700">
          Einstellungen öffnen →
        </Link>
      </div>

      <!-- Statistics -->
      <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-6">
        <div class="bg-white rounded-lg shadow-sm p-6">
          <div class="text-sm text-gray-500">Bereit für Inkasso</div>
          <div class="text-2xl font-bold text-gray-900 mt-1.5">{{ eligibleMembers.length }} Mitglieder</div>
          <div class="text-xs text-gray-400">{{ formatCurrency(eligibleSum) }} offen</div>
        </div>
        <div class="bg-white rounded-lg shadow-sm p-6">
          <div class="text-sm text-gray-500">Im Inkasso</div>
          <div class="text-2xl font-bold text-indigo-600 mt-1.5">{{ statistics.in_collection_count }} Mitglieder</div>
          <div class="text-xs text-gray-400">{{ formatCurrency(statistics.in_collection_amount) }} übergeben</div>
        </div>
        <div class="bg-white rounded-lg shadow-sm p-6">
          <div class="text-sm text-gray-500">Rückläufe &amp; Ablehnungen</div>
          <div class="text-2xl font-bold text-red-600 mt-1.5">{{ statistics.rejected_count }} Akten</div>
        </div>
        <div class="bg-white rounded-lg shadow-sm p-6">
          <div class="text-sm text-gray-500">Realisiert {{ currentYear }}</div>
          <div class="text-2xl font-bold text-green-700 mt-1.5">{{ formatCurrency(statistics.recovered_amount) }}</div>
        </div>
      </div>

      <!-- Tabs + primary action -->
      <div class="flex flex-wrap items-center justify-between gap-4">
        <TabRail v-model="activeTab" :tabs="tabs" />
        <button
          type="button"
          :disabled="!settings.active || eligibleMembers.length === 0"
          class="inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors disabled:bg-gray-100 disabled:text-gray-400 disabled:cursor-not-allowed"
          @click="showWizard = true"
        >
          <Plus class="w-4 h-4" />
          Inkassolauf erstellen
        </button>
      </div>

      <!-- Hint when the partner is not configured yet -->
      <div v-if="!settings.active" class="flex gap-3 p-4 rounded-lg bg-indigo-50 border border-indigo-200 text-indigo-800">
        <Info class="w-5 h-5 flex-none text-indigo-400" />
        <div class="text-sm">
          <div class="font-semibold">Kein Inkassopartner aktiv</div>
          <div>
            Solange kein Partner aktiv ist, können keine Inkassofälle übertragen werden.
            <Link :href="route('settings.index')" class="font-semibold underline">Jetzt in den Einstellungen aktivieren</Link>.
          </div>
        </div>
      </div>

      <!-- Runs table -->
      <div v-if="activeTab === 'runs'" class="bg-white rounded-lg shadow-sm overflow-hidden">
        <div v-if="runs.length === 0" class="p-12 text-center text-gray-400 text-sm">
          Es wurden noch keine Inkassoläufe erstellt.
        </div>
        <div v-else class="overflow-x-auto">
          <table class="w-full">
            <thead class="bg-gray-50">
              <tr>
                <th class="text-left px-6 py-3 text-xs font-medium uppercase tracking-wider text-gray-500">Lauf</th>
                <th class="text-left px-3 py-3 text-xs font-medium uppercase tracking-wider text-gray-500">Übergabedatum</th>
                <th class="text-left px-3 py-3 text-xs font-medium uppercase tracking-wider text-gray-500">Partner</th>
                <th class="text-right px-3 py-3 text-xs font-medium uppercase tracking-wider text-gray-500">Mitglieder</th>
                <th class="text-right px-3 py-3 text-xs font-medium uppercase tracking-wider text-gray-500">Betrag</th>
                <th class="text-left px-6 py-3 text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="run in runs"
                :key="run.id"
                class="border-t border-gray-100 hover:bg-gray-50 cursor-pointer"
                @click="openRun(run)"
              >
                <td class="px-6 py-4 text-sm font-semibold text-indigo-600">{{ run.run_number }}</td>
                <td class="px-3 py-4 text-sm text-gray-700">{{ formatDate(run.handed_over_at) }}</td>
                <td class="px-3 py-4 text-sm text-gray-700">DIAGONAL Inkasso</td>
                <td class="px-3 py-4 text-sm text-gray-700 text-right">{{ run.member_count }}</td>
                <td class="px-3 py-4 text-sm font-semibold text-gray-900 text-right">{{ formatCurrency(run.total_amount) }}</td>
                <td class="px-6 py-4">
                  <StatusPill :label="run.status_text" :color="run.status_color" />
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Ready for collection -->
      <div v-else class="bg-white rounded-lg shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 text-sm text-gray-500">
          Mitglieder mit abgeschlossenem Mahnprozess. Ausgeschlossene Mitglieder werden im Inkassolauf nicht übergeben.
        </div>
        <div v-if="readyMembers.length === 0" class="p-12 text-center text-gray-400 text-sm">
          Aktuell ist kein Mitglied bereit für die Inkassoübergabe.
        </div>
        <div v-else class="overflow-x-auto">
          <table class="w-full">
            <thead class="bg-gray-50">
              <tr>
                <th class="text-left px-6 py-3 text-xs font-medium uppercase tracking-wider text-gray-500">Mitglied</th>
                <th class="text-left px-3 py-3 text-xs font-medium uppercase tracking-wider text-gray-500">Mahnstufe</th>
                <th class="text-right px-3 py-3 text-xs font-medium uppercase tracking-wider text-gray-500">Forderungen</th>
                <th class="text-right px-3 py-3 text-xs font-medium uppercase tracking-wider text-gray-500">Offen</th>
                <th class="text-left px-6 py-3 text-xs font-medium uppercase tracking-wider text-gray-500">Hinweis</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="member in readyMembers" :key="member.id" class="border-t border-gray-100">
                <td class="px-6 py-4">
                  <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-full bg-indigo-500 text-white flex items-center justify-center text-xs font-semibold">
                      {{ initials(member) }}
                    </div>
                    <div>
                      <Link
                        :href="route('members.show', member.id)"
                        class="text-sm font-medium text-gray-900 hover:text-indigo-600"
                      >
                        {{ member.first_name }} {{ member.last_name }}
                      </Link>
                      <div class="text-xs text-gray-400">{{ member.member_number }}</div>
                    </div>
                  </div>
                </td>
                <td class="px-3 py-4">
                  <StatusPill :label="`Stufe ${member.level}`" color="orange" />
                </td>
                <td class="px-3 py-4 text-sm text-gray-500 text-right">{{ member.claims }} Positionen</td>
                <td class="px-3 py-4 text-sm font-semibold text-gray-900 text-right">{{ formatCurrency(member.open_amount) }}</td>
                <td class="px-6 py-4">
                  <StatusPill v-if="member.block" :label="member.block" color="red" />
                  <span v-else class="text-sm text-gray-400">übergabefähig</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <CollectionRunWizard
      v-if="showWizard"
      :members="eligibleMembers"
      :blocked-members="blockedMembers"
      :settings="settings"
      @close="showWizard = false"
    />
  </AppLayout>
</template>

<script setup>
import { computed, ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { Plus, Info, ListChecks, UserCheck } from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import TabRail from '@/Components/TabRail.vue'
import StatusPill from '@/Components/Inkasso/StatusPill.vue'
import CollectionRunWizard from '@/Components/Inkasso/CollectionRunWizard.vue'
import { formatCurrency, formatDate } from '@/utils/formatters'

const props = defineProps({
  runs: { type: Array, default: () => [] },
  readyMembers: { type: Array, default: () => [] },
  statistics: { type: Object, default: () => ({}) },
  settings: { type: Object, default: () => ({}) },
})

const activeTab = ref('runs')
const showWizard = ref(false)

const tabs = [
  { id: 'runs', name: 'Inkassoläufe', icon: ListChecks },
  { id: 'ready', name: 'Bereit für Inkasso', icon: UserCheck },
]

// Members that may actually be handed over.
const eligibleMembers = computed(() => props.readyMembers.filter(member => !member.block))
const blockedMembers = computed(() => props.readyMembers.filter(member => member.block))

const eligibleSum = computed(() =>
  eligibleMembers.value.reduce((total, member) => total + Number(member.open_amount || 0), 0)
)

const currentYear = new Date().getFullYear()

const initials = member =>
  `${member.first_name?.[0] ?? ''}${member.last_name?.[0] ?? ''}`.toUpperCase()

const openRun = run => router.get(route('finances.inkasso.show', run.id))
</script>
