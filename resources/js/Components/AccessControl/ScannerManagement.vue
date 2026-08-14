<template>
    <div class="space-y-6">
        <!-- Secret Key Section -->
        <div class="bg-white shadow-sm rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-lg leading-6 font-medium text-gray-900 flex items-center">
                            <Key class="w-5 h-5 mr-2 text-gray-500" />
                            Scanner-Secret-Key
                        </h3>
                        <p class="mt-1 text-sm text-gray-500">
                            Wird für die HMAC-Signatur der QR-Codes benötigt. Alle Geräte müssen diesen Key kennen.
                        </p>
                    </div>
                </div>

                <!-- Secret Key not generated yet -->
                <div v-if="!currentSecretKey" class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                    <div class="flex items-start">
                        <AlertTriangle class="w-5 h-5 text-yellow-500 mt-0.5 mr-2 flex-shrink-0" />
                        <div class="flex-1">
                            <p class="text-sm font-medium text-yellow-800">
                                Noch kein Secret-Key generiert
                            </p>
                            <p class="text-sm text-yellow-700 mt-1">
                                Sie müssen zuerst einen Secret-Key generieren, bevor Geräte die QR-Codes validieren können.
                            </p>
                            <button
                                @click="generateSecretKey"
                                :disabled="isRegeneratingKey"
                                class="mt-3 inline-flex items-center px-3 py-1.5 bg-yellow-600 text-white text-sm font-medium rounded-md hover:bg-yellow-700 disabled:opacity-50"
                            >
                                <Key class="w-4 h-4 mr-1" />
                                {{ isRegeneratingKey ? 'Generiere...' : 'Secret-Key generieren' }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Secret Key exists -->
                <div v-else class="flex items-center space-x-3">
                    <div class="flex-1 font-mono text-sm bg-gray-50 px-4 py-2 rounded-lg border">
                        {{ showSecretKey ? currentSecretKey : '••••••••••••••••••••••••••••••••' }}
                    </div>
                    <button
                        @click="showSecretKey = !showSecretKey"
                        class="p-2 text-gray-500 hover:text-gray-700"
                        :title="showSecretKey ? 'Verbergen' : 'Anzeigen'"
                    >
                        <Eye v-if="!showSecretKey" class="w-5 h-5" />
                        <EyeOff v-else class="w-5 h-5" />
                    </button>
                    <button
                        @click="copySecretKey"
                        class="p-2 text-gray-500 hover:text-gray-700"
                        title="Kopieren"
                    >
                        <Copy class="w-5 h-5" />
                    </button>
                    <button
                        @click="confirmRegenerateSecretKey"
                        class="p-2 text-orange-500 hover:text-orange-700"
                        title="Neu generieren"
                    >
                        <RefreshCw class="w-5 h-5" />
                    </button>
                </div>
            </div>
        </div>

        <!-- Device List -->
        <div class="bg-white shadow-sm rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">
                        Geräte
                    </h3>
                    <button
                        @click="openCreateModal"
                        class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700 transition-colors"
                    >
                        <Plus class="w-4 h-4 mr-2" />
                        Neues Gerät
                    </button>
                </div>

                <!-- Empty State -->
                <div v-if="scanners.length === 0" class="text-center py-12">
                    <Scan class="mx-auto h-12 w-12 text-gray-400" />
                    <h3 class="mt-2 text-sm font-medium text-gray-900">Keine Geräte</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        Erstellen Sie Ihr erstes Gerät für die Zugangskontrolle.
                    </p>
                </div>

                <!-- Device Table -->
                <div v-else class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    #
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Name
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Geräteaufgabe
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Status
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Verknüpfte Leistung
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    IP-Adresse
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Zuletzt gesehen
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Scans heute
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Aktionen
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="scanner in scanners" :key="scanner.id" class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-900">
                                    {{ scanner.device_number }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">{{ scanner.device_name }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="flex items-center text-sm leading-5 text-gray-700">
                                        <component
                                            :is="deviceTaskIcon(scanner.device_task)"
                                            v-if="deviceTaskIcon(scanner.device_task)"
                                            class="w-4 h-4 mr-1.5 shrink-0 text-indigo-600"
                                        />
                                        {{ deviceTaskLabel(scanner.device_task) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center gap-1">
                                        <span :class="getStatusClasses(scanner)">
                                            {{ getStatusLabel(scanner) }}
                                        </span>
                                        <Tooltip
                                            v-if="tokenExpiryState(scanner.token_expires_at) !== 'valid'"
                                            position="top"
                                            :text="tokenExpiryWarning(scanner.token_expires_at)"
                                        >
                                            <span
                                                :class="[
                                                    'inline-flex items-center justify-center w-5 h-5 rounded-full cursor-default',
                                                    tokenExpiryState(scanner.token_expires_at) === 'expired'
                                                        ? 'bg-red-100 text-red-600'
                                                        : 'bg-amber-100 text-amber-600'
                                                ]"
                                            >
                                                <AlertTriangle class="w-3 h-3" />
                                            </span>
                                        </Tooltip>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ scanner.addon?.name || '—' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 font-mono">
                                    {{ scanner.ip_address || '-' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ formatLastSeen(scanner.last_seen_at) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ scanner.today_scans || 0 }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex items-center justify-end space-x-2">
                                        <button
                                            @click="openEditModal(scanner)"
                                            class="text-indigo-600 hover:text-indigo-900 p-1"
                                            title="Bearbeiten"
                                        >
                                            <Settings class="w-4 h-4" />
                                        </button>
                                        <button
                                            v-if="scanner.config_downloadable"
                                            @click="downloadConfig(scanner)"
                                            class="text-green-600 hover:text-green-900 p-1"
                                            title="Konfiguration herunterladen"
                                        >
                                            <Download class="w-4 h-4" />
                                        </button>

                                        <!-- Disabled download button with tooltip.
                                             Teleported: the table scrolls horizontally, which would
                                             clip the panel and widen the scroll area. -->
                                        <Tooltip v-else position="left" teleport>
                                            <template #default>
                                                <button
                                                    disabled
                                                    class="text-gray-300 cursor-not-allowed p-1 rounded opacity-50"
                                                >
                                                    <Download class="w-4 h-4" />
                                                </button>
                                            </template>

                                            <template #content>
                                                <div class="font-semibold mb-1 flex items-center">
                                                    <AlertCircle class="w-3 h-3 mr-1" />
                                                    Download nicht möglich
                                                </div>
                                                <div class="text-gray-300">
                                                    Der API-Token ist aus Sicherheitsgründen nur als Hash gespeichert
                                                    und lässt sich nicht erneut ausgeben.
                                                </div>
                                                <div class="mt-2 pt-2 border-t border-gray-700 text-gray-400">
                                                    Tipp: Token erneuern, um eine neue Konfiguration zu erhalten
                                                </div>
                                            </template>
                                        </Tooltip>
                                        <button
                                            @click="confirmDelete(scanner)"
                                            class="text-red-600 hover:text-red-900 p-1"
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
            </div>
        </div>

        <!-- Create/Edit Modal -->
        <div v-if="showModal" class="fixed inset-0 bg-gray-500/75 overflow-y-auto h-full w-full z-50" @click="closeModal">
            <div class="relative top-10 mx-auto p-5 border border-gray-50 w-full max-w-3xl shadow-lg rounded-md bg-white" @click.stop>
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">
                        {{ editingScanner ? 'Gerät bearbeiten' : 'Neues Gerät' }}
                    </h3>
                    <button @click="closeModal" class="text-gray-400 hover:text-gray-500">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="saveScanner" class="space-y-4">
                    <!-- Two columns so the optional settings do not push the
                         dialog past the bottom of the screen. Stacks below md. -->
                    <div class="grid grid-cols-1 md:grid-cols-2 md:gap-x-6 gap-y-4">
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Name <span class="text-red-500">*</span></label>
                            <input
                                v-model="form.device_name"
                                type="text"
                                required
                                placeholder="z.B. Getränkeautomat Theke"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Geräteaufgabe <span class="text-red-500">*</span>
                            </label>
                            <select
                                v-model="form.device_task"
                                required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            >
                                <option v-for="task in DEVICE_TASKS" :key="task.value" :value="task.value">
                                    {{ task.label }}
                                </option>
                            </select>
                            <p class="mt-1.5 text-xs text-gray-500">
                                {{ deviceTaskHint(form.device_task) }}
                            </p>
                        </div>

                        <!-- Quota configuration for devices settling a usage add-on -->
                        <div
                            v-if="isAddonLinkedTask(form.device_task)"
                            class="bg-indigo-50 border border-indigo-200 rounded-lg p-4"
                        >
                            <div class="flex items-center text-sm font-semibold text-indigo-800 mb-3">
                                <component
                                    :is="deviceTaskIcon(form.device_task)"
                                    v-if="deviceTaskIcon(form.device_task)"
                                    class="w-4 h-4 mr-2 shrink-0"
                                />
                                {{ form.device_task === 'dispenser' ? 'Getränkeanlage-Konfiguration' : 'Bereichs-Konfiguration' }}
                            </div>

                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Verknüpfte Nutzungsleistung <span class="text-red-500">*</span>
                            </label>

                            <p v-if="usageAddons.length === 0" class="text-sm text-gray-600">
                                Es ist noch keine aktive Nutzungsleistung angelegt. Legen Sie zuerst
                                unter „Add-ons“ eine Nutzungsleistung an.
                            </p>

                            <template v-else>
                                <select
                                    v-model="form.addon_id"
                                    required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                >
                                    <option :value="null" disabled>Bitte wählen</option>
                                    <option v-for="addon in usageAddons" :key="addon.id" :value="addon.id">
                                        {{ addon.name }}
                                    </option>
                                </select>
                                <p class="mt-1.5 text-xs text-gray-600">
                                    Beim Zapfvorgang wird geprüft, ob das Mitglied ein gültiges
                                    Kontingent dieser Leistung besitzt.
                                </p>

                                <div class="mt-3 bg-white border border-gray-200 rounded-lg p-3">
                                    <p class="text-sm font-medium text-gray-900 mb-2">
                                        Bei jeder Nutzung
                                    </p>
                                    <label class="flex items-center mb-2 cursor-pointer">
                                        <input
                                            v-model="form.enforce_quota"
                                            type="radio"
                                            :value="true"
                                            class="text-indigo-600 focus:ring-indigo-500"
                                        />
                                        <span class="ml-2 text-sm text-gray-700">
                                            Kontingent prüfen &amp; Nutzung freischalten
                                        </span>
                                    </label>
                                    <label class="flex items-center cursor-pointer">
                                        <input
                                            v-model="form.enforce_quota"
                                            type="radio"
                                            :value="false"
                                            class="text-indigo-600 focus:ring-indigo-500"
                                        />
                                        <span class="ml-2 text-sm text-gray-700">
                                            Nur protokollieren (keine Sperre)
                                        </span>
                                    </label>
                                </div>
                            </template>
                        </div>

                        <div v-if="editingScanner">
                            <label class="flex items-center">
                                <input
                                    v-model="form.is_active"
                                    type="checkbox"
                                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                />
                                <span class="ml-2 text-sm text-gray-700">Gerät aktiv</span>
                            </label>
                        </div>
                        </div>

                        <!-- Second column: optional security settings -->
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Erlaubte IP-Adressen (optional)
                            </label>
                            <p class="text-xs text-gray-500 mb-2">
                                Wenn gesetzt, werden nur Anfragen von diesen IP-Adressen akzeptiert.
                            </p>
                            <div class="space-y-2">
                                <div v-for="(ip, index) in form.allowed_ips" :key="index" class="flex items-center space-x-2">
                                    <input
                                        v-model="form.allowed_ips[index]"
                                        type="text"
                                        placeholder="z.B. 192.168.1.100"
                                        class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono"
                                    />
                                    <button
                                        type="button"
                                        @click="removeIp(index)"
                                        class="p-1 text-red-500 hover:text-red-700"
                                    >
                                        <X class="w-4 h-4" />
                                    </button>
                                </div>
                                <button
                                    type="button"
                                    @click="addIp"
                                    class="text-sm text-indigo-600 hover:text-indigo-800"
                                >
                                    + IP-Adresse hinzufügen
                                </button>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Ablaufdatum des API-Tokens
                            </label>
                            <input
                                v-model="form.token_expires_at"
                                type="date"
                                :disabled="tokenNeverExpires"
                                :min="tomorrow"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:bg-gray-100 disabled:text-gray-400"
                            />
                            <label class="mt-2 flex items-center">
                                <input
                                    v-model="tokenNeverExpires"
                                    type="checkbox"
                                    class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                />
                                <span class="ml-2 text-sm text-gray-700">Token läuft nie ab</span>
                            </label>
                            <p class="mt-2 text-xs text-amber-600 flex items-start" v-if="!tokenNeverExpires">
                                <AlertTriangle class="w-3.5 h-3.5 mr-1 mt-0.5 shrink-0" />
                                <span>
                                    Nach diesem Datum verweigert das Gerät den Zutritt, bis ein neuer
                                    Token hinterlegt wurde. Planen Sie den Austausch rechtzeitig ein.
                                </span>
                            </p>
                            </div>
                        </div>
                    </div>

                    <!-- Show Token after creation -->
                    <div v-if="newApiToken" class="bg-green-50 border border-green-200 rounded-lg p-4">
                        <div class="flex items-start">
                            <CheckCircle class="w-5 h-5 text-green-500 mt-0.5 mr-2" />
                            <div class="flex-1">
                                <p class="text-sm font-medium text-green-800">
                                    Gerät erfolgreich angelegt!
                                </p>
                                <p class="text-xs text-green-700 mt-1">
                                    Kopieren Sie den API-Token jetzt. Er wird nur einmal angezeigt.
                                </p>
                                <div class="mt-2 flex items-center space-x-2">
                                    <code class="flex-1 text-xs bg-white px-2 py-1 rounded border font-mono break-all">
                                        {{ newApiToken }}
                                    </code>
                                    <button
                                        type="button"
                                        @click="copyToken"
                                        class="p-1 text-green-600 hover:text-green-800"
                                    >
                                        <Copy class="w-4 h-4" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between pt-4">
                        <!-- Destructive action sits apart from the primary ones -->
                        <button
                            v-if="editingScanner && !newApiToken"
                            type="button"
                            @click="regenerateToken"
                            :disabled="isRegeneratingToken"
                            class="inline-flex items-center px-4 py-2 bg-orange-500 text-white rounded-md text-sm font-medium hover:bg-orange-600 disabled:opacity-50"
                        >
                            <RefreshCw
                                class="w-4 h-4 mr-1"
                                :class="{ 'animate-spin': isRegeneratingToken }"
                            />
                            {{ isRegeneratingToken ? 'Generiere...' : 'Token erneuern' }}
                        </button>
                        <span v-else></span>

                        <div class="flex space-x-3">
                            <button
                                type="button"
                                @click="closeModal"
                                class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50"
                            >
                                {{ newApiToken ? 'Schließen' : 'Abbrechen' }}
                            </button>
                            <button
                                v-if="!newApiToken"
                                type="submit"
                                :disabled="isSaving"
                                class="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm font-medium hover:bg-indigo-700 disabled:opacity-50"
                            >
                                {{ isSaving ? 'Speichern...' : (editingScanner ? 'Speichern' : 'Erstellen') }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <div v-if="showDeleteModal" class="fixed inset-0 bg-gray-500/75 overflow-y-auto h-full w-full z-50" @click="showDeleteModal = false">
            <div class="relative top-20 mx-auto p-5 border border-gray-50 w-96 shadow-lg rounded-md bg-white" @click.stop>
                <div class="text-center">
                    <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100">
                        <AlertTriangle class="h-6 w-6 text-red-600" />
                    </div>
                    <h3 class="text-lg font-medium text-gray-900 mt-4">Gerät löschen</h3>
                    <p class="text-sm text-gray-500 mt-2">
                        Möchten Sie das Gerät "{{ scannerToDelete?.device_name }}" wirklich löschen?
                        Diese Aktion kann nicht rückgängig gemacht werden.
                    </p>
                    <div class="mt-6 space-y-2">
                        <button
                            @click="deleteScanner"
                            :disabled="isDeleting"
                            class="w-full px-4 py-2 bg-red-600 text-white rounded-md text-sm font-medium hover:bg-red-700 disabled:opacity-50"
                        >
                            {{ isDeleting ? 'Löschen...' : 'Endgültig löschen' }}
                        </button>
                        <button
                            @click="showDeleteModal = false"
                            class="w-full px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50"
                        >
                            Abbrechen
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Regenerate Secret Key Confirmation Modal -->
        <div v-if="showRegenerateKeyModal" class="fixed inset-0 bg-gray-500/75 overflow-y-auto h-full w-full z-50" @click="showRegenerateKeyModal = false">
            <div class="relative top-20 mx-auto p-5 border border-gray-50 w-96 shadow-lg rounded-md bg-white" @click.stop>
                <div class="text-center">
                    <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-orange-100">
                        <AlertTriangle class="h-6 w-6 text-orange-600" />
                    </div>
                    <h3 class="text-lg font-medium text-gray-900 mt-4">Secret-Key erneuern</h3>
                    <p class="text-sm text-gray-500 mt-2">
                        Wenn Sie den Secret-Key erneuern, müssen alle Geräte neu konfiguriert werden.
                        Bestehende QR-Codes werden ungültig.
                    </p>
                    <div class="mt-6 space-y-2">
                        <button
                            @click="regenerateSecretKey"
                            :disabled="isRegeneratingKey"
                            class="w-full px-4 py-2 bg-orange-500 text-white rounded-md text-sm font-medium hover:bg-orange-600 disabled:opacity-50"
                        >
                            {{ isRegeneratingKey ? 'Generiere...' : 'Ja, Key erneuern' }}
                        </button>
                        <button
                            @click="showRegenerateKeyModal = false"
                            class="w-full px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50"
                        >
                            Abbrechen
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, computed, watch } from 'vue'
import {
    Plus, Scan, Settings, Download, Trash2, Key, Eye, EyeOff,
    Copy, RefreshCw, X, CheckCircle, AlertTriangle, AlertCircle
} from 'lucide-vue-next'
import { formatDistanceToNow } from 'date-fns'
import { de } from 'date-fns/locale'
import Tooltip from '@/Components/Tooltip.vue'
import {
    DEVICE_TASKS,
    DEFAULT_DEVICE_TASK,
    deviceTaskHint,
    deviceTaskIcon,
    deviceTaskLabel,
    isAddonLinkedTask
} from '@/utils/deviceTasks'
import { defaultTokenExpiry, tokenExpiryState, tokenExpiryWarning } from '@/utils/scannerToken'
import { formatDateForInput } from '@/utils/formatters'

const props = defineProps({
    scanners: {
        type: Array,
        required: true
    },
    usageAddons: {
        type: Array,
        default: () => []
    },
    gymId: {
        type: Number,
        required: true
    },
    initialSecretKey: {
        type: String,
        default: null
    }
})

const emit = defineEmits(['scanner-created', 'scanner-updated', 'scanner-deleted', 'success', 'error'])

const currentSecretKey = ref(props.initialSecretKey || '')
const showSecretKey = ref(false)

const showModal = ref(false)
const showDeleteModal = ref(false)
const showRegenerateKeyModal = ref(false)
const editingScanner = ref(null)
const scannerToDelete = ref(null)
const newApiToken = ref(null)

const isSaving = ref(false)
const isDeleting = ref(false)
const isRegeneratingToken = ref(false)
const isRegeneratingKey = ref(false)

const form = reactive({
    device_name: '',
    device_task: DEFAULT_DEVICE_TASK,
    addon_id: null,
    enforce_quota: true,
    allowed_ips: [],
    token_expires_at: '',
    is_active: true
})

// Separate from the form: the payload carries null, not a flag.
const tokenNeverExpires = ref(false)

// The date input must not offer today or earlier — the API rejects it.
const tomorrow = computed(() => {
    const date = new Date()
    date.setDate(date.getDate() + 1)

    return formatDateForInput(date)
})

// Ticking the box clears the date, unticking restores the suggestion, so the
// operator never faces an empty field they have to fill in themselves.
watch(tokenNeverExpires, (never) => {
    form.token_expires_at = never ? '' : defaultTokenExpiry()
})

const resetForm = () => {
    form.device_name = ''
    form.device_task = DEFAULT_DEVICE_TASK
    form.addon_id = null
    form.enforce_quota = true
    form.allowed_ips = []
    form.token_expires_at = defaultTokenExpiry()
    form.is_active = true
    tokenNeverExpires.value = false
    newApiToken.value = null
}

const openCreateModal = () => {
    editingScanner.value = null
    resetForm()
    showModal.value = true
}

const openEditModal = (scanner) => {
    editingScanner.value = scanner
    form.device_name = scanner.device_name
    form.device_task = scanner.device_task || DEFAULT_DEVICE_TASK
    form.addon_id = scanner.addon_id ?? null
    form.enforce_quota = scanner.enforce_quota ?? true
    form.allowed_ips = scanner.allowed_ips ? [...scanner.allowed_ips] : []
    // Existing devices keep what they have — an unset expiry stays unset
    // rather than silently acquiring one on the next save.
    form.token_expires_at = scanner.token_expires_at
        ? formatDateForInput(scanner.token_expires_at)
        : ''
    tokenNeverExpires.value = !scanner.token_expires_at
    form.is_active = scanner.is_active
    newApiToken.value = null
    showModal.value = true
}

const closeModal = () => {
    showModal.value = false
    editingScanner.value = null
    resetForm()
}

const addIp = () => {
    form.allowed_ips.push('')
}

const removeIp = (index) => {
    form.allowed_ips.splice(index, 1)
}

const saveScanner = async () => {
    isSaving.value = true

    try {
        const isAddonLinked = isAddonLinkedTask(form.device_task)

        const data = {
            device_name: form.device_name,
            device_task: form.device_task,
            // The add-on link and the quota mode only apply to add-on linked tasks.
            addon_id: isAddonLinked ? form.addon_id : null,
            enforce_quota: isAddonLinked ? form.enforce_quota : true,
            allowed_ips: form.allowed_ips.filter(ip => ip.trim() !== ''),
            // null tells the API the token never expires.
            token_expires_at: tokenNeverExpires.value ? null : (form.token_expires_at || null),
            is_active: form.is_active
        }

        if (editingScanner.value) {
            const response = await axios.put(
                route('access-control.scanners.update', editingScanner.value.id),
                data
            )
            emit('scanner-updated', response.data.scanner)
            emit('success', 'Gerät erfolgreich aktualisiert')
            closeModal()
        } else {
            const response = await axios.post(route('access-control.scanners.store'), data)
            emit('scanner-created', response.data.scanner)
            newApiToken.value = response.data.api_token
            emit('success', 'Gerät erfolgreich angelegt')
        }
    } catch (error) {
        emit('error', error.response?.data?.message || 'Fehler beim Speichern des Geräts')
    } finally {
        isSaving.value = false
    }
}

const confirmDelete = (scanner) => {
    scannerToDelete.value = scanner
    showDeleteModal.value = true
}

const deleteScanner = async () => {
    if (!scannerToDelete.value) return

    isDeleting.value = true

    try {
        await axios.delete(route('access-control.scanners.destroy', scannerToDelete.value.id))
        emit('scanner-deleted', scannerToDelete.value.id)
        emit('success', 'Gerät erfolgreich gelöscht')
        showDeleteModal.value = false
        scannerToDelete.value = null
    } catch (error) {
        emit('error', error.response?.data?.message || 'Fehler beim Löschen des Geräts')
    } finally {
        isDeleting.value = false
    }
}

const regenerateToken = async () => {
    if (!editingScanner.value) return

    isRegeneratingToken.value = true

    try {
        const response = await axios.post(
            route('access-control.scanners.regenerate-token', editingScanner.value.id)
        )
        newApiToken.value = response.data.api_token
        emit('success', 'API-Token wurde erneuert')
    } catch (error) {
        emit('error', error.response?.data?.message || 'Fehler beim Erneuern des Tokens')
    } finally {
        isRegeneratingToken.value = false
    }
}

const downloadConfig = (scanner) => {
    // The button is disabled in this case, but the list may be stale if the
    // token was regenerated elsewhere — a navigation would show a raw 409.
    if (!scanner.config_downloadable) {
        emit('error', 'Der API-Token ist nur als Hash gespeichert. Erneuern Sie den Token, um eine neue Konfigurationsdatei zu erhalten.')

        return
    }

    window.location.href = route('access-control.scanners.download-config', scanner.id)
}

const confirmRegenerateSecretKey = () => {
    showRegenerateKeyModal.value = true
}

const generateSecretKey = async () => {
    isRegeneratingKey.value = true

    try {
        const response = await axios.post(route('access-control.regenerate-secret-key'))
        currentSecretKey.value = response.data.scanner_secret_key
        emit('success', 'Secret-Key wurde erfolgreich generiert.')
    } catch (error) {
        emit('error', error.response?.data?.message || 'Fehler beim Generieren des Secret-Keys')
    } finally {
        isRegeneratingKey.value = false
    }
}

const regenerateSecretKey = async () => {
    isRegeneratingKey.value = true

    try {
        const response = await axios.post(route('access-control.regenerate-secret-key'))
        currentSecretKey.value = response.data.scanner_secret_key
        emit('success', 'Secret-Key wurde erneuert. Alle Geräte müssen neu konfiguriert werden.')
        showRegenerateKeyModal.value = false
    } catch (error) {
        emit('error', error.response?.data?.message || 'Fehler beim Erneuern des Secret-Keys')
    } finally {
        isRegeneratingKey.value = false
    }
}

const copySecretKey = async () => {
    try {
        await navigator.clipboard.writeText(currentSecretKey.value)
        emit('success', 'Secret-Key in Zwischenablage kopiert')
    } catch (error) {
        emit('error', 'Kopieren fehlgeschlagen')
    }
}

const copyToken = async () => {
    try {
        await navigator.clipboard.writeText(newApiToken.value)
        emit('success', 'API-Token in Zwischenablage kopiert')
    } catch (error) {
        emit('error', 'Kopieren fehlgeschlagen')
    }
}

const getStatusClasses = (scanner) => {
    if (!scanner.is_active) {
        return 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800'
    }
    if (scanner.locked_until && new Date(scanner.locked_until) > new Date()) {
        return 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800'
    }
    if (scanner.last_seen_at && isOnline(scanner.last_seen_at)) {
        return 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800'
    }
    return 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800'
}

const getStatusLabel = (scanner) => {
    if (!scanner.is_active) {
        return 'Inaktiv'
    }
    if (scanner.locked_until && new Date(scanner.locked_until) > new Date()) {
        return 'Gesperrt'
    }
    if (scanner.last_seen_at && isOnline(scanner.last_seen_at)) {
        return 'Online'
    }
    return 'Offline'
}

const isOnline = (lastSeenAt) => {
    if (!lastSeenAt) return false
    const lastSeen = new Date(lastSeenAt)
    const twentyFiveMinutesAgo = new Date(Date.now() - 25 * 60 * 1000)
    return lastSeen > twentyFiveMinutesAgo
}

const formatLastSeen = (lastSeenAt) => {
    if (!lastSeenAt) return 'Noch nie'
    try {
        return formatDistanceToNow(new Date(lastSeenAt), { addSuffix: true, locale: de })
    } catch {
        return '-'
    }
}
</script>
