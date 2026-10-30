<template>
    <div class="space-y-6">
        <!-- Activation -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <h3 class="text-xl font-semibold text-gray-900 flex items-center">
                        <Ticket class="w-6 h-6 text-indigo-600 mr-3 flex-none" />
                        Gästeportal
                    </h3>
                    <p class="mt-1 text-sm text-gray-500">
                        Laufkunden können sich im Gästeportal registrieren, Tageskarten, Mehrfachkarten und Solarium-Minuten kaufen und mit ihrem QR-Code einlösen.
                        Aufgeladenes Guthaben wird beim Kauf zuerst verrechnet.
                    </p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer flex-none">
                    <input
                        v-model="guestEnabled"
                        type="checkbox"
                        class="sr-only peer"
                        :disabled="loading || savingPortal"
                        @change="savePortal"
                    >
                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                </label>
            </div>

            <div v-if="guestEnabled && portalUrl" class="mt-4 flex flex-wrap items-center gap-2 text-sm">
                <span class="text-gray-500">Ihr Gästeportal:</span>
                <a :href="portalUrl" target="_blank" rel="noopener noreferrer" class="text-indigo-600 hover:text-indigo-800 break-all">
                    {{ portalUrl }}
                </a>
            </div>

            <div v-if="!mollieConfigured" class="mt-4 flex items-start gap-2 rounded-md bg-amber-50 border border-amber-200 px-3 py-2 text-sm text-amber-800">
                <AlertTriangle class="w-4 h-4 mt-0.5 flex-none" />
                <span>Mollie ist nicht eingerichtet. Gäste können dann nur mit aufgeladenem Guthaben bezahlen.</span>
            </div>
        </div>

        <!-- Products -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between gap-4 mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Produkte</h3>
                <button
                    v-if="!form"
                    type="button"
                    class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700"
                    @click="startCreate"
                >
                    <Plus class="w-4 h-4" />
                    Produkt anlegen
                </button>
            </div>

            <!-- Form -->
            <form v-if="form" class="mb-6 rounded-lg border border-gray-200 p-4 space-y-4" @submit.prevent="saveProduct">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Art</label>
                        <select
                            v-model="form.type"
                            :disabled="!!form.id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md disabled:bg-gray-50"
                        >
                            <option v-for="type in productTypes" :key="type.value" :value="type.value">{{ type.label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                        <input v-model="form.name" type="text" maxlength="255" required class="w-full px-3 py-2 border border-gray-300 rounded-md" :placeholder="namePlaceholder">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Preis (€)</label>
                        <input v-model="form.price" type="number" min="0.5" step="0.01" required class="w-full px-3 py-2 border border-gray-300 rounded-md">
                    </div>
                    <div v-if="form.type !== 'day_pass'">
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ form.type === 'solarium_minutes' ? 'Minuten' : 'Eintritte' }}</label>
                        <input v-model="form.quantity" type="number" min="1" max="1000" step="1" required class="w-full px-3 py-2 border border-gray-300 rounded-md">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Beschreibung (optional)</label>
                        <textarea v-model="form.description" rows="2" maxlength="1000" class="w-full px-3 py-2 border border-gray-300 rounded-md"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Reihenfolge</label>
                        <input v-model="form.sort_order" type="number" min="0" max="999" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                    </div>
                    <label class="flex items-center gap-2 self-end pb-2 text-sm text-gray-700">
                        <input v-model="form.active" type="checkbox" class="rounded border-gray-300 text-indigo-600">
                        Im Gästeportal anbieten
                    </label>
                </div>

                <p v-if="formError" class="text-sm text-red-600">{{ formError }}</p>

                <div class="flex justify-end gap-2">
                    <button type="button" class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50" @click="form = null">
                        Abbrechen
                    </button>
                    <button type="submit" :disabled="savingProduct" class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700 disabled:opacity-50">
                        <Loader2 v-if="savingProduct" class="w-4 h-4 animate-spin" />
                        Speichern
                    </button>
                </div>
            </form>

            <div v-if="loading" class="py-6 text-center text-sm text-gray-500">Wird geladen …</div>

            <p v-else-if="products.length === 0" class="py-6 text-center text-sm text-gray-500">
                Sie haben noch keine Produkte angelegt.
            </p>

            <ul v-else class="divide-y divide-gray-100">
                <li v-for="product in products" :key="product.id" class="flex items-center justify-between gap-4 py-3">
                    <div class="min-w-0">
                        <p class="font-medium text-gray-900 truncate">
                            {{ product.name }}
                            <span v-if="!product.active" class="ml-2 inline-flex px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-600">inaktiv</span>
                        </p>
                        <p class="text-sm text-gray-500">{{ product.type_label }} · {{ product.quantity_label }} · {{ formatCurrency(product.price) }}</p>
                    </div>
                    <button type="button" class="p-2 text-gray-400 hover:text-indigo-600" title="Bearbeiten" @click="startEdit(product)">
                        <Pencil class="w-4 h-4" />
                    </button>
                </li>
            </ul>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import { Ticket, Plus, Pencil, Loader2, AlertTriangle } from '@lucide/vue'
