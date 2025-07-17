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
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Name
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Mitgliedsnummer
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Status
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Letzter Besuch
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Vertragsende
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
                  {{ member.last_visit ? formatDate(member.last_visit) : 'Noch nie' }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  {{ member.contract_end_date ? formatDate(member.contract_end_date) : '-' }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                  <div class="flex items-center justify-end space-x-2">
                    <Link
                      :href="route('members.show', member.id)"
                      class="text-gray-700 hover:text-gray-900 p-1 rounded"
                      title="Anzeigen"
                    >
                      <Eye class="w-4 h-4" />
                    </Link>
                    <Link
                      :href="route('members.show', member.id) + '?edit=true'"
                      class="text-indigo-600 hover:text-indigo-900 p-1 rounded"
                      title="Bearbeiten"
                    >
                      <Edit class="w-4 h-4" />
                    </Link>
                    <button
                      @click="confirmDelete(member)"
                      class="text-red-600 hover:text-red-900 p-1 rounded"
                      title="Löschen"
                    >
                      <Trash2 class="w-4 h-4" />
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="members.links && members.links.length > 3" class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
          <div class="flex-1 flex justify-between sm:hidden">
            <Link
              v-if="members.prev_page_url"
              :href="members.prev_page_url"
              class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
            >
              Zurück
            </Link>
            <Link
              v-if="members.next_page_url"
              :href="members.next_page_url"
              class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
            >
              Weiter
            </Link>
          </div>
          <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
            <div>
              <p class="text-sm text-gray-700">
                Zeige
                <span class="font-medium">{{ members.from }}</span>
                bis
                <span class="font-medium">{{ members.to }}</span>
                von
                <span class="font-medium">{{ members.total }}</span>
                Ergebnissen
              </p>
            </div>
            <div>
              <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                <template v-for="(link, index) in members.links" :key="index">
                  <Link
                    v-if="link.url"
                    :href="link.url"
                    :class="[
                      'relative inline-flex items-center px-4 py-2 border text-sm font-medium',
                      link.active
                        ? 'z-10 bg-indigo-50 border-indigo-500 text-indigo-600'
                        : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50',
                      index === 0 ? 'rounded-l-md' : '',
                      index === members.links.length - 1 ? 'rounded-r-md' : ''
                    ]"
                    v-html="link.label"
                  />
                  <span
                    v-else
                    :class="[
                      'relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-300',
                      index === 0 ? 'rounded-l-md' : '',
                      index === members.links.length - 1 ? 'rounded-r-md' : ''
                    ]"
                    v-html="link.label"
                  />
                </template>
              </nav>
            </div>
          </div>
        </div>
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

    <!-- Lösch-Bestätigungsmodal -->
    <div v-if="showDeleteModal" class="fixed inset-0 overflow-y-auto h-full w-full z-50">
      <div class="relative top-20 mx-auto p-5 w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3 text-center">
          <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100">
            <AlertTriangle class="h-6 w-6 text-red-600" />
          </div>
          <h3 class="text-lg font-medium text-gray-900 mt-2">Mitglied löschen</h3>
          <div class="mt-2 px-7 py-3">
            <p class="text-sm text-gray-500">
              Sind Sie sicher, dass Sie das Mitglied
              <strong>{{ memberToDelete?.first_name }} {{ memberToDelete?.last_name }}</strong>
              löschen möchten? Diese Aktion kann nicht rückgängig gemacht werden.
            </p>
          </div>
          <div class="items-center px-4 py-3">
            <button
              @click="deleteMember"
              class="px-4 py-2 bg-red-500 text-white text-base font-medium rounded-md w-full shadow-sm hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-red-300"
            >
              Löschen
            </button>
            <button
              @click="showDeleteModal = false"
              class="mt-3 px-4 py-2 bg-white text-gray-500 text-base font-medium rounded-md w-full shadow-sm border border-gray-300 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-gray-300"
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
import { ref, reactive, watch } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import { debounce } from 'lodash'
import AppLayout from '@/Layouts/AppLayout.vue'
import {
  Users, Plus, Search, Edit, Trash2, Eye, AlertTriangle
} from 'lucide-vue-next'
import MemberStatusBadge from '@/Components/MemberStatusBadge.vue'

// Props
const props = defineProps({
  members: Object,
  filters: Object
})

// Reactive data
const filters = reactive({
  search: props.filters?.search || '',
  status: props.filters?.status || ''
})

const showDeleteModal = ref(false)
const memberToDelete = ref(null)

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

const getInitials = (firstName, lastName) => {
  const first = firstName?.charAt(0) || ''
  const last = lastName?.charAt(0) || ''
  return (first + last).toUpperCase()
}

const formatDate = (dateString) => {
  if (!dateString) return ''
  const date = new Date(dateString)
  return date.toLocaleDateString('de-DE')
}

const confirmDelete = (member) => {
  memberToDelete.value = member
  showDeleteModal.value = true
}

const deleteMember = () => {
  if (memberToDelete.value) {
    router.delete(route('members.destroy', memberToDelete.value.id), {
      onSuccess: () => {
        showDeleteModal.value = false
        memberToDelete.value = null
      }
    })
  }
}
</script>
