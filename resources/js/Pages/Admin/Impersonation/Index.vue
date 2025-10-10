<template>
  <AppLayout title="Benutzer simulieren">
    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="bg-white shadow-sm sm:rounded-lg mb-6">
          <div class="p-6">
            <h2 class="text-2xl font-semibold text-gray-900">
              Benutzer-Simulation
            </h2>
            <p class="mt-2 text-gray-600">
              Wählen Sie einen Benutzer aus, um sich als dieser anzumelden.
            </p>
          </div>
        </div>

        <!-- Info-Box für Administratoren -->
        <div class="mb-6">
          <div class="bg-blue-50 border-l-4 border-blue-400 p-4 rounded-lg">
            <div class="flex items-center">
              <Info class="h-5 w-5 text-blue-400 mr-3 flex-shrink-0" />
              <div>
                <p class="text-sm font-medium text-blue-800">
                  Hinweis zur Benutzer-Simulation
                </p>
                <p class="text-sm text-blue-700">
                  Nach dem Start einer Simulation werden Sie als der gewählte Benutzer eingeloggt.
                  Verwenden Sie das rote Banner oben auf der Seite, um zur Admin-Ansicht zurückzukehren.
                </p>
              </div>
            </div>
          </div>
        </div>

        <!-- Suchfeld -->
        <div class="bg-white shadow-sm sm:rounded-lg mb-6">
          <div class="p-6">
            <div class="relative">
              <input
                v-model="searchQuery"
                type="text"
                placeholder="Nach Namen oder E-Mail suchen..."
                class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              />
              <Search class="absolute left-3 top-2.5 h-5 w-5 text-gray-400" />
            </div>
          </div>
        </div>

        <!-- Benutzerliste -->
        <div class="bg-white shadow-sm sm:rounded-lg">
          <div class="overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    ID
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Name
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    E-Mail
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Registriert am
                  </th>
                  <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Aktionen
                  </th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <tr v-for="user in filteredUsers"
                    :key="user.id"
                    class="hover:bg-gray-50 transition-colors">
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    {{ user.id }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="flex items-center">
                      <div class="flex-shrink-0 h-10 w-10">
                        <div class="h-10 w-10 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center">
                          <span class="text-white font-medium text-sm">
                            {{ getInitials(user.first_name, user.last_name) }}
                          </span>
                        </div>
                      </div>
                      <div class="ml-4">
                        <div class="text-sm font-medium text-gray-900">
                          {{ user.first_name }} {{ user.last_name }}
                        </div>
                      </div>
                    </div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    {{ user.email }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    {{ formatDate(user.created_at) }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                    <button
                      @click="startImpersonation(user.id)"
                      class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors"
                    >
                      <UserCheck class="h-4 w-4 mr-1" />
                      Simulieren
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>

            <!-- Keine Ergebnisse -->
            <div v-if="filteredUsers.length === 0" class="text-center py-12">
              <Users class="mx-auto h-12 w-12 text-gray-400" />
              <h3 class="mt-2 text-sm font-medium text-gray-900">
                Keine Benutzer gefunden
              </h3>
              <p class="mt-1 text-sm text-gray-500">
                {{ searchQuery ? 'Versuchen Sie eine andere Suche.' : 'Es sind keine Benutzer zur Simulation verfügbar.' }}
              </p>
            </div>
          </div>
        </div>

        <!-- Pagination -->
        <div v-if="users.links && users.links.length > 3" class="mt-6">
          <nav class="flex items-center justify-between">
            <div class="flex-1 flex justify-between sm:hidden">
              <Link
                v-if="users.prev_page_url"
                :href="users.prev_page_url"
                class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
              >
                <ChevronLeft class="h-4 w-4 mr-1" />
                Zurück
              </Link>
              <Link
                v-if="users.next_page_url"
                :href="users.next_page_url"
                class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
              >
                Weiter
                <ChevronRight class="h-4 w-4 ml-1" />
              </Link>
            </div>
            <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
              <div>
                <p class="text-sm text-gray-700">
                  Zeige <span class="font-medium">{{ users.from }}</span> bis
                  <span class="font-medium">{{ users.to }}</span> von
                  <span class="font-medium">{{ users.total }}</span> Ergebnissen
                </p>
              </div>
              <div>
                <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                  <template v-for="(link, index) in users.links" :key="index">
                    <Link
                      v-if="link.url"
                      :href="link.url"
                      :class="[
                        link.active
                          ? 'z-10 bg-blue-50 border-blue-500 text-blue-600'
                          : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50',
                        index === 0 ? 'rounded-l-md' : '',
                        index === users.links.length - 1 ? 'rounded-r-md' : '',
                        'relative inline-flex items-center px-4 py-2 border text-sm font-medium'
                      ]"
                      v-html="link.label"
                    />
                    <span
                      v-else
                      :class="[
                        index === 0 ? 'rounded-l-md' : '',
                        index === users.links.length - 1 ? 'rounded-r-md' : '',
                        'relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-300 cursor-not-allowed'
                      ]"
                      v-html="link.label"
                    />
                  </template>
                </nav>
              </div>
            </div>
          </nav>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import {
  Info,
  Search,
  UserCheck,
  Users,
  ChevronLeft,
  ChevronRight
} from 'lucide-vue-next'
import { formatDateShort as formatDate } from '@/utils/formatters'

const props = defineProps({
  users: Object,
  flash: Object
})

const searchQuery = ref('')

const filteredUsers = computed(() => {
  if (!searchQuery.value) {
    return props.users.data || []
  }

  const query = searchQuery.value.toLowerCase()
  return (props.users.data || []).filter(user => {
    const fullName = `${user.first_name} ${user.last_name}`.toLowerCase()
    return fullName.includes(query) || user.email.toLowerCase().includes(query)
  })
})

const getInitials = (firstName, lastName) => {
  const first = firstName ? firstName.charAt(0).toUpperCase() : ''
  const last = lastName ? lastName.charAt(0).toUpperCase() : ''
  return first + last || '??'
}

const startImpersonation = (userId) => {
  if (confirm('Möchten Sie wirklich diesen Benutzer simulieren?')) {
    router.post(`/admin/impersonate/${userId}`, {}, {
      preserveScroll: true,
      preserveState: false
    })
  }
}
</script>
