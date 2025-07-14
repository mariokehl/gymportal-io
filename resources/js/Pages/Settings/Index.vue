<template>
    <AppLayout title="Einstellungen">
        <template #header>
            Einstellungen
        </template>

        <div class="max-w-4xl mx-auto">
            <!-- Tabs -->
            <div class="border-b border-gray-200 mb-6">
                <nav class="-mb-px flex space-x-8">
                    <button v-for="tab in tabs" :key="tab.key" @click="activeTab = tab.key" :class="[
                        'py-2 px-1 border-b-2 font-medium text-sm',
                        activeTab === tab.key
                            ? 'border-blue-500 text-blue-600'
                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                    ]">
                        <component :is="tab.icon" class="w-4 h-4 mr-2 inline" />
                        {{ tab.label }}
                    </button>
                </nav>
            </div>

            <!-- Success/Error Messages -->
            <div v-if="successMessage"
                class="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded">
                {{ successMessage }}
            </div>
            <div v-if="errorMessage" class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded">
                {{ errorMessage }}
            </div>

            <!-- Gym Settings -->
            <div v-if="activeTab === 'gym'" class="space-y-6">
                <div class="bg-white shadow-sm rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                            Gym-Informationen
                        </h3>

                        <form @submit.prevent="saveGymSettings" class="space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm/6 font-medium text-gray-700">Name <span class="text-red-500">*</span></label>
                                    <input v-model="gymForm.name" type="text" class="block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-700 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6" required />
                                </div>

                                <div class="md:col-span-2">
                                    <label class="block text-sm/6 font-medium text-gray-700">Beschreibung</label>
                                    <textarea v-model="gymForm.description" rows="3" class="block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-700 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6"></textarea>
                                </div>

                                <LogoUpload v-model="gymForm.logo_path" :current-gym="currentGym" />

                                <div>
                                    <label class="block text-sm/6 font-medium text-gray-700">Straße und Hausnummer <span class="text-red-500">*</span></label>
                                    <input v-model="gymForm.address" type="text" class="block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-700 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6" />
                                </div>

                                <div>
                                    <label class="block text-sm/6 font-medium text-gray-700">Stadt <span class="text-red-500">*</span></label>
                                    <input v-model="gymForm.city" type="text" class="block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-700 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6" />
                                </div>

                                <div>
                                    <label class="block text-sm/6 font-medium text-gray-700">Postleitzahl <span class="text-red-500">*</span></label>
                                    <input v-model="gymForm.postal_code" type="text" class="block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-700 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6" />
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Land <span class="text-red-500">*</span></label>
                                    <select v-model="gymForm.country" class="w-full p-2 border border-gray-300 rounded-md bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                        <option value="DE">Deutschland</option>
                                        <option value="AT">Österreich</option>
                                        <option value="CH">Schweiz</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-sm/6 font-medium text-gray-700">Telefon <span class="text-red-500">*</span></label>
                                    <input v-model="gymForm.phone" type="tel" class="block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-700 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6" />
                                </div>

                                <div>
                                    <label class="block text-sm/6 font-medium text-gray-700">E-Mail <span class="text-red-500">*</span></label>
                                    <input v-model="gymForm.email" type="email" class="block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-700 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6" />
                                </div>

                                <div>
                                    <label class="block text-sm/6 font-medium text-gray-700">Website</label>
                                    <input v-model="gymForm.website" type="url" class="block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-700 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6" />
                                </div>
                            </div>

                            <div class="flex justify-end">
                                <button
                                    type="submit"
                                    :disabled="isSubmittingGym"
                                    class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md disabled:opacity-50">
                                    Speichern
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="bg-white shadow-sm rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                            Organisation löschen
                        </h3>
                        <p class="text-sm text-gray-600 mt-1">
                            Sobald eine Organisation gelöscht wird, werden alle zugehörigen Ressourcen und Daten
                            dauerhaft gelöscht.
                            Laden Sie vor dem Löschen dieser Organisation alle Daten und Informationen herunter, die Sie
                            behalten möchten.
                        </p>

                        <form @submit.prevent="deleteGym" class="space-y-6">
                            <div class="flex justify-end">
                                <button
                                    type="submit"
                                    :disabled="isSubmittingGym"
                                    class="bg-red-600 hover:bg-red-700 text-white font-medium py-2 px-4 rounded-md disabled:opacity-50">
                                    Organisation löschen
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Team Management -->
            <div v-if="activeTab === 'team'" class="space-y-6">
                <div class="bg-white shadow-sm rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg leading-6 font-medium text-gray-900">
                                Team-Mitglieder
                            </h3>
                            <button @click="showAddUserModal = true"
                                class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md flex items-center">
                                <component :is="Plus" class="w-4 h-4 mr-2" />
                                Benutzer hinzufügen
                            </button>
                        </div>

                        <div class="bg-gray-50 border border-gray-200 rounded-md p-3 shadow-sm mb-4">
                            <div class="flex items-center">
                                <svg class="h-4 w-4 text-gray-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd"></path>
                                </svg>
                                <span class="text-sm text-gray-700">
                                    Feature noch nicht implementiert - wird in Kürze verfügbar sein
                                </span>
                            </div>
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
                                    <tr v-for="gymUser in gymUsers" :key="gymUser.id">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div
                                                    class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                                                    <span class="text-blue-600 font-medium text-sm">
                                                        {{ getUserInitials(gymUser.user) }}
                                                    </span>
                                                </div>
                                                <div class="ml-4">
                                                    <div class="text-sm font-medium text-gray-900">
                                                        {{ gymUser.user.first_name }} {{ gymUser.user.last_name }}
                                                    </div>
                                                    <div class="text-sm text-gray-500">
                                                        {{ gymUser.user.email }}
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <select v-model="gymUser.role" @change="updateUserRole(gymUser)"
                                                :disabled="gymUser.user.id === user.id || gymUser.isUpdating"
                                                class="w-full p-2 border border-gray-300 rounded-md bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 disabled:bg-gray-100">
                                                <option value="admin">Admin</option>
                                                <option value="staff">Mitarbeiter</option>
                                                <option value="trainer">Trainer</option>
                                            </select>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ formatDate(gymUser.created_at) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <button v-if="gymUser.user.id !== user.id" @click="removeUser(gymUser)"
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
            </div>

            <!-- Contracts -->
            <div v-if="activeTab === 'contracts'" class="space-y-6">
                <ContractWidget :current-gym="currentGym" />
            </div>
        </div>

        <!-- Add User Modal -->
        <div v-if="showAddUserModal" class="fixed inset-0 overflow-y-auto h-full w-full z-50">
            <div class="relative top-20 mx-auto p-5 w-96 shadow-lg rounded-md bg-white">
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
                            <label class="block text-sm/6 font-medium text-gray-700">Rolle</label>
                            <select v-model="userForm.role"
                                class="w-full p-2 border border-gray-300 rounded-md bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
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
                                class="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700 disabled:opacity-50">
                                Hinzufügen
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import {
    Building2, Users, Plus, Trash2,
    Signature
} from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import LogoUpload from '@/Components/LogoUpload.vue'
import ContractWidget from '@/Components/ContractWidget.vue'

