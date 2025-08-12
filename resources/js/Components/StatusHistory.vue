<!-- components/StatusHistory.vue - Angepasst an die neuen Datenstrukturen -->
<template>
  <div class="space-y-4">
    <div class="flex justify-between items-center">
      <h3 class="text-lg font-semibold text-gray-900">Status-Verlauf</h3>
      <div class="flex items-center gap-2">
        <span v-if="member.status_history" class="text-sm text-gray-500">
          {{ member.status_history.length }} Änderungen
        </span>
        <button
          v-if="member.status_history && member.status_history.length > 5"
          @click="showAllHistory = !showAllHistory"
          class="text-sm text-indigo-600 hover:text-indigo-800"
        >
          {{ showAllHistory ? 'Weniger anzeigen' : 'Alle anzeigen' }}
        </button>
      </div>
    </div>

    <div v-if="member.status_history && member.status_history.length > 0">
      <div class="flow-root">
        <ul class="-mb-8">
          <li
            v-for="(history, index) in displayedHistory"
            :key="history.id"
            class="relative pb-8"
          >
            <span
              v-if="index !== displayedHistory.length - 1"
              class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-gray-200"
            ></span>

            <div class="relative flex space-x-3">
              <div>
                <span :class="[
                  'h-8 w-8 rounded-full flex items-center justify-center ring-8 ring-white',
                  getHistoryIconColor(history.new_status)
                ]">
                  <component :is="getStatusIcon(history.new_status)" class="w-4 h-4 text-white" />
                </span>
              </div>

              <div class="flex-1 min-w-0">
                <div>
                  <!-- Hauptänderungstext -->
                  <div class="text-sm text-gray-900">
                    <span class="font-medium">
                      {{ history.changed_by_name }}
                    </span>
                    <span v-if="history.changed_by_details?.role" class="text-gray-500">
                      ({{ history.changed_by_details.role }})
                    </span>
                    hat den Status von
                    <span :class="getStatusBadgeClass(history.old_status)" class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full mx-1">
                      {{ history.old_status_text || getStatusText(history.old_status) }}
                    </span>
                    zu
                    <span :class="getStatusBadgeClass(history.new_status)" class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full mx-1">
                      {{ history.new_status_text || getStatusText(history.new_status) }}
                    </span>
                    geändert
                  </div>

                  <!-- Grund -->
                  <div v-if="history.reason" class="mt-1 text-sm text-gray-600">
                    <span class="font-medium">Grund:</span> {{ history.reason }}
                  </div>

                  <!-- Metadata Informationen -->
                  <div v-if="history.metadata && Object.keys(history.metadata).length > 0" class="mt-1">
                    <!-- Trigger Information -->
                    <div v-if="history.metadata.triggered_by" class="text-xs text-gray-500">
                      <span class="inline-flex items-center gap-1">
                        <Info class="w-3 h-3" />
                        {{ getTriggeredByText(history.metadata.triggered_by) }}
                      </span>
                    </div>

                    <!-- Betroffene Mitgliedschaften -->
                    <div v-if="history.metadata.activated_memberships" class="text-xs text-green-600">
                      <CheckCircle class="w-3 h-3 inline mr-1" />
                      {{ history.metadata.activated_memberships }} Mitgliedschaft(en) aktiviert
                    </div>
                    <div v-if="history.metadata.paused_memberships" class="text-xs text-yellow-600">
                      <Pause class="w-3 h-3 inline mr-1" />
                      {{ history.metadata.paused_memberships }} Mitgliedschaft(en) pausiert
                    </div>
                    <div v-if="history.metadata.reactivated_memberships" class="text-xs text-blue-600">
                      <PlayCircle class="w-3 h-3 inline mr-1" />
                      {{ history.metadata.reactivated_memberships }} Mitgliedschaft(en) reaktiviert
                    </div>
                  </div>

                  <!-- Zeitstempel und technische Details -->
                  <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-gray-500">
                    <time :datetime="history.created_at" class="flex items-center gap-1">
                      <Clock class="w-3 h-3" />
                      {{ formatDateTime(history.created_at) }}
                    </time>

                    <span v-if="history.metadata?.ip_address" class="flex items-center gap-1">
                      <Globe class="w-3 h-3" />
                      IP: {{ history.metadata.ip_address }}
                    </span>

                    <span v-if="history.metadata?.action_source" class="flex items-center gap-1">
                      <Tag class="w-3 h-3" />
                      {{ getActionSourceText(history.metadata.action_source) }}
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </li>
        </ul>
      </div>
    </div>

    <div v-else class="text-center py-8 bg-gray-50 rounded-lg">
      <History class="w-12 h-12 text-gray-400 mx-auto mb-3" />
      <p class="text-gray-500">Keine Status-Änderungen vorhanden</p>
      <p class="text-xs text-gray-400 mt-1">Änderungen werden hier automatisch protokolliert</p>
    </div>

    <!-- Statistiken -->
    <div v-if="statusInfo || statusStats" class="mt-6 space-y-4">
      <!-- Basis-Statistiken -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-gray-50 rounded-lg p-4">
          <div class="text-sm text-gray-500">Gesamte Änderungen</div>
          <div class="mt-1 text-2xl font-semibold text-gray-900">
            {{ statusStats?.total_changes || member.status_history?.length || 0 }}
          </div>
          <div class="mt-1 text-xs text-gray-500">
            {{ statusStats?.manual_changes || 0 }} manuell /
            {{ statusStats?.automatic_changes || 0 }} automatisch
          </div>
        </div>

        <div class="bg-gray-50 rounded-lg p-4">
          <div class="text-sm text-gray-500">Aktueller Status seit</div>
          <div class="mt-1 text-sm font-medium text-gray-900">
            {{ statusInfo?.days_since_last_change !== null
              ? `vor ${statusInfo.days_since_last_change} Tagen`
              : member.status_history?.[0]
                ? formatDate(member.status_history[0].created_at)
                : '-'
            }}
          </div>
          <div v-if="statusInfo?.current_status_reason" class="mt-1 text-xs text-gray-500">
            {{ statusInfo.current_status_reason }}
          </div>
        </div>

        <div class="bg-gray-50 rounded-lg p-4">
          <div class="text-sm text-gray-500">Aktivierungen</div>
          <div class="mt-1 text-sm font-medium text-gray-900">
            <div v-if="statusInfo?.first_activation_date">
              Erstmals: {{ statusInfo.first_activation_date }}
            </div>
            <div v-if="statusInfo?.last_activation_date && statusInfo.last_activation_date !== statusInfo.first_activation_date">
              Zuletzt: {{ statusInfo.last_activation_date }}
            </div>
            <div v-else-if="!statusInfo?.first_activation_date">
              Noch nicht aktiviert
            </div>
          </div>
        </div>
      </div>

      <!-- Änderungen nach Benutzer (wenn vorhanden) -->
      <div v-if="statusStats?.changes_by_user && statusStats.changes_by_user.length > 0" class="bg-gray-50 rounded-lg p-4">
        <div class="text-sm font-medium text-gray-700 mb-3">Änderungen nach Benutzer</div>
        <div class="space-y-2">
          <div
            v-for="userStat in statusStats.changes_by_user"
            :key="userStat.user_id"
            class="flex items-center justify-between text-sm"
          >
            <span class="text-gray-600">
              {{ userStat.user_name }}
              <span v-if="userStat.user_role" class="text-xs text-gray-500">({{ userStat.user_role }})</span>
            </span>
            <span class="font-medium text-gray-900">{{ userStat.count }} Änderungen</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import {
  History, Info, Clock, Globe, Tag,
  CheckCircle, Pause, PlayCircle
} from 'lucide-vue-next'