import { formatCurrency } from '@/utils/formatters'

const emit = defineEmits(['success', 'error'])

const productTypes = [
    { value: 'day_pass', label: 'Tageskarte' },
    { value: 'visit_card', label: 'Mehrfachkarte' },
    { value: 'solarium_minutes', label: 'Solarium-Minuten' },
]

const loading = ref(true)
const savingPortal = ref(false)
const savingProduct = ref(false)
const guestEnabled = ref(false)
const portalUrl = ref('')
const mollieConfigured = ref(true)
const products = ref([])
const form = ref(null)
const formError = ref('')

const namePlaceholder = computed(() => ({
    day_pass: 'z. B. Tageskarte',
    visit_card: 'z. B. 10er-Karte',
    solarium_minutes: 'z. B. Solarium 20 Minuten',
}[form.value?.type] ?? ''))

async function load() {
    loading.value = true
    try {
        const { data } = await axios.get(route('settings.guest-portal.index'))
        guestEnabled.value = data.guest_enabled
        portalUrl.value = data.guest_portal_url
        mollieConfigured.value = data.mollie_configured
        products.value = data.products
    } catch {
        emit('error', 'Die Einstellungen des Gästeportals konnten nicht geladen werden.')
    } finally {
        loading.value = false
    }
}

async function savePortal() {
    savingPortal.value = true
    try {
        const { data } = await axios.put(route('settings.guest-portal.update'), { guest_enabled: guestEnabled.value })
        guestEnabled.value = data.guest_enabled
        emit('success', data.message)
    } catch {
        guestEnabled.value = !guestEnabled.value
        emit('error', 'Die Einstellung konnte nicht gespeichert werden.')
    } finally {
        savingPortal.value = false
    }
}

function startCreate() {
    formError.value = ''
    form.value = { id: null, type: 'day_pass', name: '', description: '', price: '', quantity: 10, sort_order: 0, active: true }
}

function startEdit(product) {
    formError.value = ''
    form.value = { ...product, description: product.description ?? '' }
}

async function saveProduct() {
    savingProduct.value = true
    formError.value = ''

    const payload = {
        name: form.value.name,
        description: form.value.description || null,
        price: form.value.price,
        quantity: form.value.type === 'day_pass' ? null : form.value.quantity,
        sort_order: form.value.sort_order,
        active: form.value.active,
    }

    try {
        const { data } = form.value.id
            ? await axios.put(route('settings.guest-portal.products.update', form.value.id), payload)
            : await axios.post(route('settings.guest-portal.products.store'), { ...payload, type: form.value.type })

        const index = products.value.findIndex(product => product.id === data.product.id)
        if (index === -1) {
            products.value.push(data.product)
        } else {
            products.value[index] = data.product
        }

        form.value = null
        emit('success', data.message)
    } catch (e) {
        const errors = e.response?.data?.errors
        formError.value = errors ? Object.values(errors).flat()[0] : 'Das Produkt konnte nicht gespeichert werden.'
    } finally {
        savingProduct.value = false
    }
}

onMounted(load)
</script>
