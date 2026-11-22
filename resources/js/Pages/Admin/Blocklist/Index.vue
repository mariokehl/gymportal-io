<template>
  <AppLayout title="Sperrliste">
    <template #header>
      Sperrliste
    </template>

    <div class="space-y-6">
      <div class="bg-surface rounded-lg shadow p-6">
        <div class="flex items-center justify-between mb-6">
          <div>
            <h2 class="text-lg font-semibold text-ink">Gesperrte Einträge</h2>
            <p class="text-sm text-ink-muted mt-1">
              Mitglieder und Personen, die von der Registrierung ausgeschlossen sind
            </p>
          </div>
          <button
            @click="showManual = true"
            class="inline-flex items-center gap-2 px-4 py-2 bg-danger-solid text-white rounded-lg text-sm font-medium hover:bg-danger-solid-strong transition-colors"
          >
            <Plus :size="16" />
            Manuell sperren
          </button>
        </div>

        <div class="overflow-hidden rounded-xl border border-line">
          <table class="w-full text-sm">
            <thead class="bg-surface-muted text-ink-muted uppercase text-xs">
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
            <tbody class="divide-y divide-line-subtle">
              <tr v-if="entries.data.length === 0">
                <td colspan="7" class="px-4 py-8 text-center text-ink-muted">
                  Keine Einträge in der Sperrliste.
                </td>
              </tr>
              <tr v-for="entry in entries.data" :key="entry.id" class="hover:bg-surface-muted">
                <td class="px-4 py-3">
                  <div v-if="entry.member" class="font-medium text-ink">
                    {{ entry.member.first_name }} {{ entry.member.last_name }}
                  </div>
                  <div v-else class="text-ink-muted italic">Manueller Eintrag</div>
                  <div class="text-ink-muted text-xs">
                    {{ entry.member?.member_number ? '#' + entry.member.member_number : 'ID #' + (entry.original_member_id ?? '—') }}
                  </div>
                </td>
                <td class="px-4 py-3">
                  <span class="px-2 py-0.5 rounded-full text-xs font-medium"
                    :class="{
                      'bg-danger-soft text-danger': ['chargeback','fraud'].includes(entry.reason),
                      'bg-orange-100 text-orange-700': entry.reason === 'payment_failed',
                      'bg-fill text-ink-secondary': entry.reason === 'manual',
                    }"
                  >
                    {{ reasonLabels[entry.reason] }}
                  </span>
                </td>
                <td class="px-4 py-3 text-ink-secondary">
                  {{ formatDate(entry.blocked_at) }}
                </td>
                <td class="px-4 py-3 text-ink-secondary">
                  {{ entry.blocked_until ? formatDate(entry.blocked_until) : 'Permanent' }}
                </td>
                <td class="px-4 py-3 text-ink-secondary">
                  {{ entry.blocked_by_user?.name ?? '—' }}
                </td>
                <td class="px-4 py-3">
                  <span class="flex items-center gap-1 text-xs font-medium"
                    :class="isActive(entry) ? 'text-danger' : 'text-ink-muted'"
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
                    class="text-sm text-brand hover:text-brand-strong font-medium"
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
                ? 'bg-brand text-white border-brand'
                : 'border-line text-ink-secondary hover:bg-surface-muted'"
            />
            <span v-else v-html="link.label" class="px-3 py-1 rounded text-sm border border-line-subtle text-ink-disabled" />
          </template>
        </div>
      </div>

      <!-- Verdächtige Registrierungen (Audit-Log) -->
      <div v-if="fraudChecks.length > 0" class="bg-surface rounded-lg shadow p-6">
        <h2 class="text-lg font-semibold text-ink mb-4">Letzte Fraud-Checks</h2>
        <div class="overflow-hidden rounded-xl border border-line">
          <table class="w-full text-sm">
            <thead class="bg-surface-muted text-ink-muted uppercase text-xs">
              <tr>
                <th class="px-4 py-3 text-left">E-Mail</th>
                <th class="px-4 py-3 text-left">Score</th>
                <th class="px-4 py-3 text-left">Aktion</th>
                <th class="px-4 py-3 text-left">Treffer</th>
                <th class="px-4 py-3 text-left">Zeitpunkt</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-line-subtle">
              <tr v-for="check in fraudChecks" :key="check.id" class="hover:bg-surface-muted">
                <td class="px-4 py-3 text-ink">{{ check.email }}</td>
                <td class="px-4 py-3">
                  <span class="font-mono font-medium" :class="{
                    'text-danger': check.fraud_score >= 80,
                    'text-orange-500': check.fraud_score >= 40 && check.fraud_score < 80,
                    'text-success': check.fraud_score < 40,
                  }">
                    {{ check.fraud_score }}/100
                  </span>
                </td>
                <td class="px-4 py-3">
                  <span class="px-2 py-0.5 rounded-full text-xs font-medium" :class="{
                    'bg-danger-soft text-danger': check.action === 'blocked',
                    'bg-orange-100 text-orange-700': check.action === 'flagged',
                    'bg-success-soft text-success': check.action === 'allowed',
                  }">
                    {{ actionLabels[check.action] }}
                  </span>
                </td>
                <td class="px-4 py-3 text-ink-secondary text-xs">
                  {{ formatMatchedFields(check.matched_fields) }}
                </td>
                <td class="px-4 py-3 text-ink-secondary">
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
import { ShieldX, ShieldCheck, Plus } from '@lucide/vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import AppShell from '@/Layouts/AppShell.vue'
import UnblockModal from './UnblockModal.vue'
import ManualBlockModal from './ManualBlockModal.vue'

defineOptions({ layout: AppShell })

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
