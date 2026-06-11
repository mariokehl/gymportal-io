<template>
    <div class="space-y-6">
        <div class="bg-white shadow-sm rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">
                        Team-Mitglieder
                    </h3>
                    <button @click="showAddUserModal = true"
                        class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2 px-4 rounded-md flex items-center">
                        <component :is="Plus" class="w-4 h-4 mr-2" />
                        Hinzufügen
                    </button>
                </div>

                <!-- Team Members Table -->
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Benutzer
                                </th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Rolle
                                </th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Hinzugefügt am
                                </th>
                                <th
                                    class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Aktionen
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="gymUser in localGymUsers" :key="gymUser.id">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div
                                            class="w-10 h-10 bg-indigo-100 rounded-full flex items-center justify-center">
                                            <span class="text-indigo-600 font-medium text-sm">
                                                {{ getUserInitials(gymUser.user) }}
                                            </span>
                                        </div>
                                        <div class="ml-4">
                                            <!-- Display mode -->
                                            <div v-if="editingNameId !== gymUser.id"
                                                class="group flex items-center text-sm font-medium text-gray-900">
                                                <span>{{ gymUser.user?.first_name }} {{ gymUser.user?.last_name }}</span>
                                                <button v-if="isGymOwner" @click="startEditName(gymUser)"
                                                    class="ml-2 text-gray-400 hover:text-indigo-600 opacity-0 group-hover:opacity-100 focus:opacity-100 transition-opacity"
                                                    title="Namen bearbeiten">
                                                    <component :is="Pencil" class="w-3.5 h-3.5" />
                                                </button>
                                            </div>
                                            <!-- Edit mode (owner only) -->
                                            <div v-else class="flex items-center gap-1">
                                                <input v-model="nameForm.first_name" type="text" placeholder="Vorname"
                                                    @keyup.enter="saveName(gymUser)" @keyup.esc="cancelEditName"
                                                    class="w-24 rounded-md border border-gray-300 px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500" />
                                                <input v-model="nameForm.last_name" type="text" placeholder="Nachname"
                                                    @keyup.enter="saveName(gymUser)" @keyup.esc="cancelEditName"
                                                    class="w-24 rounded-md border border-gray-300 px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500" />
                                                <button @click="saveName(gymUser)" :disabled="gymUser.isSavingName"
                                                    class="text-green-600 hover:text-green-800 disabled:opacity-50" title="Speichern">
                                                    <component :is="Check" class="w-4 h-4" />
                                                </button>
                                                <button @click="cancelEditName" :disabled="gymUser.isSavingName"
                                                    class="text-gray-400 hover:text-gray-600 disabled:opacity-50" title="Abbrechen">
                                                    <component :is="X" class="w-4 h-4" />
                                                </button>
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                {{ gymUser.user?.email }}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <select v-model="gymUser.role" @change="updateUserRole(gymUser)"
                                        :disabled="gymUser.user?.id === currentUser.id || gymUser.isUpdating"
                                        class="w-full p-2 border border-gray-300 rounded-md bg-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 disabled:bg-gray-100">
                                        <option value="admin">Admin</option>
                                        <option value="staff">Mitarbeiter</option>
                                        <option value="trainer">Trainer</option>
                                    </select>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ formatDate(gymUser.created_at) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <button v-if="gymUser.user?.id !== currentUser.id" @click="removeUser(gymUser)"
                                        :disabled="gymUser.isRemoving"
                                        class="text-red-600 hover:text-red-900 disabled:opacity-50">
                                        <component :is="Trash2" class="w-4 h-4" />
                                    </button>
                                    <span v-else class="text-gray-400 text-xs">
                                        (Sie)
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Pending Invitations -->
        <div v-if="localInvitations.length > 0" class="bg-white shadow-sm rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                    Ausstehende Einladungen
                </h3>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    E-Mail
                                </th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Rolle
                                </th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Eingeladen am
                                </th>
                                <th
                                    class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Aktionen
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="invitation in localInvitations" :key="invitation.id">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ invitation.email }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ roleLabel(invitation.role) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ formatDate(invitation.created_at) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-3">
                                    <button @click="resendInvitation(invitation)"
                                        :disabled="invitation.isBusy"
                                        class="text-indigo-600 hover:text-indigo-900 disabled:opacity-50"
                                        title="Einladung erneut senden">
                                        <component :is="Send" class="w-4 h-4 inline" />
                                    </button>
                                    <button @click="withdrawInvitation(invitation)"
                                        :disabled="invitation.isBusy"
                                        class="text-red-600 hover:text-red-900 disabled:opacity-50"
                                        title="Einladung zurückziehen">
                                        <component :is="Trash2" class="w-4 h-4 inline" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Add User Modal -->
        <div v-if="showAddUserModal" class="fixed inset-0 bg-gray-500/75 overflow-y-auto h-full w-full z-50">
            <div class="relative top-20 mx-auto p-5 border border-gray-50 w-96 shadow-lg rounded-md bg-white">
                <div class="mt-3">
                    <h3 class="text-lg font-medium text-gray-900 mb-1">Team-Mitglied einladen</h3>
                    <p class="text-sm text-gray-500 mb-4">
                        Die Person erhält eine Einladung per E-Mail. Bereits registrierte Benutzer
                        werden sofort hinzugefügt.
                    </p>

                    <form @submit.prevent="inviteUser" class="space-y-4">
                        <div>
                            <label class="block text-sm/6 font-medium text-gray-700">E-Mail-Adresse</label>
                            <input v-model="userForm.email" type="email"
                                class="block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-700 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6"
                                required />
                            <p v-if="formErrors.email" class="mt-1 text-sm text-red-600">{{ formErrors.email }}</p>
                        </div>

                        <div>
                            <label class="block text-sm/6 font-medium text-gray-700">Rolle</label>
                            <select v-model="userForm.role"
                                class="w-full p-2 border border-gray-300 rounded-md bg-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                required>
                                <option value="admin">Admin</option>
                                <option value="staff">Mitarbeiter</option>
                                <option value="trainer">Trainer</option>
                            </select>
                        </div>

                        <div class="flex justify-end space-x-3 pt-4">
                            <button type="button" @click="closeAddUserModal"
                                class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 border border-gray-300 rounded-md hover:bg-gray-300">
                                Abbrechen
                            </button>
                            <button type="submit" :disabled="isSubmittingUser"
                                class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md hover:bg-indigo-700 disabled:opacity-50">
                                Einladen
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { Plus, Trash2, Send, Pencil, Check, X } from 'lucide-vue-next'
import { formatDate } from '@/utils/formatters'

