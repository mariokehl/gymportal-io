<template>
    <div class="space-y-6">
        <div class="bg-surface shadow-sm rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-semibold text-ink mb-4">
                    Rolling QR-Code
                </h3>
                <p class="text-sm text-ink-secondary mb-6">
                    Rolling QR-Codes wechseln in kurzen Intervallen und bieten dadurch einen erhöhten Schutz gegen Screenshots und Weitergabe.
                </p>

                <!-- Warnung -->
                <div class="bg-warning-subtle border border-warning-line rounded-lg p-4 mb-6">
                    <div class="flex items-start">
                        <AlertTriangle class="w-5 h-5 text-warning mt-0.5 mr-2 flex-shrink-0" />
                        <div>
                            <p class="text-sm font-medium text-warning-strong">
                                Wichtiger Hinweis
                            </p>
                            <p class="text-sm text-warning mt-1">
                                Der Scan-Prozess auf den Scanner-Geräten muss entsprechend konfiguriert sein, bevor diese Einstellung aktiviert wird.
                                Andernfalls können unter Umständen alle QR-Codes ungültig sein und nicht gelesen werden.
                                Laden Sie nach dem Aktivieren die Scanner-Konfiguration erneut herunter.
                            </p>
                        </div>
                    </div>
                </div>

                <form @submit.prevent="saveSettings" class="space-y-6">
                    <!-- Toggle -->
                    <div class="flex items-start">
                        <div class="flex items-center h-7">
                            <input
                                id="rolling_qr_enabled"
                                v-model="form.rolling_qr_enabled"
                                type="checkbox"
                                class="h-4 w-4 text-brand border-line-strong rounded"
                            />
                        </div>
                        <div class="ml-3">
                            <label for="rolling_qr_enabled" class="text-sm font-medium text-ink-secondary">
                                Rolling QR-Code aktivieren (Empfohlen)
                            </label>
                            <p class="text-xs text-ink-muted mt-1">
                                Wenn deaktiviert, werden standardmäßig statische QR-Codes mit Zeitstempel verwendet.
                            </p>
                        </div>
                    </div>

                    <!-- Intervall (nur sichtbar wenn aktiviert) -->
                    <div v-if="form.rolling_qr_enabled" class="ml-7 space-y-4">
                        <div>
                            <label for="rolling-qr-settings-form-rolling-qr-interval" class="block text-sm font-medium text-ink-secondary mb-1">
                                Intervall
                            </label>
                            <div class="flex items-center space-x-2">
                                <input id="rolling-qr-settings-form-rolling-qr-interval"
                                    v-model.number="form.rolling_qr_interval"
                                    type="number"
                                    min="1"
                                    max="60"
                                    class="field block w-24 px-3 py-1.5"
                                />
                                <span class="text-sm text-ink-muted">Sekunden</span>
                            </div>
                            <p class="mt-1 text-xs text-ink-muted">
                                Zeitspanne, nach der ein neuer QR-Code generiert wird (Standard: 3 Sekunden).
                            </p>
                        </div>

                        <div>
                            <label for="rolling-qr-settings-form-rolling-qr-tolerance-windows" class="block text-sm font-medium text-ink-secondary mb-1">
                                Toleranzfenster
                            </label>
                            <div class="flex items-center space-x-2">
                                <input id="rolling-qr-settings-form-rolling-qr-tolerance-windows"
                                    v-model.number="form.rolling_qr_tolerance_windows"
                                    type="number"
                                    min="0"
                                    max="5"
                                    class="field block w-24 px-3 py-1.5"
                                />
                                <span class="text-sm text-ink-muted">vorherige Zeitfenster</span>
                            </div>
                            <p class="mt-1 text-xs text-ink-muted">
                                Anzahl der vorherigen Zeitfenster, die beim Scannen zusätzlich akzeptiert werden (Standard: 1).
                            </p>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button
                            type="submit"
                            :disabled="isSaving"
                            class="bg-brand hover:bg-brand-strong text-white font-medium py-2 px-4 rounded-md disabled:opacity-50"
                        >
                            {{ isSaving ? 'Speichern...' : 'Speichern' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue'
import { AlertTriangle } from '@lucide/vue'

const props = defineProps({
    rollingQrEnabled: {
        type: Boolean,
        default: false
    },
    rollingQrInterval: {
        type: Number,
        default: 3
    },
    rollingQrToleranceWindows: {
        type: Number,
        default: 1
    }
})

const emit = defineEmits(['success', 'error'])

const form = ref({
    rolling_qr_enabled: props.rollingQrEnabled,
    rolling_qr_interval: props.rollingQrInterval,
    rolling_qr_tolerance_windows: props.rollingQrToleranceWindows,
})
const isSaving = ref(false)

const saveSettings = async () => {
    isSaving.value = true
    try {
        await axios.put(route('access-control.rolling-qr-settings.update'), form.value)
        emit('success', 'Rolling QR-Code Einstellungen wurden gespeichert.')
    } catch (error) {
        emit('error', error.response?.data?.message || 'Fehler beim Speichern der Einstellungen.')
    } finally {
        isSaving.value = false
    }
}
</script>
