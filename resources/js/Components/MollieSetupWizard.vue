<template>
    <div class="bg-gray-50 py-8">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="text-center mb-8">
                <h1 class="text-3xl font-bold text-gray-900">Mollie Payment Integration</h1>
                <p class="mt-2 text-gray-600">Richten Sie Ihre Zahlungsabwicklung in wenigen Schritten ein</p>
            </div>

            <!-- Progress Bar -->
            <div class="mb-8">
                <div class="flex items-center justify-center">
                    <div v-for="(step, index) in steps" :key="index" class="flex items-center">
                        <div class="flex items-center justify-center w-8 h-8 rounded-full text-sm font-medium"
                            :class="getStepClasses(index)">
                            {{ index + 1 }}
                        </div>
                        <div v-if="index < steps.length - 1" class="w-16 h-0.5 mx-2"
                            :class="currentStep > index ? 'bg-indigo-500' : 'bg-gray-300'"></div>
                    </div>
                </div>
                <div class="flex justify-center mt-2">
                    <span class="text-sm text-gray-600">{{ steps[currentStep]?.title }}</span>
                </div>
            </div>

            <!-- Main Content -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <!-- Step 1: Willkommen -->
                <div v-if="currentStep === 0" class="text-center">
                    <div class="mx-auto w-16 h-16 bg-indigo-100 rounded-full flex items-center justify-center mb-4">
                        <svg class="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1">
                            </path>
                        </svg>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">Willkommen bei der Mollie Integration</h2>
                    <p class="text-gray-600 mb-6 max-w-2xl mx-auto">
                        Mit Mollie können Sie ganz einfach Online-Zahlungen von Ihren Mitgliedern akzeptieren.
                        Dieser Assistent führt Sie durch die komplette Einrichtung.
                    </p>

                    <div v-if="isConfigured" class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
                        <div class="flex items-center justify-center">
                            <svg class="w-5 h-5 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd"
                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                    clip-rule="evenodd"></path>
                            </svg>
                            <span class="text-green-800 font-medium">Mollie ist bereits konfiguriert</span>
                        </div>
                        <p class="text-green-700 text-sm mt-2">Sie können die Konfiguration überprüfen oder ändern.</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
                        <div class="text-center p-4 border border-gray-200 rounded-lg">
                            <div class="w-12 h-12 bg-indigo-100 rounded-lg mx-auto mb-3 flex items-center justify-center">
                                <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <h3 class="font-semibold text-gray-900">Schnelle Einrichtung</h3>
                            <p class="text-sm text-gray-600">In wenigen Minuten einsatzbereit</p>
                        </div>
                        <div class="text-center p-4 border border-gray-200 rounded-lg">
                            <div
                                class="w-12 h-12 bg-green-100 rounded-lg mx-auto mb-3 flex items-center justify-center">
                                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z">
                                    </path>
                                </svg>
                            </div>
                            <h3 class="font-semibold text-gray-900">Sicher & Zuverlässig</h3>
                            <p class="text-sm text-gray-600">PCI-DSS konform und sicher</p>
                        </div>
                        <div class="text-center p-4 border border-gray-200 rounded-lg">
                            <div
                                class="w-12 h-12 bg-purple-100 rounded-lg mx-auto mb-3 flex items-center justify-center">
                                <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M13 10V3L4 14h7v7l9-11h-7z">
                                    </path>
                                </svg>
                            </div>
                            <h3 class="font-semibold text-gray-900">Viele Zahlungsarten</h3>
                            <p class="text-sm text-gray-600">iDEAL, Kreditkarte, PayPal & mehr</p>
                        </div>
                    </div>
                </div>

                <!-- Step 2: API-Schlüssel -->
                <div v-if="currentStep === 1">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">API-Schlüssel und OAuth Token</h2>
                    <p class="text-gray-600 mb-6">
                        Geben Sie Ihren Mollie API-Schlüssel und optional Ihr OAuth Token ein. Diese finden Sie in Ihrem
                        Mollie Dashboard unter
                        <a href="https://www.mollie.com/dashboard/developers/api-keys"
                            target="_blank"
                            class="text-indigo-600 hover:underline">API-Schlüssel</a>
                        und
                        <a href="https://my.mollie.com/dashboard/developers/organization-access-tokens"
                            target="_blank"
                            class="text-indigo-600 hover:underline">Unternehmenszugriffs-Tokens</a>.
                    </p>

                    <form @submit.prevent="validateApiKey" class="space-y-6">
                        <!-- API Key -->
                        <div>
                            <label for="api_key" class="block text-sm font-medium text-gray-700 mb-2">
                                API-Schlüssel
                            </label>
                            <div class="relative">
                                <input id="api_key" v-model="form.api_key" :type="showApiKey ? 'text' : 'password'"
                                    class="block w-full border border-gray-300 rounded-lg px-3 py-2 pr-10 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                    placeholder="live_... oder test_..." required>
                                <button type="button" @click="showApiKey = !showApiKey"
                                    class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                    <svg v-if="showApiKey" class="w-5 h-5 text-gray-400" fill="none"
                                        stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z">
                                        </path>
                                    </svg>
                                    <svg v-else class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21">
                                        </path>
                                    </svg>
                                </button>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">
                                Beginnt mit "live_" für Produktionsumgebung oder "test_" für Testumgebung<br>
                            </p>
                        </div>

                        <div class="flex items-center">
                            <input id="test_mode" v-model="form.test_mode" type="checkbox"
                                class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                            <label for="test_mode" class="ml-2 block text-sm text-gray-700">
                                Test-Modus aktivieren
                            </label>
                        </div>

                        <!-- OAuth Token -->
                        <div>
                            <label for="oauth_token" class="block text-sm font-medium text-gray-700 mb-2">
                                OAuth Token (optional)
                            </label>
                            <div class="relative">
                                <input id="oauth_token" v-model="form.oauth_token"
                                    :type="showOAuthToken ? 'text' : 'password'"
                                    class="block w-full border border-gray-300 rounded-lg px-3 py-2 pr-10 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                    placeholder="access_...">
                                <button type="button" @click="showOAuthToken = !showOAuthToken"
                                    class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                    <svg v-if="showOAuthToken" class="w-5 h-5 text-gray-400" fill="none"
                                        stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z">
                                        </path>
                                    </svg>
                                    <svg v-else class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21">
                                        </path>
                                    </svg>
                                </button>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">
                                Beginnt mit "access_". Für Webhook-Funktionalität erforderlich.
                            </p>
                        </div>

                        <!-- OAuth Token Hinweis -->
                        <div v-if="form.oauth_token" class="bg-indigo-50 border border-indigo-200 rounded-lg p-4">
                            <div class="flex">
                                <svg class="w-5 h-5 text-indigo-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                                        clip-rule="evenodd"></path>
                                </svg>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-indigo-800">OAuth Token Berechtigungen</h3>
                                    <p class="text-sm text-indigo-700 mt-1">
                                        Stellen Sie sicher, dass Ihr OAuth Token folgende Berechtigungen hat:
                                    </p>
                                    <ul class="text-sm text-indigo-700 mt-1 list-disc list-inside">
                                        <li>payment-links.read</li>
                                        <li>webhooks.read</li>
                                        <li>webhooks.write</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- Warnung wenn kein OAuth Token -->
                        <div v-if="!form.oauth_token" class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                            <div class="flex">
                                <svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                        clip-rule="evenodd"></path>
                                </svg>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-yellow-800">Eingeschränkte Funktionalität</h3>
                                    <p class="text-sm text-yellow-700 mt-1">
                                        Ohne OAuth Token sind Webhook-Erstellung und -Tests nicht verfügbar.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div v-if="apiValidationError" class="bg-red-50 border border-red-200 rounded-lg p-4">
                            <div class="flex">
                                <svg class="w-5 h-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                                        clip-rule="evenodd"></path>
                                </svg>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-red-800">Validierung fehlgeschlagen</h3>
                                    <p class="text-sm text-red-700 mt-1">{{ apiValidationError }}</p>
                                </div>
                            </div>
                        </div>

                        <div v-if="availableMethods.length > 0"
                            class="bg-green-50 border border-green-200 rounded-lg p-4">
                            <div class="flex">
                                <svg class="w-5 h-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                        clip-rule="evenodd"></path>
                                </svg>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-green-800">Anmeldeinformationen validiert</h3>
                                    <p class="text-sm text-green-700 mt-1">{{ availableMethods.length }} Zahlungsmethoden verfügbar</p>
                                </div>
                            </div>
                        </div>

                    </form>
                </div>

                <!-- Step 3: Zahlungsmethoden -->
                <div v-if="currentStep === 2">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">Zahlungsmethoden auswählen</h2>
                    <p class="text-gray-600 mb-6">
                        Wählen Sie die Zahlungsmethoden aus, die Sie Ihren Mitgliedern anbieten möchten.
                    </p>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div v-for="method in availableMethods" :key="method.id"
                            class="border border-gray-200 rounded-lg p-4 cursor-pointer transition-all hover:border-indigo-300"
                            :class="form.enabled_methods.includes(method.id) ? 'border-indigo-500 bg-indigo-50' : ''"
                            @click="togglePaymentMethod(method.id)">
                            <div class="flex items-center">
                                <input :id="method.id" :checked="form.enabled_methods.includes(method.id)"
                                    type="checkbox"
                                    class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded pointer-events-none">
                                <div class="ml-3 flex-1">
                                    <div class="flex items-center">
                                        <div class="w-8 h-8 bg-gray-100 rounded mr-2 flex items-center justify-center">
                                            <img :src="method.image.size1x" class="h-8 w-8 object-contain" />
                                        </div>
                                        <div>
                                            <label :for="method.id"
                                                class="text-sm font-medium text-gray-900 cursor-pointer">
                                                {{ method.description }}
                                            </label>
                                            <p v-if="method.pricing" class="text-xs text-gray-500">
                                                {{ formatPricing(method.pricing) }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="form.enabled_methods.length === 0"
                        class="mt-4 bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                        <div class="flex">
                            <svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd"
                                    d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                    clip-rule="evenodd"></path>
                            </svg>
                            <div class="ml-3">
                                <p class="text-sm text-yellow-800">Bitte wählen Sie mindestens eine Zahlungsmethode aus.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 4: Konfiguration -->
                <div v-if="currentStep === 3">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">Konfiguration anpassen</h2>
                    <p class="text-gray-600 mb-6">
                        Passen Sie die Einstellungen nach Ihren Wünschen an.
                    </p>

                    <form class="space-y-6">
                        <div>
                            <label for="description_prefix" class="block text-sm font-medium text-gray-700 mb-2">
                                Zahlungsbeschreibung Präfix
                            </label>
                            <input id="description_prefix" v-model="form.description_prefix" type="text"
                                class="block w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                :placeholder="organization.name">
                            <p class="text-xs text-gray-500 mt-1">
                                Wird bei jeder Zahlung als Präfix verwendet (z.B. "{{ form.description_prefix || organization.name }} - Mitgliedsbeitrag")
                            </p>
                        </div>

                        <div>
                            <label for="redirect_url" class="block text-sm font-medium text-gray-700 mb-2">
                                Weiterleitungs-URL (optional)
                            </label>
                            <input id="redirect_url" v-model="form.redirect_url" type="url"
                                class="block w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                placeholder="https://...">
                            <p class="text-xs text-gray-500 mt-1">
                                URL, zu der Kunden nach der Zahlung weitergeleitet werden (Standard: Mitgliederbereich)
                            </p>
                        </div>

                        <div v-if="form.oauth_token">
                            <label for="webhook_url" class="block text-sm font-medium text-gray-700 mb-2">
                                Webhook-URL (automatisch generiert)
                            </label>
                            <input id="webhook_url" :value="webhookUrl" type="url"
                                class="block w-full bg-gray-50 border border-gray-300 rounded-lg px-3 py-2 text-gray-600"
                                readonly>
                            <p class="text-xs text-gray-500 mt-1">
                                Diese URL wird automatisch in Mollie als Webhook konfiguriert
                            </p>
                        </div>
                    </form>
                </div>

                <!-- Step 5: Test & Abschluss -->
                <div v-if="currentStep === 4">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">Test & Abschluss</h2>
                    <p class="text-gray-600 mb-6">
                        Testen Sie Ihre Integration und schließen Sie die Einrichtung ab.
                    </p>

                    <div class="space-y-6">
                        <!-- Konfigurationsübersicht (erweitert) -->
                        <div class="bg-gray-50 rounded-lg p-4">
                            <h3 class="font-semibold text-gray-900 mb-3">Konfigurationsübersicht</h3>
                            <dl class="grid grid-cols-1 gap-2 text-sm">
                                <div class="flex justify-between">
                                    <dt class="text-gray-600">Modus:</dt>
                                    <dd class="font-medium"
                                        :class="form.test_mode ? 'text-yellow-600' : 'text-green-600'">
                                        {{ form.test_mode ? 'Test' : 'Live' }}
                                    </dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-gray-600">OAuth Token:</dt>
                                    <dd class="font-medium"
                                        :class="form.oauth_token ? 'text-green-600' : 'text-yellow-600'">
                                        {{ form.oauth_token ? 'Konfiguriert' : 'Nicht konfiguriert' }}
                                    </dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-gray-600">Zahlungsmethoden:</dt>
                                    <dd class="font-medium text-gray-900">{{ form.enabled_methods.length }} ausgewählt
                                    </dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-gray-600">Beschreibung:</dt>
                                    <dd class="font-medium text-gray-900">{{ form.description_prefix ||
                                        organization.name }}</dd>
                                </div>
                            </dl>
                        </div>

                        <!-- Hinweis wenn Konfiguration nicht gespeichert -->
                        <div v-if="!isConfigSaved" class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                            <div class="flex">
                                <svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                        clip-rule="evenodd"></path>
                                </svg>
                                <div class="ml-3">
                                    <p class="text-sm text-yellow-800">
                                        Tests sind erst verfügbar, nachdem die Konfiguration gespeichert wurde.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Test-Bereich (erweitert) -->
                        <div class="border border-gray-200 rounded-lg p-4">
                            <h3 class="font-semibold text-gray-900 mb-3">Integration testen</h3>
                            <p class="text-gray-600 mb-4">
                                Führen Sie eine Test-Zahlung durch, um sicherzustellen, dass alles korrekt funktioniert.
                            </p>

                            <button @click="testIntegration" :disabled="isTestingIntegration || !isConfigSaved"
                                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-lg text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed">
                                <svg v-if="isTestingIntegration" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white"
                                    fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                        stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor"
                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                    </path>
                                </svg>
                                {{ isTestingIntegration ? 'Teste...' : 'Test-Zahlung starten' }}
                            </button>

                            <div v-if="testResult" class="mt-4 p-4 rounded-lg"
                                :class="testResult.success ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'">
                                <div class="flex">
                                    <svg v-if="testResult.success" class="w-5 h-5 text-green-400" fill="currentColor"
                                        viewBox="0 0 20 20">
                                        <path fill-rule="evenodd"
                                            d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                            clip-rule="evenodd"></path>
                                    </svg>
                                    <svg v-else class="w-5 h-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd"
                                            d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                                            clip-rule="evenodd"></path>
                                    </svg>
                                    <div class="ml-3">
                                        <h4 class="text-sm font-medium"
                                            :class="testResult.success ? 'text-green-800' : 'text-red-800'">
                                            {{ testResult.success ? 'Test erfolgreich' : 'Test fehlgeschlagen' }}
                                        </h4>
                                        <p class="text-sm mt-1"
                                            :class="testResult.success ? 'text-green-700' : 'text-red-700'">
                                            {{ testResult.message }}
                                        </p>
                                        <div v-if="testResult.success && testResult.payment_url" class="mt-2">
                                            <a :href="testResult.payment_url" target="_blank"
                                                class="text-sm text-indigo-600 hover:underline">
                                                Test-Zahlung öffnen →
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Webhook-Status (erweiterte Bedingungen) -->
                        <div v-if="form.oauth_token" class="border border-gray-200 rounded-lg p-4">
                            <h3 class="font-semibold text-gray-900 mb-3">Webhook-Status</h3>
                            <button @click="checkWebhookStatus" :disabled="isCheckingWebhook || !isConfigSaved"
                                class="inline-flex items-center px-3 py-2 border border-gray-300 text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50">
                                <svg v-if="isCheckingWebhook" class="animate-spin -ml-1 mr-2 h-4 w-4 text-gray-600"
                                    fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                        stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor"
                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                    </path>
                                </svg>
                                {{ isCheckingWebhook ? 'Prüfe...' : 'Webhook-Status prüfen' }}
                            </button>

                            <div v-if="webhookStatus" class="mt-4 p-4 rounded-lg"
                                :class="webhookStatus.success ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'">
                                <div class="flex">
                                    <svg v-if="webhookStatus.success" class="w-5 h-5 text-green-400" fill="currentColor"
                                        viewBox="0 0 20 20">
                                        <path fill-rule="evenodd"
                                            d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                            clip-rule="evenodd"></path>
                                    </svg>
                                    <svg v-else class="w-5 h-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd"
                                            d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                                            clip-rule="evenodd"></path>
                                    </svg>
                                    <div class="ml-3">
                                        <h4 class="text-sm font-medium"
                                            :class="webhookStatus.success ? 'text-green-800' : 'text-red-800'">
                                            {{ webhookStatus.success ? 'Webhook aktiv' : 'Webhook-Problem' }}
                                        </h4>
                                        <p class="text-sm mt-1"
                                            :class="webhookStatus.success ? 'text-green-700' : 'text-red-700'">
                                            {{ webhookStatus.message }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Hinweis wenn kein OAuth Token für Webhooks -->
                        <div v-else class="border border-gray-200 rounded-lg p-4 bg-gray-50">
                            <h3 class="font-semibold text-gray-900 mb-3">Webhook-Status</h3>
                            <p class="text-sm text-gray-600">
                                Webhook-Tests sind nur mit OAuth Token verfügbar. Ohne OAuth Token können keine Webhooks
                                erstellt oder getestet werden.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Buttons -->
                <div class="flex justify-between items-center mt-8 pt-6 border-t border-gray-200">
                    <button v-if="currentStep > 0" @click="previousStep"
                        class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7">
                            </path>
                        </svg>
                        Zurück
                    </button>
                    <div v-else></div>

                    <div class="flex space-x-3">
                        <button v-if="currentStep < steps.length - 1" @click="nextStep" :disabled="!canProceed"
                            class="inline-flex items-center px-6 py-2 border border-transparent text-sm font-medium rounded-lg text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed">
                            {{ getNextButtonText() }}
                            <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7">
                                </path>
                            </svg>
                        </button>
                        <button v-else @click="saveConfiguration" :disabled="isSaving || isConfigSaved"
                            class="inline-flex items-center px-6 py-2 border border-transparent text-sm font-medium rounded-lg text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 disabled:opacity-50 disabled:cursor-not-allowed">
                            <svg v-if="isSaving" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none"
                                viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                    stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                </path>
                            </svg>
                            {{ 'Konfiguration ' + (isConfigSaved ? 'gespeichert' : 'speichern') }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Success Modal -->
            <div v-if="showSuccessModal" class="fixed inset-0 overflow-y-auto h-full w-full z-50" @click="closeSuccessModal">
                <div class="relative top-20 mx-auto p-5 border border-gray-50 w-96 shadow-lg rounded-md bg-white" @click.stop>
                    <div class="mt-3 text-center">
                        <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-green-100">
                            <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                        </div>
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mt-4">Setup erfolgreich abgeschlossen!</h3>
                        <div class="mt-2 px-7 py-3">
                            <p class="text-sm text-gray-500">
                                Mollie wurde erfolgreich konfiguriert. Sie können jetzt Online-Zahlungen von Ihren
                                Mitgliedern entgegennehmen.
                            </p>
                        </div>
                        <div class="items-center px-4 py-3">
                            <button @click="closeSuccessModal"
                                class="px-4 py-2 bg-green-500 text-white text-base font-medium rounded-md w-full shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-green-300">
                                Schließen
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'MollieSetupWizard',
    props: {
        organization: {
            type: Object,
            required: true
        },

        watch: {
            'form.api_key'(newVal, oldVal) {
                if (newVal !== oldVal && this.availableMethods.length > 0) {
                    // API key has been changed, reset validation
                    this.availableMethods = [];
                    this.form.enabled_methods = [];
                    this.apiValidationError = null;
                }
            },
            'form.oauth_token'(newVal, oldVal) {
                if (newVal !== oldVal) {
                    this.webhookStatus = null;
                }
            }
        }
    },
    data() {
        return {
            currentStep: 0,
            showApiKey: false,
            showOAuthToken: false,
            isTestingIntegration: false,
            isCheckingWebhook: false,
            isSaving: false,
            isConfigSaved: false,
            showSuccessModal: false,

            steps: [
                { title: 'Willkommen', key: 'welcome' },
                { title: 'API-Schlüssel', key: 'api_key' },
                { title: 'Zahlungsmethoden', key: 'payment_methods' },
                { title: 'Konfiguration', key: 'configuration' },
                { title: 'Test & Abschluss', key: 'finish' }
            ],

            form: {
                api_key: '',
                oauth_token: '',
                test_mode: true,
                enabled_methods: [],
                description_prefix: '',
                redirect_url: '',
                webhook_url: ''
            },

            availableMethods: [],
            apiValidationError: null,
            testResult: null,
            webhookStatus: null
        }
    },

    computed: {
        isConfigured() {
            return this.organization.mollie_config && this.organization.mollie_config.api_key;
        },

        webhookUrl() {
            return `${window.location.origin}/api/v1/public/mollie/webhook`;
        },

        canProceed() {
            switch (this.currentStep) {
                case 0:
                    return true;
                case 1:
                    return this.form.api_key.length > 0; // Just check if API key has been entered
                case 2:
                    return this.form.enabled_methods.length > 0;
                case 3:
                    return true;
                case 4:
                    return true;
                default:
                    return false;
            }
        },

        isApiValidated() {
            return this.availableMethods.length > 0 && this.form.api_key;
        }
    },

    mounted() {
        this.loadExistingConfiguration();
    },

    methods: {
        async loadExistingConfiguration() {
            if (this.organization.mollie_config) {
                this.form = { ...this.form, ...this.organization.mollie_config };
                if (this.form.api_key) {
                    this.validateApiKey();
                }
            }
        },

        getStepClasses(index) {
            if (index < this.currentStep) {
                return 'bg-indigo-500 text-white';
            } else if (index === this.currentStep) {
                return 'bg-indigo-100 text-indigo-600 border-2 border-indigo-500';
            } else {
                return 'bg-gray-200 text-gray-600';
            }
        },

        nextStep() {
            if (this.currentStep === 1 && !this.isApiValidated) {
                // In step 1: First validate API before proceeding
                this.validateApiKey();
                return;
            }

            if (this.canProceed && this.currentStep < this.steps.length - 1) {
                this.currentStep++;
            }
        },

        previousStep() {
            if (this.currentStep > 0) {
                this.currentStep--;
            }
        },

        getNextButtonText() {
            switch (this.currentStep) {
                case 0:
                    return 'Loslegen';
                case 1:
                    return this.isApiValidated ? 'Weiter' : 'API validieren';
                default:
                    return 'Weiter';
            }
        },

        async validateApiKey() {
            if (!this.form.api_key) return;

            this.apiValidationError = null;
            this.availableMethods = [];

            if (!this.form.api_key || this.form.api_key.length < 30) {
                this.apiValidationError = 'Der API-Schlüssel muss mindestens 30 Zeichen lang sein.';
                return;
            }

            if (this.form.oauth_token && !this.form.oauth_token.startsWith('access_')) {
                this.apiValidationError = 'Das OAuth Token muss mit "access_" beginnen.';
                return;
            }

            try {
                const response = await axios.post(route('v1.mollie.validate-credentials'), {
                    api_key: this.form.api_key,
                    oauth_token: this.form.oauth_token,
                    test_mode: this.form.test_mode,
                });

                const data = response.data;

                if (data.success) {
                    this.availableMethods = data.methods || [];
                    this.form.enabled_methods = this.availableMethods.map(m => m.id);
                    if (this.currentStep === 1) {
                        setTimeout(() => this.currentStep++, 500);
                    }
                } else {
                    this.apiValidationError = data.message || 'Validierung fehlgeschlagen';
                }
            } catch (error) {
                this.apiValidationError = 'Fehler bei der Validierung: ' + error.message;
            }
        },

        togglePaymentMethod(methodId) {
            const index = this.form.enabled_methods.indexOf(methodId);
            if (index > -1) {
                this.form.enabled_methods.splice(index, 1);
            } else {
                this.form.enabled_methods.push(methodId);
            }
        },

        formatPricing(pricing) {
            if (!pricing || pricing.length === 0) return '';

            const fixed = pricing.find(p => p.feeRegion === 'other')?.fixed?.value || 0;
            const variable = pricing.find(p => p.feeRegion === 'other')?.variable || 0;

            if (fixed && variable) {
                return `€${(fixed / 100).toFixed(2)} + ${variable}%`;
            } else if (fixed) {
                return `€${(fixed / 100).toFixed(2)}`;
            } else if (variable) {
                return `${variable}%`;
            }
            return '';
        },

        async testIntegration() {
            if (!this.isConfigSaved) return;

            this.isTestingIntegration = true;
            this.testResult = null;

            try {
                const response = await axios.post(route('v1.mollie.test-integration'), {
                    organization_id: this.organization.id
                });

                const data = response.data;
                this.testResult = data;
            } catch (error) {
                this.testResult = {
                    success: false,
                    message: 'Fehler beim Testen: ' + error.message
                };
            } finally {
                this.isTestingIntegration = false;
            }
        },

        async checkWebhookStatus() {
            if (!this.isConfigSaved || !this.form.oauth_token) return;

            this.isCheckingWebhook = true;
            this.webhookStatus = null;

            try {
                const response = await axios.post(route('v1.mollie.check-webhook-status'), {
                    organization_id: this.organization.id
                });

                const data = response.data;
                this.webhookStatus = data;
            } catch (error) {
                this.webhookStatus = {
                    success: false,
                    message: 'Fehler beim Prüfen: ' + error.message
                };
            } finally {
                this.isCheckingWebhook = false;
            }
        },

        async saveConfiguration() {
            this.isSaving = true;

            try {
                const configData = {
                    ...this.form,
                    webhook_url: this.form.oauth_token ? this.webhookUrl : null,
                    organization_id: this.organization.id
                };

                const response = await axios.post(route('v1.mollie.save-config'), configData);

                const data = response.data;

                if (data.success) {
                    this.isConfigSaved = true;
                    this.showSuccessModal = true;
                    this.$emit('configuration-saved', configData);
                } else {
                    alert('Fehler beim Speichern: ' + (data.message || 'Unbekannter Fehler'));
                }
            } catch (error) {
                alert('Fehler beim Speichern: ' + error.message);
            } finally {
                this.isSaving = false;
            }
        },

        closeSuccessModal() {
            this.showSuccessModal = false;
            this.$emit('setup-completed');
        }
    }
}
</script>