// Props
const props = defineProps({
    currentUser: {
        type: Object,
        required: true
    },
    currentGym: {
        type: Object,
        required: true
    },
    gymUsers: {
        type: Array,
        required: true
    },
    pendingInvitations: {
        type: Array,
        default: () => []
    },
    isGymOwner: {
        type: Boolean,
        default: false
    }
})

// Emits
const emit = defineEmits(['success', 'error'])

// Local reactive state
const localGymUsers = ref([...props.gymUsers])
const localInvitations = ref([...props.pendingInvitations])
const showAddUserModal = ref(false)
const isSubmittingUser = ref(false)
const formErrors = ref({})

// Inline name editing (owner only)
const editingNameId = ref(null)
const nameForm = ref({ first_name: '', last_name: '' })

const userForm = ref({
    email: '',
    role: 'staff'
})

// Keep local lists in sync when Inertia reloads the page props after a
// server-side redirect (invite, resend, withdraw).
watch(() => props.gymUsers, (value) => {
    localGymUsers.value = [...value]
})
watch(() => props.pendingInvitations, (value) => {
    localInvitations.value = [...value]
})

// Methods
const getUserInitials = (user) => {
    if (!user) return '??'
    const first = user.first_name?.charAt(0) || ''
    const last = user.last_name?.charAt(0) || ''
    return (first + last).toUpperCase() || '??'
}

const roleLabel = (role) => {
    return ({ admin: 'Admin', staff: 'Mitarbeiter', trainer: 'Trainer' })[role] || role
}

