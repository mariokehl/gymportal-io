<!-- InkassoSettingsWidget.vue -->
<template>
    <div class="space-y-6">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow-sm p-6 flex flex-wrap items-end gap-6">
            <div class="flex-1 min-w-[280px]">
                <div class="text-lg font-semibold text-gray-900">Inkasso</div>
                <div class="text-sm text-gray-500 mt-0.5">
                    Inkassopartner, Übergaberegeln, Mahnstufen und Gebühren
                </div>
            </div>
        </div>

        <!-- Partner not active: selection cards -->
        <div v-if="!settings.active" class="space-y-5">
            <div class="flex gap-3 p-4 rounded-lg bg-indigo-50 border border-indigo-200 text-indigo-800">
                <div class="text-sm">
                    <div class="font-semibold">Kein Inkassopartner aktiv</div>
                    <div>
                        Solange kein Partner aktiv ist, können keine Inkassofälle übertragen werden.
                        Aktiviere einen Partner, um Inkassoläufe zu starten.
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                <div class="bg-white rounded-lg shadow-sm p-6 flex flex-col gap-3.5 border border-indigo-200">
                    <div class="flex items-center gap-3.5">
                        <div class="w-11 h-11 rounded-lg bg-gray-900 text-white flex items-center justify-center font-bold">
                            DG
                        </div>
                        <div>
                            <div class="text-lg font-semibold text-gray-900">DIAGONAL Inkasso</div>
                            <div class="text-sm text-gray-500">Forderungsmanagement · DIAGONAL Gruppe</div>
                        </div>
                    </div>
                    <p class="text-sm text-gray-700">
                        Modernes Forderungsmanagement mit empathischer Schuldneransprache. Automatisierte
                        Übergabe offener Forderungen, laufende Statusrückmeldungen im Mitgliederkonto.
                    </p>
                    <div class="flex flex-col gap-1.5 text-xs text-gray-600">
                        <div>· Automatisierte Übergabe aus dem Inkassolauf</div>
                        <div>· Statusrückmeldungen und Zahlungseingänge je Akte</div>
                        <div>· Einrichtung mit Mandanten-ID, Benutzername und Passwort</div>
                    </div>
                    <button
                        type="button"
                        class="mt-auto px-4 py-2.5 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors"
                        @click="showSetup = true"
                    >
                        Jetzt aktivieren
                    </button>
                </div>

                <div class="bg-white rounded-lg shadow-sm p-6 flex flex-col gap-3.5 opacity-55">
                    <div class="flex items-center gap-3.5">
                        <div class="w-11 h-11 rounded-lg bg-gray-200 text-gray-500 flex items-center justify-center font-bold">
                            –
                        </div>
                        <div>
                            <div class="text-lg font-semibold text-gray-900">Weitere Inkassopartner</div>
                            <div class="text-sm text-gray-500">Bald verfügbar</div>
                        </div>
                    </div>
                    <p class="text-sm text-gray-700">
                        Zusätzliche Partnerintegrationen folgen. Für Partner ohne Integration steht das
                        manuelle Verfahren über den Detailexport eines Inkassolaufs zur Verfügung.
                    </p>
                    <div class="mt-auto px-4 py-2.5 bg-gray-100 text-gray-400 rounded-lg text-sm font-medium text-center cursor-not-allowed">
                        Nicht verfügbar
                    </div>
                </div>
            </div>
        </div>

        <!-- Partner active: full configuration -->
        <div v-else class="space-y-5">
            <div class="flex flex-wrap gap-3 p-4 rounded-lg bg-green-100 border border-green-200 items-center justify-between">
                <div class="flex gap-3 items-center">
                    <CheckCircle2 class="w-5 h-5 text-green-500 flex-none" />
                    <div class="text-green-800 text-sm">
                        <div class="font-semibold">DIAGONAL Inkasso aktiv</div>
                        <div>
                            <template v-if="settings.activated_at">Aktiviert am {{ formatDate(settings.activated_at) }} · </template>
                            Mandanten-ID {{ settings.tenant_id }}
                        </div>
                    </div>
                </div>
                <Link :href="route('finances.inkasso.index')" class="text-sm text-green-800 font-semibold whitespace-nowrap">
                    Zu den Inkassoläufen →
                </Link>
            </div>

            <!-- Interface credentials -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="text-lg font-semibold text-gray-900 mb-1">Stammdaten der Schnittstelle</div>
                <div class="text-sm text-gray-500 mb-5">
                    Diese Daten erhältst du von DIAGONAL. Sie gelten für diese Organisation.
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Mandanten-ID</label>
                        <input v-model="form.tenant_id" type="text" class="input-field" >
                        <p v-if="errors.tenant_id" class="mt-1 text-sm text-red-600">{{ errors.tenant_id }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Gläubigernummer</label>
                        <input v-model="form.client_number" type="text" maxlength="5" class="input-field" >
                        <p class="text-xs text-gray-400 mt-1.5">Genau 5 Zeichen, von DIAGONAL vergeben.</p>
                        <p v-if="errors.client_number" class="mt-1 text-sm text-red-600">{{ errors.client_number }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Benutzername</label>
                        <input v-model="form.username" type="text" class="input-field" >
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Passwort</label>
                        <input
                            v-model="form.password"
                            type="password"
                            :placeholder="settings.has_password ? '•••••••••• (gespeichert)' : ''"
                            autocomplete="new-password"
                            class="input-field"
                        >
                        <p class="text-xs text-gray-400 mt-1.5">Verschlüsselt gespeichert. Zum Ändern neu eingeben.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Gläubigername auf der Akte</label>
                        <input v-model="form.creditor_name" type="text" class="input-field" >
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Ansprechpartner beim Studio</label>
                        <input v-model="form.contact" type="text" class="input-field" >
                    </div>
                </div>
                <div class="mt-5 flex items-center gap-3">
                    <button
                        type="button"
                        :disabled="testing"
                        class="px-3.5 py-2.5 bg-white border border-gray-300 text-gray-700 rounded-md text-sm font-medium hover:bg-gray-50 transition-colors disabled:opacity-60"
                        @click="testConnection"
                    >
                        {{ testing ? 'Prüfe Verbindung …' : 'Verbindung testen' }}
                    </button>
                    <span v-if="testResult" :class="testResult.success ? 'text-green-700' : 'text-red-600'" class="text-sm">
                        {{ testResult.message }}
                    </span>
                </div>
            </div>

            <!-- Handover rules -->
            <div class="bg-white rounded-lg shadow-sm p-6 space-y-5">
                <div>
                    <div class="text-lg font-semibold text-gray-900">Übergabe &amp; Rückläufe</div>
                    <div class="text-sm text-gray-500">
                        Regelt, welche Forderungen übergeben werden und was bei Rückmeldungen des Partners passiert.
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Mindestbetrag für Übergabe</label>
                        <input v-model="form.min_amount" type="number" step="0.01" min="0" class="input-field" >
                        <p class="text-xs text-gray-400 mt-1.5">Akten unter diesem Betrag werden vom Partner abgelehnt.</p>
                        <p v-if="errors.min_amount" class="mt-1 text-sm text-red-600">{{ errors.min_amount }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Minderjährige Mitglieder</label>
                        <ToggleRow v-model="form.include_minors" label="In Inkassoläufe einbeziehen" />
                    </div>
                </div>

                <div>
                    <div class="text-sm font-medium text-gray-700 mb-2.5">Umgang mit Restforderungen bei Ablehnung</div>
                    <div class="flex flex-col gap-2.5">
                        <RadioCard
                            :selected="form.residual_handling === 'always_write_off'"
                            title="Immer ausbuchen"
                            description="Alle verbleibenden unbezahlten Forderungen werden automatisch ausgebucht, sobald der Partner einen Fall als abgelehnt oder zurückgebucht markiert. Empfohlen."
                            @select="form.residual_handling = 'always_write_off'"
                        />
                        <RadioCard
                            :selected="form.residual_handling === 'partner_decision'"
                            title="Nach Entscheidung des Inkassopartners"
                            description="Verbleibende Forderungen werden nur ausgebucht, wenn DIAGONAL dies in der Rückmeldung angibt."
                            @select="form.residual_handling = 'partner_decision'"
                        />
                    </div>
                </div>

                <div>
                    <div class="text-sm font-medium text-gray-700 mb-2.5">Automatische Übermittlung bei Forderungsrückgaben</div>
                    <ToggleRow
                        v-model="form.auto_resubmit"
                        label="Zurückgegebene Forderungen automatisch erneut an DIAGONAL übermitteln"
                    />
                </div>
            </div>

            <!-- Dunning levels -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="text-lg font-semibold text-gray-900 mb-1">Mahnstufen &amp; Gebühren</div>
                <div class="text-sm text-gray-500 mb-4">
                    Stufe 4 ist die Inkassoübergabe. Solange ein Mitglied im Inkasso ist, bleibt die Mahnstufe unverändert.
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full border border-gray-200 rounded-lg overflow-hidden">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="text-left px-4 py-2.5 text-xs font-medium uppercase tracking-wider text-gray-500">Stufe</th>
                                <th class="text-left px-3 py-2.5 text-xs font-medium uppercase tracking-wider text-gray-500">Auslöser</th>
                                <th class="text-left px-3 py-2.5 text-xs font-medium uppercase tracking-wider text-gray-500">Gebühr</th>
                                <th class="text-left px-4 py-2.5 text-xs font-medium uppercase tracking-wider text-gray-500">Wirkung</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(level, index) in form.levels" :key="level.level" class="border-t border-gray-100">
                                <td class="px-4 py-3 text-sm font-semibold text-gray-900">
                                    {{ level.level === 4 ? 'Stufe 4 · Inkasso' : `Stufe ${level.level}` }}
                                </td>
                                <td class="px-3 py-3">
                                    <div v-if="level.level === 4" class="text-sm text-gray-600">manuell über Inkassolauf</div>
                                    <div v-else class="flex items-center gap-2">
                                        <input
                                            v-model.number="form.levels[index].trigger_days"
                                            type="number"
                                            min="0"
                                            class="w-20 px-2.5 py-1.5 border border-gray-300 rounded-md text-sm"
                                        >
                                        <span class="text-sm text-gray-600">
                                            {{ level.level === 1 ? 'Tage nach Fälligkeit' : `Tage nach Stufe ${level.level - 1}` }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-3 py-3">
                                    <input
                                        v-model.number="form.levels[index].fee"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        class="w-28 px-2.5 py-1.5 border border-gray-300 rounded-md text-sm"
                                    >
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-500">{{ level.effect }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mt-5">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Übergabepauschale Inkasso</label>
                        <input v-model="form.handover_flat_fee" type="number" step="0.01" min="0" class="input-field" >
                        <p class="text-xs text-gray-400 mt-1.5">Wird dem Mitglied bei der Übergabe als Forderung hinzugefügt.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Verzugszinsen p. a. (%)</label>
                        <input v-model="form.default_interest_rate" type="number" step="0.01" min="0" max="100" class="input-field" >
                        <p class="text-xs text-gray-400 mt-1.5">Berechnung durch den Partner ab Übergabedatum.</p>
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-2.5">
                <button
                    type="button"
                    class="px-4 py-2.5 bg-white border border-gray-300 text-gray-700 rounded-md text-sm font-medium hover:bg-gray-50 transition-colors"
                    @click="deactivate"
                >
                    Inkassopartner deaktivieren
                </button>
                <button
                    type="button"
                    :disabled="saving"
                    class="px-5 py-2.5 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors disabled:opacity-60"
                    @click="save"
                >
                    {{ saving ? 'Speichert …' : 'Speichern' }}
                </button>
            </div>
        </div>

        <InkassoSetupWizard
            v-if="showSetup"
            @close="showSetup = false"
            @activated="onActivated"
        />
    </div>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import { CheckCircle2 } from 'lucide-vue-next'
import InkassoSetupWizard from '@/Components/Inkasso/InkassoSetupWizard.vue'
import ToggleRow from '@/Components/Inkasso/ToggleRow.vue'
import RadioCard from '@/Components/Inkasso/RadioCard.vue'
import { formatDate } from '@/utils/formatters'

const props = defineProps({
    currentGym: { type: Object, required: true },
})

const emit = defineEmits(['success', 'error'])

// `inkasso` is the sanitised accessor; the raw column is hidden server-side.
const settings = ref(props.currentGym.inkasso ?? { active: false })
const showSetup = ref(false)
const saving = ref(false)
const testing = ref(false)
const testResult = ref(null)
const errors = reactive({})

const form = reactive(buildForm(settings.value))

function buildForm(source) {
    return {
        tenant_id: source.tenant_id ?? '',
        client_number: source.client_number ?? '',
        username: source.username ?? '',
        // Empty means "keep the stored password".
        password: '',
        creditor_name: source.creditor_name ?? '',
        contact: source.contact ?? '',
        min_amount: Number(source.min_amount ?? 10),
        include_minors: Boolean(source.include_minors),
        residual_handling: source.residual_handling ?? 'always_write_off',
        auto_resubmit: Boolean(source.auto_resubmit ?? true),
        handover_flat_fee: Number(source.handover_flat_fee ?? 58.5),
        default_interest_rate: Number(source.default_interest_rate ?? 5),
        levels: (source.levels ?? []).map(level => ({ ...level })),
    }
}

const applySettings = next => {
    settings.value = next
    Object.assign(form, buildForm(next))
}

const load = async () => {
    try {
        const { data } = await axios.get(route('settings.inkasso.index'))
        applySettings(data.settings)
    } catch {
        emit('error', 'Inkasso-Einstellungen konnten nicht geladen werden.')
    }
}

const save = async () => {
    saving.value = true
    Object.keys(errors).forEach(key => delete errors[key])

    try {
        const { data } = await axios.put(route('settings.inkasso.update'), form)
        applySettings(data.settings)
        emit('success', data.message)
    } catch (error) {
        if (error.response?.status === 422) {
            Object.assign(errors, flattenErrors(error.response.data.errors))
            emit('error', 'Bitte prüfe die markierten Felder.')
        } else {
            emit('error', 'Die Inkasso-Einstellungen konnten nicht gespeichert werden.')
        }
    } finally {
        saving.value = false
    }
}

const testConnection = async () => {
    testing.value = true
    testResult.value = null

    try {
        const { data } = await axios.post(route('settings.inkasso.test-connection'), {
            tenant_id: form.tenant_id,
            username: form.username,
            password: form.password,
        })
        testResult.value = data
    } catch (error) {
        testResult.value = error.response?.data ?? {
            success: false,
            message: 'Die Verbindung konnte nicht geprüft werden.',
        }
    } finally {
        testing.value = false
    }
}

const deactivate = async () => {
    if (!confirm('Inkassopartner wirklich deaktivieren? Es können dann keine neuen Fälle übertragen werden.')) {
        return
    }

    try {
        const { data } = await axios.post(route('settings.inkasso.deactivate'))
        applySettings(data.settings)
        emit('success', data.message)
    } catch {
        emit('error', 'Der Inkassopartner konnte nicht deaktiviert werden.')
    }
}

const onActivated = next => {
    showSetup.value = false
    applySettings(next)
    emit('success', 'DIAGONAL Inkasso aktiviert.')
}

// Laravel returns nested keys such as "levels.0.fee"; keep the first message per field.
function flattenErrors(source) {
    return Object.fromEntries(
        Object.entries(source ?? {}).map(([key, messages]) => [key, Array.isArray(messages) ? messages[0] : messages])
    )
}

load()
</script>

<style scoped>
@reference "tailwindcss";

.input-field {
    @apply w-full px-3 py-2.5 border border-gray-300 rounded-md text-sm focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600;
}
</style>
