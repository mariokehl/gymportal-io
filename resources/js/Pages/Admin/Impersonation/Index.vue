<template>
    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="bg-surface shadow-sm sm:rounded-lg mb-6">
          <div class="p-6">
            <h2 class="text-2xl font-semibold text-ink">
              Benutzer-Simulation
            </h2>
            <p class="mt-2 text-ink-secondary">
              Simulieren, blockieren oder löschen Sie Benutzerkonten.
            </p>
          </div>
        </div>

        <!-- Info-Box für Administratoren -->
        <div class="mb-6">
          <div class="bg-info-subtle border border-info-line p-4 rounded-lg">
            <div class="flex items-center">
              <Info class="h-5 w-5 text-info mr-3 flex-shrink-0" />
              <div>
                <p class="text-sm font-medium text-info-strong">
                  Hinweis zur Benutzer-Simulation
                </p>
                <p class="text-sm text-info">
                  Nach dem Start einer Simulation werden Sie als der gewählte Benutzer eingeloggt.
                  Verwenden Sie das rote Banner oben auf der Seite, um zur Admin-Ansicht zurückzukehren.
                </p>
              </div>
            </div>
          </div>
        </div>

        <!-- Suchfeld -->
        <div class="bg-surface shadow-sm sm:rounded-lg mb-6">
          <div class="p-6">
            <div class="relative">
              <input
                v-model="searchQuery"
                type="text"
                placeholder="Nach Namen oder E-Mail suchen..."
                aria-label="Benutzer nach Namen oder E-Mail suchen"
                class="field w-full pl-10 pr-4 py-2"
              />
              <Search class="absolute left-3 top-2.5 h-5 w-5 text-ink-muted" aria-hidden="true" />
            </div>
          </div>
        </div>

        <!-- Benutzerliste -->
        <div class="@container bg-surface shadow-sm sm:rounded-lg overflow-clip">
          <!-- Narrow containers: one row per user, the table needs ~1150px -->
          <ul class="@6xl:hidden divide-y divide-line-subtle">
            <li
              v-for="user in filteredUsers"
              :key="user.id"
              :class="['px-4 py-3 space-y-2.5', user.paid_gyms_count > 0 ? 'bg-success-subtle/60' : '']"
            >
              <div class="flex items-center gap-3">
                <div class="size-9 shrink-0 rounded-full bg-brand flex items-center justify-center">
                  <span class="text-white font-medium text-xs">{{ getInitials(user.first_name, user.last_name) }}</span>
                </div>
                <div class="min-w-0 flex-1">
                  <p class="flex items-center gap-2 text-sm font-medium text-ink">
                    <span class="truncate">{{ user.first_name }} {{ user.last_name }}</span>
                    <span
                      v-if="user.is_blocked"
                      class="shrink-0 inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold bg-warning-soft text-warning"
                      :title="user.blocked_reason || undefined"
                    >
                      Blockiert
                    </span>
                  </p>
                  <p class="flex items-center gap-1 text-xs text-ink-muted">
                    <span class="truncate">{{ user.email }}</span>
                    <BadgeCheck v-if="user.email_verified_at" class="h-3.5 w-3.5 shrink-0 text-success" aria-hidden="true" />
                    <span class="sr-only">{{ user.email_verified_at ? 'E-Mail verifiziert' : 'E-Mail nicht verifiziert' }}</span>
                  </p>
                </div>
              </div>
              <div class="flex items-center justify-between gap-3 pl-12">
                <UserSubscriptionBadges :user="user" />
                <UserRowActions
                  :user="user"
                  @impersonate="startImpersonation(user.id)"
                  @block="userToBlock = $event"
                  @unblock="unblockUser"
                  @delete="userToDelete = $event"
                />
              </div>
            </li>
          </ul>

          <div class="hidden @6xl:block overflow-x-auto">
            <table class="min-w-full divide-y divide-line">
              <thead class="bg-surface-muted">
                <tr>
                  <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase tracking-wider whitespace-nowrap">
                    ID
                  </th>
                  <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase tracking-wider whitespace-nowrap">
                    Name
                  </th>
                  <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase tracking-wider whitespace-nowrap">
                    E-Mail
                  </th>
                  <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase tracking-wider whitespace-nowrap">
                    Abo
                  </th>
                  <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase tracking-wider whitespace-nowrap">
                    Registriert am
                  </th>
                  <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase tracking-wider whitespace-nowrap">
                    Aktionen
                  </th>
                </tr>
              </thead>
              <tbody class="bg-surface divide-y divide-line">
                <tr v-for="user in filteredUsers"
                    :key="user.id"
                    :class="[
                      'transition-colors',
                      user.paid_gyms_count > 0 ? 'bg-success-subtle/60 hover:bg-success-subtle' : 'hover:bg-surface-muted'
                    ]">
                  <td class="px-4 py-3 whitespace-nowrap text-sm text-ink-muted tabular-nums">
                    {{ user.id }}
                  </td>
                  <td class="px-4 py-3 whitespace-nowrap">
                    <div class="flex items-center">
                      <div class="flex-shrink-0 h-10 w-10">
                        <div class="h-10 w-10 rounded-full bg-brand flex items-center justify-center">
                          <span class="text-white font-medium text-sm">
                            {{ getInitials(user.first_name, user.last_name) }}
                          </span>
                        </div>
                      </div>
                      <div class="ml-3 min-w-0">
                        <div class="flex items-center gap-2 text-sm font-medium text-ink">
                          <span class="max-w-56 truncate" :title="`${user.first_name} ${user.last_name}`">{{ user.first_name }} {{ user.last_name }}</span>
                          <span
                            v-if="user.is_blocked"
                            class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold bg-warning-soft text-warning"
                            :title="user.blocked_reason || undefined"
                          >
                            Blockiert
                          </span>
                        </div>
                        <div v-if="user.owned_gyms_count > 0" class="text-xs text-ink-muted">
                          {{ user.owned_gyms_count }} {{ user.owned_gyms_count === 1 ? 'Studio' : 'Studios' }}
                        </div>
                      </div>
                    </div>
                  </td>
                  <td class="px-4 py-3 whitespace-nowrap text-sm">
                    <div class="flex items-center gap-1.5">
                      <span :class="['max-w-64 truncate', user.email_verified_at ? 'text-ink' : 'text-ink-muted']" :title="user.email">{{ user.email }}</span>
                      <span
                        v-if="user.email_verified_at"
                        :title="`E-Mail verifiziert am ${formatDate(user.email_verified_at)}`"
                        class="inline-flex"
                      >
                        <BadgeCheck class="h-4 w-4 text-success flex-shrink-0" aria-hidden="true" />
                        <span class="sr-only">E-Mail verifiziert</span>
                      </span>
                    </div>
                    <div v-if="!user.email_verified_at" class="text-xs text-ink-muted">Nicht verifiziert</div>
                  </td>
                  <td class="px-4 py-3 whitespace-nowrap">
                    <UserSubscriptionBadges :user="user" />
                  </td>
                  <td class="px-4 py-3 whitespace-nowrap text-sm text-ink-muted tabular-nums">
                    {{ formatDate(user.created_at) }}
                  </td>
                  <td class="px-4 py-3 whitespace-nowrap text-right">
                    <UserRowActions
                      :user="user"
                      @impersonate="startImpersonation(user.id)"
                      @block="userToBlock = $event"
                      @unblock="unblockUser"
                      @delete="userToDelete = $event"
                    />
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Keine Ergebnisse -->
            <div v-if="filteredUsers.length === 0" class="text-center py-12">
              <Users class="mx-auto h-12 w-12 text-ink-muted" />
              <h3 class="mt-2 text-sm font-medium text-ink">
                Keine Benutzer gefunden
              </h3>
              <p class="mt-1 text-sm text-ink-muted">
                {{ searchQuery ? 'Versuchen Sie eine andere Suche.' : 'Es sind keine Benutzer vorhanden.' }}
              </p>
            </div>
        </div>
      </div>

      <BlockUserModal v-if="userToBlock" :user="userToBlock" @close="userToBlock = null" />
      <DeleteUserModal v-if="userToDelete" :user="userToDelete" @close="userToDelete = null" />
    </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import {
  Info,
  Search,
  Users,
  BadgeCheck
} from '@lucide/vue'
import BlockUserModal from './BlockUserModal.vue'
import DeleteUserModal from './DeleteUserModal.vue'
import UserRowActions from './UserRowActions.vue'
import UserSubscriptionBadges from './UserSubscriptionBadges.vue'
import { formatDateShort as formatDate } from '@/utils/formatters'

const props = defineProps({
  users: Array,
  flash: Object
})

const searchQuery = ref('')
const userToBlock = ref(null)
const userToDelete = ref(null)

const filteredUsers = computed(() => {
  if (!searchQuery.value) {
    return props.users
  }

  const query = searchQuery.value.toLowerCase()
  return props.users.filter(user => {
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

const unblockUser = (user) => {
  if (confirm(`Möchten Sie ${user.first_name} ${user.last_name} wirklich entsperren?`)) {
    router.delete(route('admin.users.unblock', { user: user.id }), {
      preserveScroll: true
    })
  }
}
</script>
