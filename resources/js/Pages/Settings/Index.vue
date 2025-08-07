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
                            ? 'border-indigo-500 text-indigo-600'
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
                                    <select v-model="gymForm.country" class="w-full p-2 border border-gray-300 rounded-md bg-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
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
                                    class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2 px-4 rounded-md disabled:opacity-50">
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
                                class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2 px-4 rounded-md flex items-center">
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
                                                    class="w-10 h-10 bg-indigo-100 rounded-full flex items-center justify-center">
                                                    <span class="text-indigo-600 font-medium text-sm">
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

            <!-- Payment Settings -->
            <div v-if="activeTab === 'payments'" class="space-y-6">
                <!-- Übersicht Zahlungsarten -->
                <div class="bg-white shadow-sm rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                            Zahlungsarten-Übersicht
                        </h3>

                        <!-- Status Anzeige wenn Mollie aktiv -->
                        <div v-if="mollieStatus.isActive" class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
                            <div class="flex items-start">
                                <div class="flex-shrink-0">
                                    <svg class="w-5 h-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd"
                                            d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                            clip-rule="evenodd"></path>
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h4 class="text-sm font-medium text-green-800">Mollie Integration aktiv</h4>
                                    <div class="mt-1">
                                        <p class="text-sm text-green-700">
                                            {{ mollieStatus.methodCount }} Zahlungsmethoden verfügbar
                                            • {{ mollieStatus.isTestMode ? 'Test-Modus' : 'Live-Modus' }}
                                        </p>
                                    </div>
                                    <div class="mt-2">
                                        <button @click="editMollieConfig"
                                            class="text-sm text-green-800 hover:text-green-900 font-medium">
                                            Konfiguration bearbeiten →
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Current payment methods from Model -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div v-for="method in currentPaymentMethods" :key="method.key"
                                class="border border-gray-200 rounded-lg p-4">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 rounded-lg flex items-center justify-center mr-3"
                                            :class="getMethodIconBg(method.icon)">
                                            <component :is="getIconComponent(method.icon)"
                                                :class="getMethodIconColor(method.icon)" class="w-5 h-5" />
                                        </div>
                                        <div>
                                            <h4 class="text-sm font-medium text-gray-900">{{ method.name }}</h4>
                                            <p class="text-xs text-gray-500">{{ method.description }}</p>
                                            <p v-if="method.is_overridden" class="text-xs text-orange-600 mt-1">
                                                ⚠️ Durch externe Integration überschrieben
                                            </p>
                                        </div>
                                    </div>
                                    <div class="flex items-center">
                                        <span :class="method.enabled ? 'text-green-600' : 'text-gray-400'"
                                            class="text-xs font-medium">
                                            {{ method.enabled ? 'Aktiv' : 'Inaktiv' }}
                                        </span>
                                        <div class="ml-2 w-2 h-2 rounded-full"
                                            :class="method.enabled ? 'bg-green-400' : 'bg-gray-300'"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Default Payment Methods config -->
                <div class="bg-white shadow-sm rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                            Standard Zahlungsarten
                        </h3>
                        <p class="text-sm text-gray-600 mb-6">
                            Grundlegende Zahlungsarten, die standardmäßig zur Verfügung stehen.
                        </p>

                        <div class="space-y-4">
                            <div v-for="method in standardMethods" :key="method.key"
                                class="flex items-center justify-between p-4 border border-gray-200 rounded-lg">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 rounded-lg flex items-center justify-center mr-3"
                                        :class="getMethodIconBg(method.icon)">
                                        <component :is="getIconComponent(method.icon)"
                                            :class="getMethodIconColor(method.icon)" class="w-5 h-5" />
                                    </div>
                                    <div>
                                        <h4 class="text-sm font-medium text-gray-900">{{ method.name }}</h4>
                                        <p class="text-xs text-gray-500">{{ method.description }}</p>
                                        <div class="flex items-center space-x-3 mt-1">
                                            <span v-if="method.requires_mandate" class="text-xs text-orange-600">
                                                📝 SEPA-Mandat erforderlich
                                            </span>
                                        </div>
                                        <p v-if="method.is_overridden" class="text-xs text-orange-600 mt-1">
                                            ⚠️ Durch externe Integration überschrieben
                                        </p>
                                    </div>
                                </div>
                                <div class="flex items-center space-x-3">
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox"
                                            v-model="method.enabled"
                                            :disabled="method.is_overridden"
                                            @change="updateStandardMethod(method)"
                                            class="sr-only peer">
                                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600 disabled:opacity-50 disabled:cursor-not-allowed"></div>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- External Integrations -->
                <div class="bg-white shadow-sm rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <div class="flex justify-between items-center mb-4">
                            <div>
                                <h3 class="text-lg leading-6 font-medium text-gray-900">
                                    Externe Zahlungsdienstleister
                                </h3>
                                <p class="text-sm text-gray-600 mt-1">
                                    Integrieren Sie externe Zahlungsdienstleister für erweiterte Funktionen.
                                </p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Mollie Integration -->
                            <div class="border border-gray-200 rounded-lg p-6">
                                <div class="flex items-center mb-4">
                                    <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center mr-4">
                                        <svg class="w-6 h-6 text-orange-600" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm0 2.4c5.302 0 9.6 4.298 9.6 9.6s-4.298 9.6-9.6 9.6S2.4 17.302 2.4 12 6.698 2.4 12 2.4z"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <h4 class="text-sm font-medium text-gray-900">Mollie</h4>
                                        <p class="text-xs text-gray-500">Europäischer Zahlungsdienstleister</p>
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <div class="flex items-center justify-between text-xs text-gray-600 mb-2">
                                        <span>Status:</span>
                                        <span :class="mollieStatus.isActive ? 'text-green-600' : 'text-gray-500'">
                                            {{ mollieStatus.isActive ? 'Konfiguriert' : 'Nicht konfiguriert' }}
                                        </span>
                                    </div>
                                    <div v-if="mollieStatus.isActive" class="text-xs text-gray-600 space-y-1">
                                        <div class="flex justify-between">
                                            <span>Modus:</span>
                                            <span>{{ mollieStatus.isTestMode ? 'Test' : 'Live' }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span>Methoden:</span>
                                            <span>{{ mollieStatus.methodCount }}</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="space-y-2">
                                    <button v-if="!mollieStatus.isActive"
                                        @click="setupMollie"
                                        class="w-full bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium py-2 px-4 rounded-md">
                                        Einrichten
                                    </button>
                                    <template v-else>
                                        <button @click="editMollieConfig"
                                            class="w-full bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium py-2 px-4 rounded-md mb-2">
                                            Konfiguration bearbeiten
                                        </button>
                                        <button @click="removeMollieConfig"
                                            class="w-full bg-red-50 hover:bg-red-100 text-red-600 text-sm font-medium py-2 px-4 rounded-md border border-red-200">
                                            Integration entfernen
                                        </button>
                                    </template>
                                </div>
                            </div>

                            <!-- Placeholder for more Integrations -->
                            <div class="border border-gray-200 rounded-lg p-6 bg-gray-50">
                                <div class="flex items-center mb-4">
                                    <div class="w-12 h-12 bg-gray-200 rounded-lg flex items-center justify-center mr-4">
                                        <component :is="Plus" class="w-6 h-6 text-gray-400" />
                                    </div>
                                    <div>
                                        <h4 class="text-sm font-medium text-gray-500">Weitere Integrationen</h4>
                                        <p class="text-xs text-gray-400">Kommen bald</p>
                                    </div>
                                </div>
                                <p class="text-xs text-gray-500 mb-4">
                                    Weitere Zahlungsdienstleister wie Stripe, PayPal und andere werden in Zukunft verfügbar sein.
                                </p>
                                <button disabled
                                    class="w-full bg-gray-200 text-gray-400 text-sm font-medium py-2 px-4 rounded-md cursor-not-allowed">
                                    Bald verfügbar
                                </button>
                            </div>
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

        <!-- Mollie Setup Modal -->
        <div v-if="showMollieSetup" class="fixed inset-0 bg-gray-500/75 overflow-y-auto h-full w-full z-50" @click="closeMollieSetup">
            <div class="relative top-20 mx-auto p-5 border border-gray-50 w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white" @click.stop>
                <MollieSetupWizard
                    :organization="currentGym"
                    @setup-completed="onMollieSetupCompleted"
                    @configuration-saved="onMollieConfigSaved" />
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import {
    Building2, Users, Plus, Trash2, Signature, CreditCard,
    Wallet, DollarSign, FileText, HandCoins
} from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import LogoUpload from '@/Components/LogoUpload.vue'
import ContractWidget from '@/Components/ContractWidget.vue'
import MollieSetupWizard from '@/Components/MollieSetupWizard.vue'

// Props
const props = defineProps({
    user: Object,
    currentGym: Object,
    gymUsers: Array
})

// Reactive data
const activeTab = ref('gym')
const showAddUserModal = ref(false)
const showMollieSetup = ref(false)
const isSubmittingGym = ref(false)
const isSubmittingUser = ref(false)
const successMessage = ref('')
const errorMessage = ref('')

// Make gymUsers reactive for updates
const gymUsers = ref([...props.gymUsers])

// Payment methods state - will be loaded by the Model
const standardMethods = ref([])
const currentPaymentMethods = ref([])

const mollieStatus = ref({
    isActive: false,
    isTestMode: false,
    methodCount: 0,
    enabledMethods: []
})

const tabs = [
    { key: 'gym', label: 'Gym-Einstellungen', icon: Building2 },
    { key: 'team', label: 'Team', icon: Users },
    { key: 'payments', label: 'Zahlungsarten', icon: CreditCard },
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

// Icon mapping for Payment Methods
const iconComponents = {
    Wallet,
    HandCoins,
    FileText,
    DollarSign,
    CreditCard
}

// Methods
const getIconComponent = (iconName) => {
    return iconComponents[iconName] || CreditCard
}

const getMethodIconBg = (iconName) => {
    const iconBgMap = {
        'Wallet': 'bg-green-100',
        'HandCoins': 'bg-yellow-100',
        'FileText': 'bg-blue-100',
        'DollarSign': 'bg-purple-100',
        'CreditCard': 'bg-orange-100'
    }
    return iconBgMap[iconName] || 'bg-gray-100'
}

const getMethodIconColor = (iconName) => {
    const iconColorMap = {
        'Wallet': 'text-green-600',
        'HandCoins': 'text-yellow-600',
        'FileText': 'text-blue-600',
        'DollarSign': 'text-purple-600',
        'CreditCard': 'text-orange-600'
    }
    return iconColorMap[iconName] || 'text-gray-600'
}

const saveGymSettings = async () => {
    isSubmittingGym.value = true

    try {
        const response = await axios.put(route('settings.gym.update', props.currentGym.id), gymForm.value)

        if (response.data.gym) {
            Object.assign(gymForm.value, response.data.gym)
        }

        successMessage.value = 'Gym-Einstellungen erfolgreich gespeichert!'
        setTimeout(() => successMessage.value = '', 3000)
    } catch (error) {
        errorMessage.value = 'Fehler beim Speichern der Gym-Einstellungen'
        setTimeout(() => errorMessage.value = '', 3000)
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
                router.visit('/dashboard')
            },
            onError: () => {
                errorMessage.value = 'Fehler beim Löschen der Organisation'
                setTimeout(() => errorMessage.value = '', 3000)
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

        if (response.data.gym_user) {
            gymUsers.value.push(response.data.gym_user)
            showAddUserModal.value = false
            userForm.value = { email: '', role: 'staff' }
            successMessage.value = 'Benutzer erfolgreich hinzugefügt!'
            setTimeout(() => successMessage.value = '', 3000)
        }
    } catch (error) {
        errorMessage.value = 'Fehler beim Hinzufügen des Benutzers'
        setTimeout(() => errorMessage.value = '', 3000)
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

        successMessage.value = 'Benutzerrolle erfolgreich aktualisiert!'
        setTimeout(() => successMessage.value = '', 3000)
    } catch (error) {
        console.error('Fehler beim Aktualisieren der Benutzerrolle:', error)
        errorMessage.value = 'Fehler beim Aktualisieren der Benutzerrolle'
        setTimeout(() => errorMessage.value = '', 3000)
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

        const index = gymUsers.value.findIndex(u => u.id === gymUser.id)
        if (index > -1) {
            gymUsers.value.splice(index, 1)
        }

        successMessage.value = 'Benutzer erfolgreich entfernt!'
        setTimeout(() => successMessage.value = '', 3000)
    } catch (error) {
        console.error('Fehler beim Entfernen des Benutzers:', error)
        errorMessage.value = 'Fehler beim Entfernen des Benutzers'
        setTimeout(() => errorMessage.value = '', 3000)
    } finally {
        gymUser.isRemoving = false
    }
}

// Payment methods - New: Load from Model
const loadPaymentMethods = async () => {
    try {
        const response = await axios.get(route('settings.payment-methods.overview'))
        const data = response.data

        standardMethods.value = data.methods.standard || []
        currentPaymentMethods.value = data.methods.enabled || []

        // Mollie Status update
        mollieStatus.value = {
            isActive: data.mollie_status.is_active || false,
            isTestMode: data.mollie_status.is_test_mode || false,
            methodCount: data.mollie_status.method_count || 0,
            enabledMethods: data.methods.mollie || []
        }

    } catch (error) {
        console.error('Fehler beim Laden der Zahlungsmethoden:', error)
    }
}

const updateStandardMethod = async (method) => {
    try {
        await axios.put(route('settings.payment-methods.update'), {
            method: method.key,
            enabled: method.enabled
        })

        successMessage.value = `${method.name} ${method.enabled ? 'aktiviert' : 'deaktiviert'}!`
        setTimeout(() => successMessage.value = '', 3000)

        // Payment methods refresh
        await loadPaymentMethods()
    } catch (error) {
        // Revert change on error
        method.enabled = !method.enabled
        errorMessage.value = 'Fehler beim Aktualisieren der Zahlungsmethode'
        setTimeout(() => errorMessage.value = '', 3000)
    }
}

const setupMollie = () => {
    showMollieSetup.value = true
}

const editMollieConfig = () => {
    showMollieSetup.value = true
}

const closeMollieSetup = () => {
    showMollieSetup.value = false
}

const onMollieSetupCompleted = async () => {
    await loadPaymentMethods()
    successMessage.value = 'Mollie Integration erfolgreich eingerichtet!'
    setTimeout(() => successMessage.value = '', 3000)
}

const onMollieConfigSaved = async (config) => {
    mollieStatus.value.isActive = true
    mollieStatus.value.isTestMode = config.test_mode
    mollieStatus.value.methodCount = config.enabled_methods?.length || 0
    await loadPaymentMethods()
}

const removeMollieConfig = async () => {
    if (!confirm('Möchten Sie die Mollie-Integration wirklich entfernen?')) {
        return
    }

    try {
        await axios.delete(route('settings.mollie.remove'))

        mollieStatus.value = {
            isActive: false,
            isTestMode: false,
            methodCount: 0,
            enabledMethods: []
        }

        await loadPaymentMethods()
        successMessage.value = 'Mollie Integration erfolgreich entfernt!'
        setTimeout(() => successMessage.value = '', 3000)
    } catch (error) {
        console.error(error)
        errorMessage.value = 'Fehler beim Entfernen der Mollie Integration'
        setTimeout(() => errorMessage.value = '', 3000)
    }
}

// Utility methods
const getUserInitials = (user) => {
    const first = user.first_name?.charAt(0) || ''
    const last = user.last_name?.charAt(0) || ''
    return (first + last).toUpperCase()
}

const formatDate = (dateString) => {
    if (!dateString) return '-'
    return new Date(dateString).toLocaleDateString('de-DE')
}

// Load data on mount
onMounted(() => {
    loadPaymentMethods()
})
</script>
