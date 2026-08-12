<template>
  <AppLayout :title="`Inkassolauf ${run.run_number}`">
    <div class="max-w-7xl mx-auto space-y-6">
      <Link
        :href="route('finances.inkasso.index')"
        class="inline-block text-sm text-indigo-600 font-semibold hover:text-indigo-700"
      >
        ← Zurück zu den Inkassoläufen
      </Link>

      <!-- Run header -->
      <div class="bg-white rounded-lg shadow-sm p-6 space-y-5">
        <div class="flex flex-wrap items-start justify-between gap-5">
          <div>
            <div class="flex items-center gap-2.5">
              <span class="text-xl font-semibold text-gray-900">Inkassolauf {{ run.run_number }}</span>
              <StatusPill :label="run.status_text" :color="run.status_color" />
            </div>
            <div class="text-sm text-gray-500 mt-1">
              Übergeben am {{ formatDate(run.handed_over_at) }} an DIAGONAL Inkasso
            </div>
          </div>
          <div class="flex flex-wrap gap-2.5 justify-end">
            <a
              :href="route('finances.inkasso.export', { run: run.id, format: 'xlsx' })"
              class="px-3.5 py-2.5 bg-white border border-gray-300 text-gray-700 rounded-md text-sm font-medium hover:bg-gray-50 transition-colors"
            >
              Als Excel exportieren
            </a>
            <a
              :href="route('finances.inkasso.export', { run: run.id, format: 'csv' })"
              class="px-3.5 py-2.5 bg-white border border-gray-300 text-gray-700 rounded-md text-sm font-medium hover:bg-gray-50 transition-colors"
            >
              Als CSV exportieren
            </a>
            <span v-if="run.status === 'cancelled'" class="self-center text-sm text-gray-400">
              Lauf wurde rückgängig gemacht
            </span>
            <button
              v-else
              type="button"
              class="px-3.5 py-2.5 bg-white border border-red-300 text-red-700 rounded-md text-sm font-medium hover:bg-red-50 transition-colors"
              @click="showUndo = true"
            >
              Inkassolauf rückgängig machen
            </button>
          </div>
        </div>

        <!-- Totals -->
        <div class="grid grid-cols-2 lg:grid-cols-5 gap-px bg-gray-200 border border-gray-200 rounded-lg overflow-hidden">
          <div class="bg-gray-50 p-4">
            <div class="text-xs uppercase tracking-wider text-gray-500">Mitglieder</div>
            <div class="text-xl font-bold text-gray-900 mt-1">{{ run.member_count }}</div>
          </div>
          <div class="bg-gray-50 p-4">
            <div class="text-xs uppercase tracking-wider text-gray-500">Hauptforderung</div>
            <div class="text-xl font-bold text-gray-900 mt-1">{{ formatCurrency(run.principal_amount) }}</div>
          </div>
          <div class="bg-gray-50 p-4">
            <div class="text-xs uppercase tracking-wider text-gray-500">Mahngebühren</div>
            <div class="text-xl font-bold text-gray-900 mt-1">{{ formatCurrency(run.dunning_amount) }}</div>
          </div>
          <div class="bg-gray-50 p-4">
            <div class="text-xs uppercase tracking-wider text-gray-500">Übergabepauschalen</div>
            <div class="text-xl font-bold text-gray-900 mt-1">{{ formatCurrency(run.flat_amount) }}</div>
          </div>
          <div class="bg-white p-4">
            <div class="text-xs uppercase tracking-wider text-gray-500">Gesamt übergeben</div>
            <div class="text-xl font-bold text-indigo-600 mt-1">{{ formatCurrency(run.total_amount) }}</div>
          </div>
        </div>

        <div
          v-if="run.status === 'cancelled'"
          class="bg-red-100 border border-red-200 rounded-lg px-4 py-3.5 text-red-800"
        >
          <div class="font-semibold text-sm mb-1.5">Lauf abgebrochen</div>
          <div class="space-y-1 text-sm">
            <div>· Alle Mitglieder dieses Laufs haben den Inkassostatus verlassen. Die Mahnstufen bleiben erhalten.</div>
            <div>· DIAGONAL wurde über die Einstellung der Inkassotätigkeiten informiert.</div>
          </div>
        </div>
      </div>

      <!-- Transferred members -->
      <div class="bg-white rounded-lg shadow-sm overflow-hidden">
        <div class="px-6 pt-5 pb-3.5 text-lg font-semibold text-gray-900">Übergebene Mitglieder</div>
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead class="bg-gray-50">
              <tr>
                <th class="text-left px-6 py-3 text-xs font-medium uppercase tracking-wider text-gray-500">Mitglied</th>
                <th class="text-left px-3 py-3 text-xs font-medium uppercase tracking-wider text-gray-500">Aktenzeichen</th>
                <th class="text-right px-3 py-3 text-xs font-medium uppercase tracking-wider text-gray-500">Übergeben</th>
                <th class="text-right px-3 py-3 text-xs font-medium uppercase tracking-wider text-gray-500">Gezahlt</th>
                <th class="text-right px-3 py-3 text-xs font-medium uppercase tracking-wider text-gray-500">Offen</th>
                <th class="text-left px-6 py-3 text-xs font-medium uppercase tracking-wider text-gray-500">Fall-Status</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="entry in cases"
                :key="entry.id"
                class="border-t border-gray-100 hover:bg-gray-50 cursor-pointer"
                @click="openMember(entry)"
              >
                <td class="px-6 py-4">
                  <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-full bg-indigo-500 text-white flex items-center justify-center text-xs font-semibold">
                      {{ initials(entry.member) }}
                    </div>
                    <div>
                      <div class="text-sm font-medium text-gray-900">
                        {{ entry.member.first_name }} {{ entry.member.last_name }}
                      </div>
                      <div class="text-xs text-gray-400">{{ entry.member.member_number }}</div>
                    </div>
                  </div>
                </td>
                <td class="px-3 py-4 font-mono text-xs text-gray-700">{{ entry.partner_reference || '—' }}</td>
                <td class="px-3 py-4 text-sm text-gray-700 text-right">{{ formatCurrency(entry.total_amount) }}</td>
                <td class="px-3 py-4 text-sm text-green-700 text-right">{{ formatCurrency(entry.paid_amount) }}</td>
                <td class="px-3 py-4 text-sm font-semibold text-gray-900 text-right">{{ formatCurrency(entry.open_amount) }}</td>
                <td class="px-6 py-4">
                  <StatusPill :label="entry.status_text" :color="entry.status_color" />
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Undo confirmation -->
    <teleport to="body">
      <div
        v-if="showUndo"
        class="fixed inset-0 bg-gray-500/75 flex items-center justify-center z-50 p-6 overflow-y-auto"
        @click.self="showUndo = false"
      >
        <div class="bg-white rounded-lg shadow-lg w-full max-w-xl">
          <div class="px-6 pt-6 pb-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Inkassolauf rückgängig machen</h2>
            <p class="text-sm text-gray-500 mt-1">Lauf {{ run.run_number }} · DIAGONAL Inkasso</p>
          </div>
          <div class="p-6 space-y-4">
            <div class="bg-red-100 border border-red-200 rounded-lg px-4 py-3.5 text-red-800">
              <div class="font-semibold text-sm mb-1.5">Alle Mitglieder dieses Laufs verlassen den Inkassostatus</div>
              <div class="space-y-1 text-sm">
                <div>· Alle offenen Inkassofälle werden im aktuellen Status geschlossen.</div>
                <div>· Mitglieder werden wieder „Bereit für Inkasso“ – die Mahnstufe bleibt erhalten.</div>
                <div>· Bereits verbuchte Zahlungen inkl. Teilzahlungen bleiben erhalten.</div>
                <div>· Der Lauf-Status wechselt auf ABGEBROCHEN.</div>
              </div>
            </div>
            <p class="text-sm font-semibold text-gray-900">
              Wichtig: Es ist gesetzlich vorgeschrieben, DIAGONAL darüber zu informieren, die
              Inkassotätigkeiten für die betroffenen Mitglieder einzustellen.
            </p>
          </div>
          <div class="px-6 py-4 border-t border-gray-200 bg-gray-50 flex justify-end gap-2.5">
            <button
              type="button"
              class="px-4 py-2.5 bg-white border border-gray-300 text-gray-700 rounded-md text-sm font-medium hover:bg-gray-50 transition-colors"
              @click="showUndo = false"
            >
              Abbrechen
            </button>
            <button
              type="button"
              :disabled="undoForm.processing"
              class="px-4 py-2.5 bg-red-600 text-white rounded-md text-sm font-medium hover:bg-red-700 transition-colors disabled:opacity-60"
              @click="undo"
            >
              Bestätigen
            </button>
          </div>
        </div>
      </div>
    </teleport>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import StatusPill from '@/Components/Inkasso/StatusPill.vue'
import { formatCurrency, formatDate } from '@/utils/formatters'

const props = defineProps({
  run: { type: Object, required: true },
  cases: { type: Array, default: () => [] },
  settings: { type: Object, default: () => ({}) },
})

const showUndo = ref(false)
const undoForm = useForm({})

const initials = member =>
  `${member?.first_name?.[0] ?? ''}${member?.last_name?.[0] ?? ''}`.toUpperCase()

const openMember = entry => {
  if (entry.member?.id) {
    router.get(route('members.show', entry.member.id), { tab: 'inkasso' })
  }
}

const undo = () => {
  undoForm.delete(route('finances.inkasso.undo', props.run.id), {
    onSuccess: () => { showUndo.value = false },
  })
}
</script>
