<template>
  <AppLayout title="Mitglieder">
    <template #header>
      Mitglieder
    </template>

    <div class="space-y-6">
      <!-- Header mit Such- und Filterbereich -->
      <div class="bg-white rounded-lg shadow p-6">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
          <div class="flex-1 max-w-lg">
            <div class="relative">
              <Search class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4" />
              <input
                v-model="filters.search"
                type="text"
                placeholder="Mitglieder durchsuchen..."
                class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                @input="handleSearch"
              />
            </div>
          </div>

          <div class="flex items-center gap-3">
            <!-- Status Filter -->
            <select
              v-model="filters.status"
              class="p-2 border border-gray-300 rounded-md bg-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
              @change="handleFilter"
            >
              <option value="">Alle Status</option>
              <option value="active">Aktiv</option>
              <option value="inactive">Inaktiv</option>
              <option value="paused">Pausiert</option>
              <option value="pending">Ausstehend</option>
              <option value="overdue">Überfällig</option>
            </select>

            <!-- Neu anlegen Button -->
            <Link
              :href="route('members.create')"
              class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700 transition-colors"
            >
              <Plus class="w-4 h-4 mr-2" />
              Neues Mitglied
            </Link>
          </div>
        </div>
      </div>

      <!-- Mitglieder Tabelle -->
      <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100" @click="handleSort('name')">
                  <div class="flex items-center">
                    Name
                    <ArrowUpDown class="w-4 h-4 ml-1" />
                  </div>
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100" @click="handleSort('member_number')">
                  <div class="flex items-center">
                    Mitgliedsnummer
                    <ArrowUpDown class="w-4 h-4 ml-1" />
                  </div>
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Status
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100" @click="handleSort('last_check_in')">
                  <div class="flex items-center">
                    Letzter Besuch
                    <ArrowUpDown class="w-4 h-4 ml-1" />
                  </div>
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100" @click="handleSort('contract_end_date')">
                  <div class="flex items-center">
                    Vertragsende
                    <ArrowUpDown class="w-4 h-4 ml-1" />
                  </div>
                </th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Aktionen
                </th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-for="member in members.data" :key="member.id" class="hover:bg-gray-50">
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="flex items-center">
                    <div class="flex-shrink-0 h-10 w-10">
                      <img
                        v-if="member.profile_photo_path"
                        :src="member.profile_photo_path"
                        :alt="`${member.first_name} ${member.last_name}`"
                        class="h-10 w-10 rounded-full object-cover"
                      />
                      <div
                        v-else
                        class="h-10 w-10 rounded-full bg-indigo-500 flex items-center justify-center text-white font-semibold"
                      >
                        {{ getInitials(member.first_name, member.last_name) }}
                      </div>
                    </div>
                    <div class="ml-4">
                      <div class="text-sm font-medium text-gray-900">
                        {{ member.first_name }} {{ member.last_name }}
                      </div>
                      <div class="text-sm text-gray-500">
                        {{ member.email }}
                      </div>
                    </div>
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {{ member.member_number }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <MemberStatusBadge :status="member.status" />
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    {{ member.last_check_in ? formatDate(member.last_check_in.check_in_time) : 'Noch nie' }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  {{ member.contract_end_date ? formatDate(member.contract_end_date) : '-' }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                  <div class="flex items-center justify-end space-x-2">
                    <!-- View Button -->
                    <Link
                      :href="route('members.show', member.id)"
                      class="text-gray-700 hover:text-gray-900 p-1 rounded transition-colors"
                      title="Anzeigen"
                    >
                      <Eye class="w-4 h-4" />
                    </Link>

                    <!-- Edit Button -->
                    <Link
                      :href="route('members.show', member.id) + '?edit=true'"
                      class="text-indigo-600 hover:text-indigo-900 p-1 rounded transition-colors"
                      title="Bearbeiten"
                    >
                      <Edit class="w-4 h-4" />
                    </Link>

                    <!-- Delete Button mit bedingter Deaktivierung -->
                    <button
                      v-if="member.can_delete"
                      @click="confirmDelete(member)"
                      class="text-red-600 hover:text-red-900 p-1 rounded transition-colors"
                      title="Löschen"
                    >
                      <Trash2 class="w-4 h-4" />
                    </button>

                    <!-- Deaktivierter Delete Button mit Tooltip -->
                    <Tooltip v-else position="left">
                      <template #default>
                        <button
                          disabled
                          class="text-gray-300 cursor-not-allowed p-1 rounded opacity-50"
                          :title="member.delete_block_reason"
                        >
                          <Trash2 class="w-4 h-4" />
                        </button>
                      </template>

                      <template #content>
                        <div class="font-semibold mb-1 flex items-center">
                          <AlertCircle class="w-3 h-3 mr-1" />
                          Löschen nicht möglich
                        </div>
                        <div class="text-gray-300">
                          {{ member.delete_block_reason }}
                        </div>
                        <div
                          v-if="member.status !== 'inactive'"
                          class="mt-2 pt-2 border-t border-gray-700 text-gray-400"
                        >
                          Tipp: Mitglied muss erst inaktiviert werden
                        </div>
                      </template>
                    </Tooltip>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination Component -->
        <Pagination
          :data="members"
          item-label="Mitglieder"
          :is-loading="isProcessing"
          @navigate="handlePaginationEvent"
        />
      </div>

      <!-- Keine Ergebnisse -->
      <div v-if="members.data && members.data.length === 0" class="bg-white rounded-lg shadow p-12 text-center">
        <Users class="mx-auto h-12 w-12 text-gray-400" />
        <h3 class="mt-2 text-sm font-medium text-gray-900">Keine Mitglieder gefunden</h3>
        <p class="mt-1 text-sm text-gray-500">
          {{ filters.search || filters.status ? 'Keine Mitglieder entsprechen den aktuellen Filtern.' : 'Beginnen Sie mit dem Hinzufügen Ihres ersten Mitglieds.' }}
        </p>
        <div class="mt-6">
          <Link
            :href="route('members.create')"
            class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700"
          >
            <Plus class="w-4 h-4 mr-2" />
            Neues Mitglied
          </Link>
        </div>
      </div>
    </div>

    <!-- Erweitertes Lösch-Modal -->
    <div v-if="showDeleteModal" class="fixed inset-0 bg-gray-500/75 overflow-y-auto h-full w-full z-50">
      <div class="relative top-20 mx-auto p-5 border border-gray-50 w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3 text-center">
          <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100">
            <AlertTriangle class="h-6 w-6 text-red-600" />
          </div>
          <h3 class="text-lg font-medium text-gray-900 mt-2">Mitglied löschen</h3>

          <div class="mt-4 px-7 py-3">
            <!-- Mitgliedsinfo -->
            <div class="bg-gray-50 rounded-lg p-3 mb-4">
              <div class="font-medium text-gray-900">
                {{ memberToDelete?.first_name }} {{ memberToDelete?.last_name }}
              </div>
              <div class="text-sm text-gray-500">
                {{ memberToDelete?.member_number }} • {{ memberToDelete?.email }}
              </div>
            </div>

            <!-- Prüfungs-Checkliste -->
            <div class="text-left space-y-2 mb-4">
              <div class="flex items-center text-sm">
                <CheckCircle class="w-4 h-4 text-green-500 mr-2" />
                <span class="text-gray-600">Status: Inaktiv</span>
              </div>
              <div class="flex items-center text-sm">
                <CheckCircle class="w-4 h-4 text-green-500 mr-2" />
                <span class="text-gray-600">Keine aktiven Mitgliedschaften</span>
              </div>
              <div class="flex items-center text-sm">
                <CheckCircle class="w-4 h-4 text-green-500 mr-2" />
                <span class="text-gray-600">Keine offenen Zahlungen</span>
              </div>
            </div>

            <!-- Warnung -->
            <div class="bg-red-50 border border-red-200 rounded-md p-3">
              <p class="text-sm text-red-800">
                <strong>Achtung:</strong> Diese Aktion kann nicht rückgängig gemacht werden.
                Alle Daten des Mitglieds werden unwiderruflich gelöscht.
              </p>
            </div>
          </div>

          <div class="items-center px-4 py-3 space-y-2">
            <button
              @click="deleteMember"
              :disabled="isDeleting"
              class="px-4 py-2 bg-red-600 text-white text-base font-medium rounded-md w-full shadow-sm hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            >
              <span v-if="!isDeleting">Endgültig löschen</span>
              <span v-else class="flex items-center justify-center">
                <Loader2 class="w-4 h-4 mr-2 animate-spin" />
                Wird gelöscht...
              </span>
            </button>
            <button
              @click="closeDeleteModal"
              :disabled="isDeleting"
              class="px-4 py-2 bg-white text-gray-700 text-base font-medium rounded-md w-full shadow-sm border border-gray-300 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-500 disabled:opacity-50 transition-colors"
            >
              Abbrechen
            </button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import { debounce } from 'lodash'
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import MemberStatusBadge from '@/Components/MemberStatusBadge.vue'
import Tooltip from '@/Components/Tooltip.vue'
import {
  Users, Plus, Search, Edit, Trash2, Eye, AlertTriangle, AlertCircle, CheckCircle, Loader2, ArrowUpDown
} from 'lucide-vue-next'
import { formatDate } from '@/utils/formatters'

// Props
const props = defineProps({
  members: Object,
  filters: Object
})

// Reactive data
const filters = reactive({
  search: props.filters?.search || '',
  status: props.filters?.status || '',
  sortBy: props.filters?.sortBy || 'member_number',
  sortDirection: props.filters?.sortDirection || 'asc'
})

const showDeleteModal = ref(false)
const memberToDelete = ref(null)
const isDeleting = ref(false)
const isProcessing = ref(false)

// Methods
const handleSearch = debounce(() => {
  router.get(route('members.index'), filters, {
    preserveState: true,
    replace: true
  })
}, 300)

const handleFilter = () => {
  router.get(route('members.index'), filters, {
    preserveState: true,
    replace: true
  })
}

const handleSort = (column) => {
  if (filters.sortBy === column) {
    filters.sortDirection = filters.sortDirection === 'asc' ? 'desc' : 'asc'
  } else {
    filters.sortBy = column
    filters.sortDirection = 'asc'
  }

  router.get(route('members.index'), filters, {
    preserveState: true,
    replace: true
  })
}

const handlePaginationEvent = (event) => {
  if (event.type === 'start') {
    isProcessing.value = true
  } else if (event.type === 'finish') {
    isProcessing.value = false
  }
}

const getInitials = (firstName, lastName) => {
  const first = firstName?.charAt(0) || ''
  const last = lastName?.charAt(0) || ''
  return (first + last).toUpperCase()
}

const confirmDelete = (member) => {
  memberToDelete.value = member
  showDeleteModal.value = true
}

const deleteMember = () => {
  if (memberToDelete.value && !isDeleting.value) {
    isDeleting.value = true

    router.delete(route('members.destroy', memberToDelete.value.id), {
      onSuccess: () => {
        showDeleteModal.value = false
        memberToDelete.value = null
        isDeleting.value = false
      },
      onError: (errors) => {
        isDeleting.value = false
        // Error wird automatisch von Inertia angezeigt
      }
    })
  }
}

const closeDeleteModal = () => {
  if (!isDeleting.value) {
    showDeleteModal.value = false
    memberToDelete.value = null
  }
}
</script>