const closeAddUserModal = () => {
    showAddUserModal.value = false
    formErrors.value = {}
    userForm.value = { email: '', role: 'staff' }
}

const inviteUser = () => {
    isSubmittingUser.value = true
    formErrors.value = {}

    router.post(route('settings.gym-invitations.store'), { ...userForm.value }, {
        preserveScroll: true,
        onSuccess: () => {
            closeAddUserModal()
        },
        onError: (errors) => {
            formErrors.value = errors
            emit('error', errors.email || 'Einladung konnte nicht versendet werden.')
        },
        onFinish: () => {
            isSubmittingUser.value = false
        }
    })
}

const resendInvitation = (invitation) => {
    invitation.isBusy = true
    router.post(route('settings.gym-invitations.resend', invitation.id), {}, {
        preserveScroll: true,
        onSuccess: () => emit('success', 'Einladung wurde erneut versendet.'),
        onError: () => emit('error', 'Einladung konnte nicht erneut versendet werden.'),
        onFinish: () => { invitation.isBusy = false }
    })
}

const withdrawInvitation = (invitation) => {
    if (!confirm('Möchten Sie diese Einladung wirklich zurückziehen?')) {
        return
    }

    invitation.isBusy = true
    router.delete(route('settings.gym-invitations.destroy', invitation.id), {
        preserveScroll: true,
        onSuccess: () => emit('success', 'Einladung wurde zurückgezogen.'),
        onError: () => emit('error', 'Einladung konnte nicht zurückgezogen werden.'),
        onFinish: () => { invitation.isBusy = false }
    })
}

const startEditName = (gymUser) => {
    editingNameId.value = gymUser.id
    nameForm.value = {
        first_name: gymUser.user?.first_name || '',
        last_name: gymUser.user?.last_name || ''
    }
}

const cancelEditName = () => {
    editingNameId.value = null
    nameForm.value = { first_name: '', last_name: '' }
}

const saveName = async (gymUser) => {
    if (!nameForm.value.first_name.trim() || !nameForm.value.last_name.trim()) {
        emit('error', 'Vor- und Nachname dürfen nicht leer sein.')
        return
    }

    gymUser.isSavingName = true

    try {
        const response = await axios.put(route('settings.gym-users.update-name', gymUser.id), {
            first_name: nameForm.value.first_name,
            last_name: nameForm.value.last_name
        })

        if (response.data.user) {
            gymUser.user.first_name = response.data.user.first_name
            gymUser.user.last_name = response.data.user.last_name
        }

        cancelEditName()
        emit('success', 'Name wurde erfolgreich aktualisiert!')
    } catch (error) {
        emit('error', 'Fehler beim Aktualisieren des Namens')
    } finally {
        gymUser.isSavingName = false
    }
}

const updateUserRole = async (gymUser) => {
    gymUser.isUpdating = true

    try {
        await axios.put(route('settings.gym-users.update', gymUser.id), {
            role: gymUser.role
        })

        emit('success', 'Benutzerrolle erfolgreich aktualisiert!')
    } catch (error) {
        console.error('Fehler beim Aktualisieren der Benutzerrolle:', error)
        emit('error', 'Fehler beim Aktualisieren der Benutzerrolle')
    } finally {
        gymUser.isUpdating = false
    }
}

const removeUser = async (gymUser) => {
    if (!confirm('Möchten Sie diesen Benutzer wirklich entfernen?')) {
        return
    }

    gymUser.isRemoving = true

    try {
        await axios.delete(route('settings.gym-users.destroy', gymUser.id))

        const index = localGymUsers.value.findIndex(u => u.id === gymUser.id)
        if (index > -1) {
            localGymUsers.value.splice(index, 1)
        }

        emit('success', 'Benutzer erfolgreich entfernt!')
    } catch (error) {
        console.error('Fehler beim Entfernen des Benutzers:', error)
        emit('error', 'Fehler beim Entfernen des Benutzers')
    } finally {
        gymUser.isRemoving = false
    }
}
</script>
