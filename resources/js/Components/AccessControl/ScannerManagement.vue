<template>
    <div class="space-y-6">
        <!-- Secret Key Section -->
        <div class="bg-surface shadow-sm rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-lg leading-6 font-semibold text-ink flex items-center">
                            <Key class="w-5 h-5 mr-2 text-ink-muted" />
                            Scanner-Secret-Key
                        </h3>
                        <p class="mt-1 text-sm text-ink-muted">
                            Wird für die HMAC-Signatur der QR-Codes benötigt. Alle Geräte müssen diesen Key kennen.
                        </p>
                    </div>
                </div>

                <!-- Secret Key not generated yet -->
                <div v-if="!currentSecretKey" class="bg-warning-subtle border border-warning-line rounded-lg p-4">
                    <div class="flex items-start">
                        <AlertTriangle class="w-5 h-5 text-warning mt-0.5 mr-2 flex-shrink-0" />
                        <div class="flex-1">
                            <p class="text-sm font-medium text-warning-strong">
                                Noch kein Secret-Key generiert
                            </p>
                            <p class="text-sm text-warning mt-1">
                                Sie müssen zuerst einen Secret-Key generieren, bevor Geräte die QR-Codes validieren können.
                            </p>
                            <button
                                @click="generateSecretKey"
                                :disabled="isRegeneratingKey"
                                class="mt-3 inline-flex items-center px-3 py-1.5 bg-warning-solid text-white text-sm font-medium rounded-md hover:bg-warning-solid-strong disabled:opacity-50"
                            >
                                <Key class="w-4 h-4 mr-1" />
                                {{ isRegeneratingKey ? 'Generiere...' : 'Secret-Key generieren' }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Secret Key exists -->
                <div v-else class="flex items-center space-x-3">
                    <div class="flex-1 font-mono text-sm bg-surface-muted px-4 py-2 rounded-lg border">
                        {{ showSecretKey ? currentSecretKey : '••••••••••••••••••••••••••••••••' }}
                    </div>
                    <button
                        @click="showSecretKey = !showSecretKey"
                        class="p-2 text-ink-muted hover:text-ink"
                        :title="showSecretKey ? 'Verbergen' : 'Anzeigen'"
                    >
                        <Eye v-if="!showSecretKey" class="w-5 h-5" />
                        <EyeOff v-else class="w-5 h-5" />
                    </button>
                    <button
                        @click="copySecretKey"
                        class="p-2 text-ink-muted hover:text-ink"
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
        <div class="bg-surface shadow-sm rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-lg leading-6 font-semibold text-ink">
                        Geräte
                    </h3>
                    <button
                        @click="openCreateModal"
                        class="inline-flex items-center px-4 py-2 bg-brand text-white font-medium rounded-lg hover:bg-brand-strong transition-colors"
                    >
                        <Plus class="w-4 h-4 mr-2" />
                        Neues Gerät
                    </button>
                </div>

                <!-- Empty State -->
                <div v-if="scanners.length === 0" class="text-center py-12">
                    <Scan class="mx-auto h-12 w-12 text-ink-muted" />
                    <h3 class="mt-2 text-sm font-medium text-ink">Keine Geräte</h3>
                    <p class="mt-1 text-sm text-ink-muted">
                        Erstellen Sie Ihr erstes Gerät für die Zugangskontrolle.
                    </p>
                </div>

                <!-- Device Table -->
                <div v-else class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-line">
                        <thead class="bg-surface-muted">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-ink-muted uppercase tracking-wider">
                                    #
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-ink-muted uppercase tracking-wider">
                                    Name
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-ink-muted uppercase tracking-wider">
                                    Geräteaufgabe
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-ink-muted uppercase tracking-wider">
                                    Status
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-ink-muted uppercase tracking-wider">
                                    Verknüpfte Leistung
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-ink-muted uppercase tracking-wider">
                                    IP-Adresse
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-ink-muted uppercase tracking-wider">
                                    Zuletzt gesehen
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-ink-muted uppercase tracking-wider">
                                    Scans heute
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-ink-muted uppercase tracking-wider">
                                    Aktionen
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-surface divide-y divide-line">
                            <tr v-for="scanner in scanners" :key="scanner.id" class="hover:bg-surface-muted">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-ink">
                                    {{ scanner.device_number }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-ink">{{ scanner.device_name }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="flex items-center text-sm leading-5 text-ink-secondary">
                                        <component
                                            :is="deviceTaskIcon(scanner.device_task)"
                                            v-if="deviceTaskIcon(scanner.device_task)"
                                            class="w-4 h-4 mr-1.5 shrink-0 text-brand-ink"
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
                                                        ? 'bg-danger-soft text-danger'
                                                        : 'bg-warning-soft text-warning'
                                                ]"
                                            >
                                                <AlertTriangle class="w-3 h-3" />
                                            </span>
                                        </Tooltip>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-ink-muted">
                                    <template v-if="linkedAddons(scanner).length === 1">
                                        {{ linkedAddons(scanner)[0].name }}
                                    </template>
                                    <Tooltip
                                        v-else-if="linkedAddons(scanner).length > 1"
                                        position="top"
                                        teleport
                                    >
                                        <span class="underline decoration-dotted underline-offset-2 cursor-default">
                                            {{ linkedAddons(scanner).length }} Leistungen
                                        </span>
                                        <template #content>
                                            <div
                                                v-for="group in groupAddonsByLocation(linkedAddons(scanner), gymId)"
                                                :key="group.gymId"
                                                class="not-first:mt-2"
                                            >
                                                <p class="font-bold uppercase">
                                                    {{ group.isOwnLocation ? 'Dieser Standort' : (group.gymName ?? 'Anderer Standort') }}
                                                </p>
                                                <p v-for="addon in group.addons" :key="addon.id">
                                                    {{ addon.name }}
                                                </p>
                                            </div>
                                        </template>
                                    </Tooltip>
                                    <template v-else>—</template>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-ink-muted font-mono">
                                    {{ scanner.ip_address || '-' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-ink-muted">
                                    {{ formatLastSeen(scanner.last_seen_at) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-ink">
                                    {{ scanner.today_scans || 0 }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex items-center justify-end space-x-2">
                                        <button
                                            @click="openEditModal(scanner)"
                                            class="text-brand-ink hover:text-brand-ink-strong p-1"
                                            title="Bearbeiten"
                                        >
                                            <Settings class="w-4 h-4" />
                                        </button>
                                        <button
                                            v-if="scanner.config_downloadable"
                                            @click="downloadConfig(scanner)"
                                            class="text-success hover:text-success-strong p-1"
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
                                                    type="button"
                                                    aria-disabled="true"
                                                    aria-label="Konfiguration herunterladen nicht möglich"
                                                    class="text-ink-disabled cursor-not-allowed p-1 rounded"
                                                >
                                                    <Download class="w-4 h-4" />
                                                </button>
                                            </template>

                                            <template #content>
                                                <div class="font-semibold mb-1 flex items-center">
                                                    <AlertCircle class="w-3 h-3 mr-1" />
                                                    Download nicht möglich
                                                </div>
                                                <div class="text-ink-disabled">
                                                    Der API-Token ist aus Sicherheitsgründen nur als Hash gespeichert
                                                    und lässt sich nicht erneut ausgeben.
                                                </div>
                                                <div class="mt-2 pt-2 border-t border-gray-700 text-ink-muted">
                                                    Tipp: Token erneuern, um eine neue Konfiguration zu erhalten
                                                </div>
                                            </template>
                                        </Tooltip>
                                        <button
                                            @click="confirmDelete(scanner)"
                                            class="text-danger hover:text-danger-strong p-1"
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
            <div class="relative top-10 mx-auto p-5 border border-line-subtle w-full max-w-3xl shadow-lg rounded-md bg-surface" @click.stop>
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-ink">
                        {{ editingScanner ? 'Gerät bearbeiten' : 'Neues Gerät' }}
                    </h3>
                    <button @click="closeModal" class="text-ink-muted hover:text-ink-secondary">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="saveScanner" class="space-y-4">
                    <!-- Two columns so the optional settings do not push the
                         dialog past the bottom of the screen. Stacks below md. -->
                    <div class="grid grid-cols-1 md:grid-cols-2 md:gap-x-6 gap-y-4">
                        <div class="space-y-4">
                            <div>
                                <label for="scanner-management-form-device-name" class="block text-sm font-medium text-ink-secondary mb-2">Name <span class="text-danger">*</span></label>
                            <input id="scanner-management-form-device-name"
                                v-model="form.device_name"
                                type="text"
                                required
                                placeholder="z.B. Getränkeautomat Theke"
                                class="field w-full px-3 py-2"
                            />
                        </div>

                        <div>
                            <label for="scanner-management-form-device-task" class="block text-sm font-medium text-ink-secondary mb-2">
                                Geräteaufgabe <span class="text-danger">*</span>
                            </label>
                            <select id="scanner-management-form-device-task"
                                v-model="form.device_task"
                                required
                                class="field w-full px-3 py-2"
                            >
                                <option v-for="task in DEVICE_TASKS" :key="task.value" :value="task.value">
                                    {{ task.label }}
                                </option>
                            </select>
                            <p class="mt-1.5 text-xs text-ink-muted">
                                {{ deviceTaskHint(form.device_task) }}
                            </p>
                        </div>

                        <!-- Quota configuration for devices settling a usage add-on -->
                        <div
                            v-if="isAddonLinkedTask(form.device_task)"
                            class="bg-brand-subtle border border-brand-line rounded-lg p-4"
                        >
                            <div class="flex items-center text-sm font-semibold text-brand-ink-strong mb-3">
                                <component
                                    :is="deviceTaskIcon(form.device_task)"
                                    v-if="deviceTaskIcon(form.device_task)"
                                    class="w-4 h-4 mr-2 shrink-0"
                                />
                                {{ form.device_task === 'dispenser' ? 'Getränkeanlage-Konfiguration' : 'Bereichs-Konfiguration' }}
                            </div>

                            <label class="block text-sm font-medium text-ink-secondary mb-2">
                                Verknüpfte Nutzungsleistungen <span class="text-danger">*</span>
                            </label>

                            <p v-if="usageAddons.length === 0" class="text-sm text-ink-secondary">
                                Es ist noch keine aktive Nutzungsleistung angelegt. Legen Sie zuerst
                                unter „Add-ons“ eine Nutzungsleistung an.
                            </p>

                            <template v-else>
                                <div class="bg-surface border border-line-strong rounded-md divide-y divide-line-subtle max-h-60 overflow-y-auto">
                                    <div v-for="group in usageAddonGroups" :key="group.gymId" class="p-3">
                                        <p
                                            v-if="hasForeignAddons"
                                            class="text-xs font-semibold uppercase tracking-wide text-ink-muted mb-2"
                                        >
                                            {{ group.isOwnLocation ? 'Dieser Standort' : group.gymName }}
                                        </p>
                                        <label
                                            v-for="addon in group.addons"
                                            :key="addon.id"
                                            class="flex items-center py-1 cursor-pointer"
                                        >
                                            <input
                                                v-model="form.addon_ids"
                                                type="checkbox"
                                                :value="addon.id"
                                                class="rounded text-brand-ink"
                                            />
                                            <span class="ml-2 text-sm text-ink-secondary">{{ addon.name }}</span>
                                        </label>
                                    </div>
                                </div>
                                <p class="mt-1.5 text-xs text-ink-secondary">
                                    Beim Zapfvorgang wird geprüft, ob das Mitglied ein gültiges
                                    Kontingent einer der ausgewählten Leistungen besitzt.
                                    <template v-if="hasForeignAddons">
                                        Leistungen anderer Standorte gelten nur für Mitglieder dieses
                                        Standorts und werden nur angeboten, wenn Ihr Standort deren
                                        Mitglieder zum Check-in zulässt.
                                    </template>
                                </p>

                                <div class="mt-3 bg-surface border border-line rounded-lg p-3">
                                    <p class="text-sm font-medium text-ink mb-2">
                                        Bei jeder Nutzung
                                    </p>
                                    <label class="flex items-center mb-2 cursor-pointer">
                                        <input
                                            v-model="form.enforce_quota"
                                            type="radio"
                                            :value="true"
                                            class="text-brand-ink"
                                        />
                                        <span class="ml-2 text-sm text-ink-secondary">
                                            Kontingent prüfen &amp; Nutzung freischalten
                                        </span>
                                    </label>
                                    <label class="flex items-center cursor-pointer">
                                        <input
                                            v-model="form.enforce_quota"
                                            type="radio"
                                            :value="false"
                                            class="text-brand-ink"
                                        />
                                        <span class="ml-2 text-sm text-ink-secondary">
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
                                    class="rounded border-line-strong text-brand-ink shadow-sm"
                                />
                                <span class="ml-2 text-sm text-ink-secondary">Gerät aktiv</span>
                            </label>
                        </div>
                        </div>

                        <!-- Second column: optional security settings -->
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-ink-secondary mb-2">
                                    Erlaubte IP-Adressen (optional)
                            </label>
                            <p class="text-xs text-ink-muted mb-2">
                                Wenn gesetzt, werden nur Anfragen von diesen IP-Adressen akzeptiert.
                            </p>
                            <div class="space-y-2">
                                <div v-for="(ip, index) in form.allowed_ips" :key="index" class="flex items-center space-x-2">
                                    <input
                                        v-model="form.allowed_ips[index]"
                                        type="text"
                                        placeholder="z.B. 192.168.1.100"
                                        class="field flex-1 px-3 py-2 font-mono"
                                    />
                                    <button
                                        type="button"
                                        @click="removeIp(index)"
                                        class="p-1 text-danger hover:text-danger-strong"
                                    >
                                        <X class="w-4 h-4" />
                                    </button>
                                </div>
                                <button
                                    type="button"
                                    @click="addIp"
                                    class="text-sm text-brand-ink hover:text-brand-ink-strong"
                                >
                                    + IP-Adresse hinzufügen
                                </button>
                            </div>
                        </div>

                        <div>
                            <label for="scanner-management-form-token-expires-at" class="block text-sm font-medium text-ink-secondary mb-2">
                                Ablaufdatum des API-Tokens
                            </label>
                            <input id="scanner-management-form-token-expires-at"
                                v-model="form.token_expires_at"
                                type="date"
                                :disabled="tokenNeverExpires"
                                :min="tomorrow"
                                class="field w-full px-3 py-2 disabled:bg-fill disabled:text-ink-muted"
                            />
                            <label class="mt-2 flex items-center">
                                <input
                                    v-model="tokenNeverExpires"
                                    type="checkbox"
                                    class="rounded border-line-strong text-brand-ink"
                                />
                                <span class="ml-2 text-sm text-ink-secondary">Token läuft nie ab</span>
                            </label>
                            <p class="mt-2 text-xs text-warning flex items-start" v-if="!tokenNeverExpires">
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
                    <div v-if="newApiToken" class="bg-success-subtle border border-success-line rounded-lg p-4">
                        <div class="flex items-start">
                            <CheckCircle class="w-5 h-5 text-success mt-0.5 mr-2" />
                            <div class="flex-1">
                                <p class="text-sm font-medium text-success-strong">
                                    Gerät erfolgreich angelegt!
                                </p>
                                <p class="text-xs text-success mt-1">
                                    Kopieren Sie den API-Token jetzt. Er wird nur einmal angezeigt.
                                </p>
                                <div class="mt-2 flex items-center space-x-2">
                                    <code class="flex-1 text-xs bg-surface px-2 py-1 rounded border font-mono break-all">
                                        {{ newApiToken }}
                                    </code>
                                    <button
                                        type="button"
                                        @click="copyToken"
                                        class="p-1 text-success hover:text-success-strong"
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
                            class="inline-flex items-center px-4 py-2 bg-danger-solid text-white rounded-md text-sm font-medium hover:bg-danger-solid-strong disabled:opacity-50 transition-colors"
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
                                class="px-4 py-2 border border-line-strong rounded-md text-sm font-medium text-ink-secondary hover:bg-surface-muted"
                            >
                                {{ newApiToken ? 'Schließen' : 'Abbrechen' }}
                            </button>
                            <button
                                v-if="!newApiToken"
                                type="submit"
                                :disabled="isSaving || missingAddonLink"
                                class="px-4 py-2 bg-brand text-white rounded-md text-sm font-medium hover:bg-brand-strong disabled:opacity-50"
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
            <div class="relative top-20 mx-auto p-5 border border-line-subtle w-96 shadow-lg rounded-md bg-surface" @click.stop>
                <div class="text-center">
                    <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-danger-soft">
                        <AlertTriangle class="h-6 w-6 text-danger" />
                    </div>
                    <h3 class="text-lg font-semibold text-ink mt-4">Gerät löschen</h3>
                    <p class="text-sm text-ink-muted mt-2">
                        Möchten Sie das Gerät "{{ scannerToDelete?.device_name }}" wirklich löschen?
                        Diese Aktion kann nicht rückgängig gemacht werden.
                    </p>
                    <div class="mt-6 space-y-2">
                        <button
                            @click="deleteScanner"
                            :disabled="isDeleting"
                            class="w-full px-4 py-2 bg-danger-solid text-white rounded-md text-sm font-medium hover:bg-danger-solid-strong disabled:opacity-50"
                        >
                            {{ isDeleting ? 'Löschen...' : 'Endgültig löschen' }}
                        </button>
                        <button
                            @click="showDeleteModal = false"
                            class="w-full px-4 py-2 border border-line-strong rounded-md text-sm font-medium text-ink-secondary hover:bg-surface-muted"
                        >
                            Abbrechen
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Regenerate Secret Key Confirmation Modal -->
        <div v-if="showRegenerateKeyModal" class="fixed inset-0 bg-gray-500/75 overflow-y-auto h-full w-full z-50" @click="showRegenerateKeyModal = false">
            <div class="relative top-20 mx-auto p-5 border border-line-subtle w-96 shadow-lg rounded-md bg-surface" @click.stop>
                <div class="text-center">
                    <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-orange-100">
                        <AlertTriangle class="h-6 w-6 text-orange-600" />
                    </div>
                    <h3 class="text-lg font-semibold text-ink mt-4">Secret-Key erneuern</h3>
                    <p class="text-sm text-ink-muted mt-2">
                        Wenn Sie den Secret-Key erneuern, müssen alle Geräte neu konfiguriert werden.
                        Bestehende QR-Codes werden ungültig.
                    </p>
                    <div class="mt-6 space-y-2">
                        <button
                            @click="regenerateSecretKey"
                            :disabled="isRegeneratingKey"
                            class="w-full px-4 py-2 bg-danger-solid text-white rounded-md text-sm font-medium hover:bg-danger-solid-strong disabled:opacity-50 transition-colors"
                        >
                            {{ isRegeneratingKey ? 'Generiere...' : 'Ja, Key erneuern' }}
                        </button>
                        <button
                            @click="showRegenerateKeyModal = false"
                            class="w-full px-4 py-2 border border-line-strong rounded-md text-sm font-medium text-ink-secondary hover:bg-surface-muted"
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
} from '@lucide/vue'
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
import { groupAddonsByLocation, linkedAddons } from '@/utils/scannerAddons'
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
    addon_ids: [],
    enforce_quota: true,
    allowed_ips: [],
    token_expires_at: '',
    is_active: true
})

// Usage add-ons grouped by location, the scanner's own location first.
const usageAddonGroups = computed(() => groupAddonsByLocation(props.usageAddons, props.gymId))

// Checkboxes cannot be marked required, so the button guards the add-on link.
const missingAddonLink = computed(() =>
    isAddonLinkedTask(form.device_task) && form.addon_ids.length === 0
)

const hasForeignAddons = computed(() => usageAddonGroups.value.some(group => !group.isOwnLocation))

const scannerAddonIds = (scanner) => linkedAddons(scanner).map(addon => addon.id)

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
    form.addon_ids = []
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
    // Links the location no longer offers (add-on deactivated, sibling
    // location closed) are dropped here, so saving does not fail on them.
    form.addon_ids = scannerAddonIds(scanner)
        .filter(id => props.usageAddons.some(addon => addon.id === id))
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
            // The add-on links and the quota mode only apply to add-on linked tasks.
            addon_ids: isAddonLinked ? form.addon_ids : [],
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
        return 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-fill text-ink'
    }
    if (scanner.locked_until && new Date(scanner.locked_until) > new Date()) {
        return 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-warning-soft text-warning-strong'
    }
    if (scanner.last_seen_at && isOnline(scanner.last_seen_at)) {
        return 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-success-soft text-success-strong'
    }
    return 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-danger-soft text-danger-strong'
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
