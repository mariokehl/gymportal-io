<template>
  <AppLayout title="Sperrliste">
    <template #header>
      Sperrliste
    </template>

    <div class="space-y-6">
      <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center justify-between mb-6">
          <div>
            <h2 class="text-lg font-semibold text-gray-900">Gesperrte Einträge</h2>
            <p class="text-sm text-gray-500 mt-1">
              Mitglieder und Personen, die von der Registrierung ausgeschlossen sind
            </p>
          </div>
          <button
            @click="showManual = true"
            class="inline-flex items-center gap-2 px-4 py-2 bg-red-600 text-white rounded-lg
                   text-sm font-medium hover:bg-red-700 transition-colors"
          >
            <Plus :size="16" />
            Manuell sperren
          </button>
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-200">
          <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 uppercase text-xs">
              <tr>
                <th class="px-4 py-3 text-left">Mitglied</th>
                <th class="px-4 py-3 text-left">Grund</th>
                <th class="px-4 py-3 text-left">Gesperrt am</th>
                <th class="px-4 py-3 text-left">Gesperrt bis</th>
                <th class="px-4 py-3 text-left">Gesperrt von</th>
                <th class="px-4 py-3 text-left">Status</th>
                <th class="px-4 py-3 text-left">Aktionen</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-if="entries.data.length === 0">
                <td colspan="7" class="px-4 py-8 text-center text-gray-400">
                  Keine Einträge in der Sperrliste.
                </td>
              </tr>
              <tr v-for="entry in entries.data" :key="entry.id" class="hover:bg-gray-50">
                <td class="px-4 py-3">
                  <div v-if="entry.member" class="font-medium text-gray-900">
                    {{ entry.member.first_name }} {{ entry.member.last_name }}
                  </div>
                  <div v-else class="text-gray-400 italic">Manueller Eintrag</div>
                  <div class="text-gray-400 text-xs">
                    {{ entry.member?.member_number ? '#' + entry.member.member_number : 'ID #' + (entry.original_member_id ?? '—') }}
                  </div>
                </td>
                <td class="px-4 py-3">
                  <span class="px-2 py-0.5 rounded-full text-xs font-medium"
                    :class="{
                      'bg-red-100 text-red-700': ['chargeback','fraud'].includes(entry.reason),
                      'bg-orange-100 text-orange-700': entry.reason === 'payment_failed',
                      'bg-gray-100 text-gray-600': entry.reason === 'manual',
                    }"
                  >
                    {{ reasonLabels[entry.reason] }}
                  </span>
                </td>
                <td class="px-4 py-3 text-gray-600">
                  {{ formatDate(entry.blocked_at) }}
                </td>
                <td class="px-4 py-3 text-gray-600">
                  {{ entry.blocked_until ? formatDate(entry.blocked_until) : 'Permanent' }}
                </td>
                <td class="px-4 py-3 text-gray-600">
                  {{ entry.blocked_by_user?.name ?? '—' }}
                </td>
                <td class="px-4 py-3">
                  <span class="flex items-center gap-1 text-xs font-medium"
                    :class="isActive(entry) ? 'text-red-600' : 'text-gray-400'"
                  >
                    <ShieldX v-if="isActive(entry)" :size="14" />
                    <ShieldCheck v-else :size="14" />
                    {{ isActive(entry) ? 'Aktiv' : 'Abgelaufen' }}
                  </span>
                </td>
                <td class="px-4 py-3">
                  <button
                    v-if="isActive(entry)"
                    @click="showUnblock = entry"
                    class="text-sm text-indigo-600 hover:text-indigo-800 font-medium"
                  >
                    Entsperren
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="entries.links.length > 3" class="flex justify-end gap-2 mt-4">
          <template v-for="link in entries.links" :key="link.label">
            <Link
              v-if="link.url"
              :href="link.url"
              v-html="link.label"
              class="px-3 py-1 rounded text-sm border"
              :class="link.active
                ? 'bg-indigo-600 text-white border-indigo-600'
                : 'border-gray-200 text-gray-600 hover:bg-gray-50'"
            />
            <span v-else v-html="link.label" class="px-3 py-1 rounded text-sm border border-gray-100 text-gray-300" />
          </template>
        </div>
      </div>

      <!-- Verdächtige Registrierungen (Audit-Log) -->
      <div v-if="fraudChecks.length > 0" class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Letzte Fraud-Checks</h2>
        <div class="overflow-hidden rounded-xl border border-gray-200">
          <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 uppercase text-xs">
              <tr>
                <th class="px-4 py-3 text-left">E-Mail</th>
                <th class="px-4 py-3 text-left">Score</th>
                <th class="px-4 py-3 text-left">Aktion</th>
                <th class="px-4 py-3 text-left">Treffer</th>
                <th class="px-4 py-3 text-left">Zeitpunkt</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="check in fraudChecks" :key="check.id" class="hover:bg-gray-50">
                <td class="px-4 py-3 text-gray-900">{{ check.email }}</td>
                <td class="px-4 py-3">
                  <span class="font-mono font-medium" :class="{
                    'text-red-600': check.fraud_score >= 80,
                    'text-orange-500': check.fraud_score >= 40 && check.fraud_score < 80,
                    'text-green-600': check.fraud_score < 40,
                  }">
                    {{ check.fraud_score }}/100
                  </span>
                </td>
                <td class="px-4 py-3">
                  <span class="px-2 py-0.5 rounded-full text-xs font-medium" :class="{
                    'bg-red-100 text-red-700': check.action === 'blocked',
                    'bg-orange-100 text-orange-700': check.action === 'flagged',
                    'bg-green-100 text-green-700': check.action === 'allowed',
                  }">
                    {{ actionLabels[check.action] }}
                  </span>
                </td>
                <td class="px-4 py-3 text-gray-600 text-xs">
                  {{ formatMatchedFields(check.matched_fields) }}
                </td>
                <td class="px-4 py-3 text-gray-600">
                  {{ formatDate(check.checked_at) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <UnblockModal
      v-if="showUnblock"
      :entry="showUnblock"
      @close="showUnblock = null"
    />

    <ManualBlockModal
      v-if="showManual"
      @close="showManual = false"
    />
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import { ShieldX, ShieldCheck, Plus } from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import UnblockModal from './UnblockModal.vue'
import ManualBlockModal from './ManualBlockModal.vue'

const props = defineProps({
  entries: Object,
  fraudChecks: Array,
})

const showUnblock = ref(null)
const showManual  = ref(false)

const reasonLabels = {
  payment_failed: 'Zahlungsausfall',
  chargeback:     'Rückbuchung',
  fraud:          'Betrugsverdacht',
  manual:         'Manuell',
}

const actionLabels = {
  allowed: 'Erlaubt',
  flagged: 'Verdächtig',
  blocked: 'Blockiert',
}

const isActive = (entry) => !entry.blocked_until || new Date(entry.blocked_until) > new Date()

const formatDate = (dateStr) => {
  if (!dateStr) return '—'
  return new Date(dateStr).toLocaleDateString('de-DE', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  })
}

const formatMatchedFields = (fields) => {
  if (!fields || typeof fields !== 'object') return '—'
  return Object.keys(fields)
    .filter(k => k !== '_combination_bonus')
    .join(', ')
}
</script>