// Props
const props = defineProps({
    user: Object,
    currentGym: Object,
    gymUsers: Array
})

// Reactive data
const activeTab = ref('gym')
const showAddUserModal = ref(false)
const isSubmittingGym = ref(false)
const isSubmittingUser = ref(false)
const successMessage = ref('')
const errorMessage = ref('')

// Make gymUsers reactive for updates
const gymUsers = ref([...props.gymUsers])

const tabs = [
    { key: 'gym', label: 'Gym-Einstellungen', icon: Building2 },
    { key: 'team', label: 'Team', icon: Users },
    { key: 'contracts', label: 'Online-Verträge', icon: Signature },
]

const gymForm = ref({
    name: props.currentGym?.name || '',
    slug: props.currentGym?.slug || '',
    description: props.currentGym?.description || '',
    address: props.currentGym?.address || '',
    city: props.currentGym?.city || '',
    postal_code: props.currentGym?.postal_code || '',
    country: props.currentGym?.country || 'DE',
    phone: props.currentGym?.phone || '',
    email: props.currentGym?.email || '',
    website: props.currentGym?.website || '',
    logo_path: props.currentGym?.logo_path || ''
})

const userForm = ref({
    email: '',
    role: 'staff'
})

// Methods
const saveGymSettings = async () => {
    isSubmittingGym.value = true

    try {
        const response = await axios.put(route('settings.gym.update', props.currentGym.id), gymForm.value)

        if (response.data.gym) {
            Object.assign(gymForm.value, response.data.gym)
        }
    } catch (error) {
        // Error handling...
    } finally {
        isSubmittingGym.value = false
    }
}

const deleteGym = async () => {
    if (!confirm('Möchten Sie die Organisation wirklich löschen?')) {
        return
    }

    isSubmittingGym.value = true

    try {
        await router.delete(route('gyms.remove', props.currentGym.id), {}, {
            onSuccess: () => {
                // Redirect to dashboard after successful deletion
                router.visit('/dashboard')
            },
            onError: () => {
                // Error handling...
            },
            onFinish: () => {
                isSubmittingGym.value = false
            }
        })
    } catch (error) {
        console.error('Error deleting gym:', error)
        isSubmittingGym.value = false
    }
}

const addUser = async () => {
    isSubmittingUser.value = true

    try {
        const response = await axios.post(route('settings.gym-users.store'), {
            gym_id: props.currentGym.id,
            ...userForm.value
        })
        // ...
    } catch (error) {
        // Error handling...
    } finally {
        isSubmittingUser.value = false
    }
}

const updateUserRole = async (gymUser) => {
    // Add loading state to the specific user
    gymUser.isUpdating = true

    try {
        await axios.put(route('settings.gym-users.update', gymUser.id), {
            role: gymUser.role
        })
    } catch (error) {
        console.error('Fehler beim Aktualisieren der Benutzerrolle:', error)

        // Revert the role change on error
        // You might want to store the original role to revert to

        if (error.response?.data?.message) {
            console.error(error.response?.data?.message);
        }
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

        // Remove user from local array
        const index = gymUsers.value.findIndex(u => u.id === gymUser.id)
        if (index > -1) {
            gymUsers.value.splice(index, 1)
        }
    } catch (error) {
        console.error('Fehler beim Entfernen des Benutzers:', error)

        if (error.response?.data?.message) {
            console.error(error.response?.data?.message)
        }
    } finally {
        gymUser.isRemoving = false
    }
}

const getUserInitials = (user) => {
    const first = user.first_name?.charAt(0) || ''
    const last = user.last_name?.charAt(0) || ''
    return (first + last).toUpperCase()
}

const formatDate = (dateString) => {
    if (!dateString) return '-'
    return new Date(dateString).toLocaleDateString('de-DE')
}
</script>