// Importiere die gemeinsamen Utilities
import {
  getStatusText,
  getStatusBadgeClass,
  getStatusIcon,
  formatDate,
  formatDateTime
} from '@/utils/memberStatus'

const props = defineProps({
  member: Object,
  statusInfo: Object,
  statusStats: Object
})

const showAllHistory = ref(false)

const displayedHistory = computed(() => {
  if (!props.member.status_history) return []
  return showAllHistory.value
    ? props.member.status_history
    : props.member.status_history.slice(0, 5)
})

// Spezifische Funktionen für diese Komponente
const getHistoryIconColor = (status) => {
  const colors = {
    'active': 'bg-green-500',
    'inactive': 'bg-gray-500',
    'paused': 'bg-yellow-500',
    'pending': 'bg-orange-500',
    'overdue': 'bg-red-500'
  }
  return colors[status] || 'bg-gray-500'
}

const getTriggeredByText = (trigger) => {
  const triggers = {
    'member_inactivation': 'Automatisch durch Mitgliedsinaktivierung',
    'payment_overdue': 'Automatisch durch überfällige Zahlung',
    'payment_resolved': 'Automatisch nach Zahlungseingang',
    'auto_activation': 'Automatische Aktivierung',
    'manual_update': 'Manuelle Änderung',
    'registration': 'Bei Registrierung',
    'system': 'System-Aktion'
  }
  return triggers[trigger] || trigger
}

const getActionSourceText = (source) => {
  const sources = {
    'manual_update': 'Manuell',
    'system': 'System',
    'auto_activation': 'Automatisch',
    'api': 'API',
    'import': 'Import'
  }
  return sources[source] || source
}
</script>
