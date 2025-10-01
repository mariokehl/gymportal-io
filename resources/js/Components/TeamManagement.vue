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
                        Benutzer hinzufügen
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
                                            <div class="text-sm font-medium text-gray-900">
                                                {{ gymUser.user?.first_name }} {{ gymUser.user?.last_name }}
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

        <!-- Add User Modal -->
        <div v-if="showAddUserModal" class="fixed inset-0 bg-gray-500/75 overflow-y-auto h-full w-full z-50">
            <div class="relative top-20 mx-auto p-5 border border-gray-50 w-96 shadow-lg rounded-md bg-white">
                <div class="mt-3">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Benutzer hinzufügen</h3>

                    <form @submit.prevent="addUser" class="space-y-4">
                        <div>
                            <label class="block text-sm/6 font-medium text-gray-700">E-Mail-Adresse</label>
                            <input v-model="userForm.email" type="email"
                                class="block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-700 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6"
                                required />
                        </div>

                        <div>
                            <label class="block text-sm/6 font-medium text-gray-700">Vorname</label>
                            <input v-model="userForm.first_name" type="text"
                                class="block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-700 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6"
                                required />
                        </div>

                        <div>
                            <label class="block text-sm/6 font-medium text-gray-700">Nachname</label>
                            <input v-model="userForm.last_name" type="text"
                                class="block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-700 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6"
                                required />
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
                            <button type="button" @click="showAddUserModal = false"
                                class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 border border-gray-300 rounded-md hover:bg-gray-300">
                                Abbrechen
                            </button>
                            <button type="submit" :disabled="isSubmittingUser"
                                class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md hover:bg-indigo-700 disabled:opacity-50">
                                Hinzufügen
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue'
import { Plus, Trash2 } from 'lucide-vue-next'

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
    }
})

// Emits
const emit = defineEmits(['success', 'error'])

// Local reactive state
const localGymUsers = ref([...props.gymUsers])
const showAddUserModal = ref(false)
const isSubmittingUser = ref(false)

const userForm = ref({
    email: '',
    first_name: '',
    last_name: '',
    role: 'staff'
})

// Methods
const getUserInitials = (user) => {
    if (!user) return '??'
    const first = user.first_name?.charAt(0) || ''
    const last = user.last_name?.charAt(0) || ''
    return (first + last).toUpperCase() || '??'
}

const formatDate = (dateString) => {
    if (!dateString) return '-'
    return new Date(dateString).toLocaleDateString('de-DE')
}

const addUser = async () => {
    isSubmittingUser.value = true

    try {
        const response = await axios.post(route('settings.gym-users.store'), {
            gym_id: props.currentGym.id,
            ...userForm.value
        })

        if (response.data.gym_user) {
            localGymUsers.value.push(response.data.gym_user)
            showAddUserModal.value = false
            userForm.value = { email: '', first_name: '', last_name: '', role: 'staff' }
            emit('success', 'Benutzer erfolgreich hinzugefügt!')
        }
    } catch (error) {
        emit('error', 'Fehler beim Hinzufügen des Benutzers')
    } finally {
        isSubmittingUser.value = false
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
