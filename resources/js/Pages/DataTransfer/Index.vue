<template>
  <AppLayout title="Import/Export">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Datenimport/-export
      </h2>
    </template>

    <div class="py-6">
      <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <!-- Warning Banner -->
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
          <div class="flex">
            <AlertTriangle class="w-5 h-5 text-yellow-400 mr-3 flex-shrink-0 mt-0.5" />
            <div>
              <h3 class="text-sm font-medium text-yellow-800">Wichtige Hinweise</h3>
              <ul class="mt-2 text-sm text-yellow-700 list-disc list-inside space-y-1">
                <li v-for="item in sensitiveDataWarning.excluded" :key="item">
                  {{ item }} werden <strong>nicht</strong> exportiert
                </li>
                <li>Der "Ersetzen"-Modus löscht alle bestehenden Daten unwiderruflich</li>
              </ul>
            </div>
          </div>
        </div>

        <!-- Export Section -->
        <div class="bg-white shadow-sm rounded-lg overflow-hidden">
          <div class="px-4 py-5 sm:p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4 flex items-center">
              <Download class="w-5 h-5 mr-2 text-indigo-500" />
              Daten exportieren
            </h3>

            <p class="text-sm text-gray-600 mb-4">
              Exportieren Sie alle Daten Ihrer Organisation als JSON-Datei.
              Dies umfasst Mitglieder, Verträge, Kurse, Zahlungen und Einstellungen.
            </p>

            <!-- Export Statistics Preview -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
              <div class="bg-gray-50 p-3 rounded-lg">
                <p class="text-sm text-gray-500">Mitglieder</p>
                <p class="text-xl font-semibold">{{ exportStats.members_count }}</p>
              </div>
              <div class="bg-gray-50 p-3 rounded-lg">
                <p class="text-sm text-gray-500">Verträge</p>
                <p class="text-xl font-semibold">{{ exportStats.memberships_count }}</p>
              </div>
              <div class="bg-gray-50 p-3 rounded-lg">
                <p class="text-sm text-gray-500">Zahlungen</p>
                <p class="text-xl font-semibold">{{ exportStats.payments_count }}</p>
              </div>
              <div class="bg-gray-50 p-3 rounded-lg">
                <p class="text-sm text-gray-500">Kurse</p>
                <p class="text-xl font-semibold">{{ exportStats.courses_count }}</p>
              </div>
            </div>

            <button
              @click="handleExport"
              :disabled="isExporting"
              class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150 disabled:opacity-50"
            >
              <Download class="w-4 h-4 mr-2" />
              {{ isExporting ? 'Wird exportiert...' : 'Als JSON exportieren' }}
            </button>
          </div>
        </div>

        <!-- JSON Import Section -->
        <div class="bg-white shadow-sm rounded-lg overflow-hidden">
          <div class="px-4 py-5 sm:p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4 flex items-center">
              <Upload class="w-5 h-5 mr-2 text-green-500" />
              JSON-Import
            </h3>

            <!-- File Upload Area -->
            <div
              class="mt-2 flex justify-center rounded-lg border-2 border-dashed px-6 py-10 transition-colors"
              :class="isDragging ? 'border-indigo-500 bg-indigo-50' : 'border-gray-300'"
              @drop.prevent="handleDrop"
              @dragover.prevent="isDragging = true"
              @dragleave.prevent="isDragging = false"
            >
              <div class="text-center">
                <FileJson class="mx-auto h-12 w-12 text-gray-400" />
                <div class="mt-4 flex text-sm text-gray-600">
                  <label class="relative cursor-pointer rounded-md font-semibold text-indigo-600 hover:text-indigo-500">
                    <span>Datei auswählen</span>
                    <input
                      type="file"
                      class="sr-only"
                      accept=".json"
                      @change="handleFileSelect"
                      ref="fileInput"
                    >
                  </label>
                  <p class="pl-1">oder per Drag & Drop</p>
                </div>
                <p class="text-xs text-gray-500 mt-1">JSON-Datei bis zu 100 MB</p>
              </div>
            </div>

            <!-- Selected File Info -->
            <div v-if="selectedFile" class="mt-4 p-4 bg-gray-50 rounded-lg">
              <div class="flex items-center justify-between">
                <div class="flex items-center">
                  <FileJson class="w-8 h-8 text-indigo-500 mr-3" />
                  <div>
                    <p class="font-medium text-gray-900">{{ selectedFile.name }}</p>
                    <p class="text-sm text-gray-500">{{ formatFileSize(selectedFile.size) }}</p>
                  </div>
                </div>
                <button
                  @click="clearFile"
                  class="text-gray-400 hover:text-gray-600"
                >
                  <X class="w-5 h-5" />
                </button>
              </div>
            </div>

            <!-- Validation in progress -->
            <div v-if="isValidating" class="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
              <div class="flex items-center">
                <Loader2 class="w-5 h-5 text-blue-500 mr-3 animate-spin" />
                <span class="text-sm text-blue-700">Datei wird validiert...</span>
              </div>
            </div>

            <!-- Validation Results -->
            <div v-if="validationResult && !isValidating" class="mt-4">
              <div v-if="validationResult.valid" class="bg-green-50 border border-green-200 rounded-lg p-4">
                <h4 class="text-sm font-medium text-green-800 flex items-center">
                  <CheckCircle class="w-4 h-4 mr-2" />
                  Datei ist gültig
                </h4>
                <div class="mt-2 grid grid-cols-2 md:grid-cols-4 gap-2 text-sm text-green-700">
                  <span>Mitglieder: {{ validationResult.stats.members }}</span>
                  <span>Verträge: {{ validationResult.stats.memberships }}</span>
                  <span>Zahlungen: {{ validationResult.stats.payments }}</span>
                  <span>Kurse: {{ validationResult.stats.courses }}</span>
                </div>
                <div v-if="validationResult.warnings && validationResult.warnings.length > 0" class="mt-3 text-sm text-yellow-700">
                  <p class="font-medium">Warnungen:</p>
                  <ul class="list-disc list-inside">
                    <li v-for="warning in validationResult.warnings" :key="warning">{{ warning }}</li>
                  </ul>
                </div>
              </div>
              <div v-else class="bg-red-50 border border-red-200 rounded-lg p-4">
                <h4 class="text-sm font-medium text-red-800 flex items-center">
                  <XCircle class="w-4 h-4 mr-2" />
                  Validierungsfehler
                </h4>
                <ul class="mt-2 text-sm text-red-700 list-disc list-inside">
                  <li v-for="error in validationResult.errors" :key="error">{{ error }}</li>
                </ul>
              </div>
            </div>

            <!-- Import Mode Selection -->
            <div v-if="validationResult?.valid" class="mt-6">
              <label class="block text-sm font-medium text-gray-700 mb-3">Import-Modus</label>
              <div class="space-y-3">
                <label
                  class="flex items-start p-4 border rounded-lg cursor-pointer transition-colors"
                  :class="importMode === 'append' ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 hover:border-gray-300'"
                >
                  <input
                    type="radio"
                    v-model="importMode"
                    value="append"
                    class="mt-1 mr-3 text-indigo-600 focus:ring-indigo-500"
                  >
                  <div>
                    <span class="font-medium text-gray-900">Hinzufügen (Append)</span>
                    <p class="text-sm text-gray-500">
                      Neue Datensätze werden hinzugefügt. Bestehende Daten bleiben erhalten.
                      Duplikate (gleiche E-Mail) werden übersprungen.
                    </p>
                  </div>
                </label>
                <label
                  class="flex items-start p-4 border rounded-lg cursor-pointer transition-colors"
                  :class="importMode === 'replace' ? 'border-red-500 bg-red-50' : 'border-gray-200 hover:border-gray-300'"
                >
                  <input
                    type="radio"
                    v-model="importMode"
                    value="replace"
                    class="mt-1 mr-3 text-red-600 focus:ring-red-500"
                  >
                  <div>
                    <span class="font-medium text-gray-900">Ersetzen (Replace)</span>
                    <p class="text-sm text-red-600">
                      ACHTUNG: Alle bestehenden Daten werden gelöscht und durch die importierten Daten ersetzt!
                    </p>
                  </div>
                </label>
              </div>

              <!-- Replace Confirmation -->
              <div v-if="importMode === 'replace'" class="mt-4 p-4 bg-red-50 border border-red-200 rounded-lg">
                <label class="flex items-start">
                  <input
                    type="checkbox"
                    v-model="confirmReplace"
                    class="mt-1 mr-3 text-red-600 focus:ring-red-500 rounded"
                  >
                  <span class="text-sm text-red-800">
                    Ich verstehe, dass alle bestehenden Daten ({{ exportStats.members_count }} Mitglieder,
                    {{ exportStats.payments_count }} Zahlungen, etc.) unwiderruflich gelöscht werden.
                  </span>
                </label>
              </div>

              <!-- Import Button -->
              <button
                @click="handleImport"
                :disabled="!canImport"
                class="mt-6 w-full inline-flex items-center justify-center px-4 py-3 bg-green-600 border border-transparent rounded-md font-semibold text-sm text-white uppercase tracking-widest hover:bg-green-700 focus:bg-green-700 active:bg-green-900 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition ease-in-out duration-150 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                <Upload class="w-4 h-4 mr-2" />
                {{ isImporting ? 'Wird importiert...' : 'JSON importieren' }}
              </button>
            </div>

            <!-- Import Success -->
            <div v-if="importResult && importResult.success" class="mt-4 p-4 bg-green-50 border border-green-200 rounded-lg">
              <h4 class="text-sm font-medium text-green-800 flex items-center">
                <CheckCircle class="w-4 h-4 mr-2" />
                Import erfolgreich!
              </h4>
              <div class="mt-2 grid grid-cols-2 md:grid-cols-3 gap-2 text-sm text-green-700">
                <span>Mitglieder: {{ importResult.stats.members }}</span>
                <span>Verträge: {{ importResult.stats.membership_plans }}</span>
                <span>Mitgliedschaften: {{ importResult.stats.memberships }}</span>
                <span>Zahlungen: {{ importResult.stats.payments }}</span>
                <span>Kurse: {{ importResult.stats.courses }}</span>
                <span>Check-Ins: {{ importResult.stats.check_ins }}</span>
              </div>
            </div>

            <!-- Import Error -->
            <div v-if="importResult && !importResult.success" class="mt-4 p-4 bg-red-50 border border-red-200 rounded-lg">
              <h4 class="text-sm font-medium text-red-800 flex items-center">
                <XCircle class="w-4 h-4 mr-2" />
                Import fehlgeschlagen
              </h4>
              <p class="mt-1 text-sm text-red-700">{{ importResult.error }}</p>
            </div>
          </div>
        </div>

        <!-- CSV Import Section -->
        <div class="bg-white shadow-sm rounded-lg overflow-hidden">
          <div class="px-4 py-5 sm:p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4 flex items-center">
              <FileSpreadsheet class="w-5 h-5 mr-2 text-orange-500" />
              CSV-Import
            </h3>

            <!-- Format Description -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
              <div class="flex">
                <Info class="w-5 h-5 text-blue-400 mr-3 flex-shrink-0 mt-0.5" />
                <div>
                  <h4 class="text-sm font-medium text-blue-800">Erwartetes CSV-Format</h4>
                  <p class="mt-1 text-sm text-blue-700">
                    Semikolon-getrennt (;), UTF-8.
                  </p>
                  <div class="mt-2 text-sm text-blue-700">
                    <p class="font-medium">Verwendete Spalten:</p>
                    <ul class="list-disc list-inside mt-1 space-y-0.5">
                      <li><strong>email</strong>, <strong>name</strong> (Pflicht)</li>
                      <li>anrede, geburtsdatum, telefon</li>
                      <li>adresse_strasse, adresse_hausnummer, adresse_ort, adresse_plz, land (2-stelliger ISO-Code, Standard: DE)</li>
                      <li><strong>monatsbeitrag</strong> (zur Zuordnung des Tarifs), <strong>tarif</strong> (optional, Tarifname zur genauen Zuordnung)</li>
                      <li>kontoinhaber, iban (bei SEPA-Zahlungsarten)</li>
                    </ul>
                  </div>

                  <div v-if="membershipPlans && membershipPlans.length > 0" class="mt-3 text-sm text-blue-700">
                    <p class="font-medium">Aktive Tarife (Zuordnung via Monatsbeitrag):</p>
                    <div class="flex flex-wrap gap-2 mt-1">
                      <span
                        v-for="plan in membershipPlans"
                        :key="plan.id"
                        class="inline-flex items-center px-2 py-0.5 rounded bg-blue-100 text-blue-800 text-xs"
                      >
                        {{ plan.name }} &mdash; {{ formatPrice(plan.price) }}
                      </span>
                    </div>
                  </div>
                  <button
                    @click="downloadExampleCsv"
                    class="mt-3 inline-flex items-center px-3 py-1.5 text-sm font-medium text-blue-700 bg-blue-100 rounded-md hover:bg-blue-200 transition-colors"
                  >
                    <Download class="w-4 h-4 mr-1.5" />
                    Beispiel-CSV herunterladen
                  </button>
                </div>
              </div>
            </div>

            <!-- CSV File Upload Area -->
            <div
              class="mt-2 flex justify-center rounded-lg border-2 border-dashed px-6 py-10 transition-colors"
              :class="csvIsDragging ? 'border-orange-500 bg-orange-50' : 'border-gray-300'"
              @drop.prevent="handleCsvDrop"
              @dragover.prevent="csvIsDragging = true"
              @dragleave.prevent="csvIsDragging = false"
            >
              <div class="text-center">
                <FileSpreadsheet class="mx-auto h-12 w-12 text-gray-400" />
                <div class="mt-4 flex text-sm text-gray-600">
                  <label class="relative cursor-pointer rounded-md font-semibold text-orange-600 hover:text-orange-500">
                    <span>CSV-Datei auswählen</span>
                    <input
                      type="file"
                      class="sr-only"
                      accept=".csv"
                      @change="handleCsvFileSelect"
                      ref="csvFileInput"
                    >
                  </label>
                  <p class="pl-1">oder per Drag & Drop</p>
                </div>
                <p class="text-xs text-gray-500 mt-1">CSV-Datei bis zu 10 MB</p>
              </div>
            </div>

            <!-- Selected CSV File Info -->
            <div v-if="csvSelectedFile" class="mt-4 p-4 bg-gray-50 rounded-lg">
              <div class="flex items-center justify-between">
                <div class="flex items-center">
                  <FileSpreadsheet class="w-8 h-8 text-orange-500 mr-3" />
                  <div>
                    <p class="font-medium text-gray-900">{{ csvSelectedFile.name }}</p>
                    <p class="text-sm text-gray-500">{{ formatFileSize(csvSelectedFile.size) }}</p>
                  </div>
                </div>
                <button
                  @click="clearCsvFile"
                  class="text-gray-400 hover:text-gray-600"
                >
                  <X class="w-5 h-5" />
                </button>
              </div>
            </div>

            <!-- CSV Validation in progress -->
            <div v-if="csvIsValidating" class="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
              <div class="flex items-center">
                <Loader2 class="w-5 h-5 text-blue-500 mr-3 animate-spin" />
                <span class="text-sm text-blue-700">CSV wird validiert...</span>
              </div>
            </div>

            <!-- CSV Validation Results -->
            <div v-if="csvValidationResult && !csvIsValidating" class="mt-4">
              <div v-if="csvValidationResult.valid" class="bg-green-50 border border-green-200 rounded-lg p-4">
                <h4 class="text-sm font-medium text-green-800 flex items-center">
                  <CheckCircle class="w-4 h-4 mr-2" />
                  CSV ist gültig
                </h4>
                <div class="mt-2 grid grid-cols-2 md:grid-cols-3 gap-2 text-sm text-green-700">
                  <span>Zeilen: {{ csvValidationResult.stats.rows }}</span>
                  <span>Gültig: {{ csvValidationResult.stats.valid_rows }}</span>
                  <span>Tarife zugeordnet: {{ csvValidationResult.stats.plans_matched }}</span>
                  <span>Neue Mitglieder: {{ csvValidationResult.stats.new_members }}</span>
                  <span>Doppelte E-Mails: {{ csvValidationResult.stats.existing_members }}</span>
                </div>
                <div v-if="csvValidationResult.warnings && csvValidationResult.warnings.length > 0" class="mt-3 text-sm text-yellow-700">
                  <p class="font-medium">Warnungen:</p>
                  <ul class="list-disc list-inside max-h-32 overflow-y-auto">
                    <li v-for="warning in csvValidationResult.warnings" :key="warning">{{ warning }}</li>
                  </ul>
                </div>
              </div>
              <div v-else class="bg-red-50 border border-red-200 rounded-lg p-4">
                <h4 class="text-sm font-medium text-red-800 flex items-center">
                  <XCircle class="w-4 h-4 mr-2" />
                  Validierungsfehler
                </h4>
                <ul class="mt-2 text-sm text-red-700 list-disc list-inside">
                  <li v-for="error in csvValidationResult.errors" :key="error">{{ error }}</li>
                </ul>
              </div>
            </div>

            <!-- CSV Import Configuration -->
            <div v-if="csvValidationResult?.valid" class="mt-6 space-y-4">
              <!-- Start Date -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Vertragsbeginn</label>
                <input
                  type="date"
                  v-model="csvStartDate"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                >
                <p class="mt-1 text-xs text-gray-500">Startdatum für alle importierten Mitgliedschaften.</p>
              </div>

              <!-- Payment Method -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Zahlungsart</label>
                <select
                  v-model="csvPaymentMethodType"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                >
                  <option value="" disabled>Bitte wählen...</option>
                  <option
                    v-for="method in paymentMethods"
                    :key="method.key"
                    :value="method.key"
                  >
                    {{ method.name }}
                  </option>
                </select>

                <!-- Mollie SEPA hint -->
                <div v-if="csvPaymentMethodType === 'mollie_directdebit'" class="mt-2 p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                  <div class="flex">
                    <AlertTriangle class="w-4 h-4 text-yellow-400 mr-2 flex-shrink-0 mt-0.5" />
                    <p class="text-sm text-yellow-700">
                      Kunden und SEPA-Mandate werden automatisch bei Mollie angelegt.
                      Dafür muss IBAN und Kontoinhaber in der CSV vorhanden sein.
                    </p>
                  </div>
                </div>

                <!-- SEPA hint -->
                <div v-if="csvPaymentMethodType === 'sepa_direct_debit'" class="mt-2 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                  <div class="flex">
                    <Info class="w-4 h-4 text-blue-400 mr-2 flex-shrink-0 mt-0.5" />
                    <p class="text-sm text-blue-700">
                      SEPA-Mandate werden mit IBAN und Kontoinhaber aus der CSV angelegt.
                    </p>
                  </div>
                </div>
              </div>

              <!-- Delete existing data toggle -->
              <div>
                <div class="flex items-center justify-between">
                  <div>
                    <label class="block text-sm font-medium text-gray-700">Bestehende Daten vorher löschen</label>
                    <p class="text-xs text-gray-500 mt-0.5">
                      Alle Mitglieder, Mitgliedschaften, Zahlungen und Zahlungsarten werden unwiderruflich gelöscht.
                    </p>
                  </div>
                  <button
                    type="button"
                    @click="csvDeleteExisting = !csvDeleteExisting; csvConfirmDelete = false"
                    :class="csvDeleteExisting ? 'bg-red-600' : 'bg-gray-200'"
                    class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
                  >
                    <span
                      :class="csvDeleteExisting ? 'translate-x-5' : 'translate-x-0'"
                      class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                    />
                  </button>
                </div>

                <!-- Delete confirmation -->
                <div v-if="csvDeleteExisting" class="mt-3 p-4 bg-red-50 border border-red-200 rounded-lg">
                  <label class="flex items-start">
                    <input
                      type="checkbox"
                      v-model="csvConfirmDelete"
                      class="mt-1 mr-3 text-red-600 focus:ring-red-500 rounded"
                    >
                    <span class="text-sm text-red-800">
                      Ich verstehe, dass alle bestehenden Mitglieder ({{ exportStats.members_count }}),
                      Verträge ({{ exportStats.memberships_count }}), Zahlungen ({{ exportStats.payments_count }})
                      und zugehörige Zahlungsarten unwiderruflich gelöscht werden.
                    </span>
                  </label>
                </div>
              </div>

              <!-- CSV Import Button -->
              <button
                @click="handleCsvImport"
                :disabled="!canCsvImport"
                class="w-full inline-flex items-center justify-center px-4 py-3 bg-orange-600 border border-transparent rounded-md font-semibold text-sm text-white uppercase tracking-widest hover:bg-orange-700 focus:bg-orange-700 active:bg-orange-900 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2 transition ease-in-out duration-150 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                <Loader2 v-if="csvIsImporting" class="w-4 h-4 mr-2 animate-spin" />
                <Upload v-else class="w-4 h-4 mr-2" />
                {{ csvIsImporting ? 'Wird importiert...' : 'CSV importieren' }}
              </button>

              <!-- Import Progress -->
              <div v-if="csvIsImporting" class="mt-4 p-4 bg-orange-50 border border-orange-200 rounded-lg">
                <div class="flex items-center justify-between mb-2">
                  <span class="text-sm font-medium text-orange-800">{{ csvProgressLabel }}</span>
                  <span class="text-sm text-orange-600">{{ csvProgressPercent }}%</span>
                </div>
                <div class="bg-orange-200 rounded-full h-2.5">
                  <div
                    class="bg-orange-600 h-2.5 rounded-full transition-all duration-500 ease-out"
                    :style="{ width: csvProgressPercent + '%' }"
                  ></div>
                </div>
                <div class="mt-3 flex items-center space-x-4 text-xs text-orange-700">
                  <span
                    v-for="(step, index) in csvProgressSteps"
                    :key="step.key"
                    class="flex items-center"
                    :class="{ 'font-semibold': csvProgressStep === index }"
                  >
                    <CheckCircle v-if="csvProgressStep > index" class="w-3.5 h-3.5 mr-1 text-green-600" />
                    <Loader2 v-else-if="csvProgressStep === index" class="w-3.5 h-3.5 mr-1 animate-spin" />
                    <span v-else class="w-3.5 h-3.5 mr-1 rounded-full border border-orange-300 inline-block"></span>
                    {{ step.label }}
                  </span>
                </div>
              </div>
            </div>

            <!-- CSV Import Success -->
            <div v-if="csvImportResult && csvImportResult.success" class="mt-4 p-4 bg-green-50 border border-green-200 rounded-lg">
              <h4 class="text-sm font-medium text-green-800 flex items-center">
                <CheckCircle class="w-4 h-4 mr-2" />
                CSV-Import erfolgreich!
              </h4>
              <div v-if="csvImportResult.stats.deleted && csvImportResult.stats.deleted.members" class="mt-2 mb-2 text-sm text-red-600">
                Gelöscht: {{ csvImportResult.stats.deleted.members }} Mitglieder,
                {{ csvImportResult.stats.deleted.memberships }} Mitgliedschaften,
                {{ csvImportResult.stats.deleted.payments }} Zahlungen,
                {{ csvImportResult.stats.deleted.payment_methods }} Zahlungsarten<template v-if="csvImportResult.stats.deleted.mollie_customers">,
                {{ csvImportResult.stats.deleted.mollie_customers }} Mollie-Kunden</template>
              </div>
              <div class="mt-2 grid grid-cols-2 md:grid-cols-3 gap-2 text-sm text-green-700">
                <span>Mitglieder erstellt: {{ csvImportResult.stats.members_created }}</span>
                <span>Mitgliedschaften: {{ csvImportResult.stats.memberships_created }}</span>
                <span>Zahlungen: {{ csvImportResult.stats.payments_created }}</span>
                <span>Zahlungsarten: {{ csvImportResult.stats.payment_methods_created }}</span>
                <span>Übersprungen: {{ csvImportResult.stats.skipped }}</span>
              </div>
              <div v-if="csvImportResult.stats.errors && csvImportResult.stats.errors.length > 0" class="mt-3 text-sm text-yellow-700">
                <p class="font-medium">Hinweise:</p>
                <ul class="list-disc list-inside max-h-32 overflow-y-auto">
                  <li v-for="error in csvImportResult.stats.errors" :key="error">{{ error }}</li>
                </ul>
              </div>
            </div>

            <!-- CSV Import Error -->
            <div v-if="csvImportResult && !csvImportResult.success" class="mt-4 p-4 bg-red-50 border border-red-200 rounded-lg">
              <h4 class="text-sm font-medium text-red-800 flex items-center">
                <XCircle class="w-4 h-4 mr-2" />
                CSV-Import fehlgeschlagen
              </h4>
              <p class="mt-1 text-sm text-red-700">{{ csvImportResult.error }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import {
  Download,
  Upload,
  FileJson,
  FileSpreadsheet,
  AlertTriangle,
  CheckCircle,
  XCircle,
  X,
  Loader2,
  Info
} from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'

const props = defineProps({
  currentGym: Object,
  exportStats: Object,
  sensitiveDataWarning: Object,
  paymentMethods: Array,
  membershipPlans: Array,
})

// ── JSON Import State ──
const isDragging = ref(false)
const selectedFile = ref(null)
const validationResult = ref(null)
const importMode = ref('append')
const confirmReplace = ref(false)
const isExporting = ref(false)
const isImporting = ref(false)
const isValidating = ref(false)
const importResult = ref(null)
const fileInput = ref(null)

// ── CSV Import State ──
const csvIsDragging = ref(false)
const csvSelectedFile = ref(null)
const csvValidationResult = ref(null)
const csvIsValidating = ref(false)
const csvIsImporting = ref(false)
const csvImportResult = ref(null)
const csvFileInput = ref(null)

// Default start date: 1st of next month
const nextMonth = new Date()
nextMonth.setMonth(nextMonth.getMonth() + 1)
nextMonth.setDate(1)
const csvStartDate = ref(nextMonth.toISOString().slice(0, 10))
const csvPaymentMethodType = ref('')
const csvDeleteExisting = ref(false)
const csvConfirmDelete = ref(false)
const csvProgressStep = ref(0)
const csvProgressTimer = ref(null)

const csvProgressSteps = computed(() => {
  const steps = []
  if (csvDeleteExisting.value) {
    steps.push({ key: 'delete', label: 'Daten löschen' })
  }
  steps.push(
    { key: 'members', label: 'Mitglieder' },
    { key: 'payments', label: 'Zahlungen' },
    { key: 'done', label: 'Abschluss' },
  )
  return steps
})

const csvProgressPercent = computed(() => {
  const total = csvProgressSteps.value.length
  if (total === 0) return 0
  return Math.min(Math.round((csvProgressStep.value / total) * 100), 95)
})

const csvProgressLabel = computed(() => {
  const step = csvProgressSteps.value[csvProgressStep.value]
  if (!step) return 'Import wird verarbeitet...'
  const labels = {
    delete: 'Bestehende Daten werden gelöscht...',
    members: 'Mitglieder werden importiert...',
    payments: 'Zahlungen werden erstellt...',
    done: 'Import wird abgeschlossen...',
  }
  return labels[step.key] || 'Import wird verarbeitet...'
})

// ── Computed ──
const canImport = computed(() => {
  if (!validationResult.value?.valid) return false
  if (isImporting.value) return false
  if (importMode.value === 'replace' && !confirmReplace.value) return false
  return true
})

const canCsvImport = computed(() => {
  if (!csvValidationResult.value?.valid) return false
  if (csvIsImporting.value) return false
  if (!csvStartDate.value) return false
  if (!csvPaymentMethodType.value) return false
  if (csvDeleteExisting.value && !csvConfirmDelete.value) return false
  return true
})

// ── JSON Import Methods ──
const handleExport = async () => {
  isExporting.value = true
  try {
    const response = await axios.get(route('data-transfer.export'), {
      responseType: 'blob'
    })

    const url = window.URL.createObjectURL(new Blob([response.data]))
    const link = document.createElement('a')
    link.href = url
    const filename = `gym_export_${props.currentGym.slug}_${new Date().toISOString().slice(0, 10)}.json`
    link.setAttribute('download', filename)
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(url)
  } catch (error) {
    console.error('Export failed:', error)
    alert('Export fehlgeschlagen: ' + (error.response?.data?.message || error.message))
  } finally {
    isExporting.value = false
  }
}

const handleFileSelect = (event) => {
  const file = event.target.files[0]
  if (file) processFile(file)
}

const handleDrop = (event) => {
  isDragging.value = false
  const file = event.dataTransfer.files[0]
  if (file) processFile(file)
}

const processFile = async (file) => {
  if (!file.name.endsWith('.json')) {
    alert('Bitte wählen Sie eine JSON-Datei aus.')
    return
  }

  selectedFile.value = file
  validationResult.value = null
  confirmReplace.value = false
  importResult.value = null
  isValidating.value = true

  const formData = new FormData()
  formData.append('file', file)

  try {
    const response = await axios.post(route('data-transfer.validate'), formData)
    validationResult.value = response.data
  } catch (error) {
    validationResult.value = {
      valid: false,
      errors: [error.response?.data?.error || 'Validierung fehlgeschlagen']
    }
  } finally {
    isValidating.value = false
  }
}

const clearFile = () => {
  selectedFile.value = null
  validationResult.value = null
  confirmReplace.value = false
  importResult.value = null
  if (fileInput.value) fileInput.value.value = ''
}

const handleImport = async () => {
  if (!canImport.value) return

  isImporting.value = true
  importResult.value = null

  const formData = new FormData()
  formData.append('file', selectedFile.value)
  formData.append('mode', importMode.value)
  formData.append('confirm_replace', confirmReplace.value ? '1' : '0')

  try {
    const response = await axios.post(route('data-transfer.import'), formData)
    importResult.value = response.data

    if (response.data.success) {
      selectedFile.value = null
      validationResult.value = null
      confirmReplace.value = false
      if (fileInput.value) fileInput.value.value = ''

      setTimeout(() => {
        router.reload()
      }, 2000)
    }
  } catch (error) {
    importResult.value = {
      success: false,
      error: error.response?.data?.error || error.message
    }
  } finally {
    isImporting.value = false
  }
}

// ── CSV Import Methods ──
const handleCsvFileSelect = (event) => {
  const file = event.target.files[0]
  if (file) processCsvFile(file)
}

const handleCsvDrop = (event) => {
  csvIsDragging.value = false
  const file = event.dataTransfer.files[0]
  if (file) processCsvFile(file)
}

const processCsvFile = async (file) => {
  if (!file.name.toLowerCase().endsWith('.csv')) {
    alert('Bitte wählen Sie eine CSV-Datei aus.')
    return
  }

  csvSelectedFile.value = file
  csvValidationResult.value = null
  csvImportResult.value = null
  csvIsValidating.value = true

  const formData = new FormData()
  formData.append('file', file)

  try {
    const response = await axios.post(route('data-transfer.validate-csv'), formData)
    csvValidationResult.value = response.data
  } catch (error) {
    csvValidationResult.value = {
      valid: false,
      errors: [error.response?.data?.error || 'Validierung fehlgeschlagen']
    }
  } finally {
    csvIsValidating.value = false
  }
}

const clearCsvFile = () => {
  csvSelectedFile.value = null
  csvValidationResult.value = null
  csvImportResult.value = null
  if (csvFileInput.value) csvFileInput.value.value = ''
}

const startCsvProgress = () => {
  csvProgressStep.value = 0
  const totalSteps = csvProgressSteps.value.length
  const stepDuration = csvDeleteExisting.value ? 3000 : 4000

  csvProgressTimer.value = setInterval(() => {
    if (csvProgressStep.value < totalSteps - 1) {
      csvProgressStep.value++
    }
  }, stepDuration)
}

const stopCsvProgress = () => {
  if (csvProgressTimer.value) {
    clearInterval(csvProgressTimer.value)
    csvProgressTimer.value = null
  }
}

const handleCsvImport = async () => {
  if (!canCsvImport.value) return

  csvIsImporting.value = true
  csvImportResult.value = null
  startCsvProgress()

  const formData = new FormData()
  formData.append('file', csvSelectedFile.value)
  formData.append('start_date', csvStartDate.value)
  formData.append('payment_method_type', csvPaymentMethodType.value)
  formData.append('delete_existing', csvDeleteExisting.value ? '1' : '0')

  try {
    const response = await axios.post(route('data-transfer.import-csv'), formData)

    // Complete progress to 100%
    stopCsvProgress()
    csvProgressStep.value = csvProgressSteps.value.length

    csvImportResult.value = response.data

    if (response.data.success) {
      csvSelectedFile.value = null
      csvValidationResult.value = null
      if (csvFileInput.value) csvFileInput.value.value = ''

      setTimeout(() => {
        router.reload()
      }, 2000)
    }
  } catch (error) {
    stopCsvProgress()
    csvImportResult.value = {
      success: false,
      error: error.response?.data?.error || error.message
    }
  } finally {
    csvIsImporting.value = false
  }
}

// ── Shared Helpers ──
const formatFileSize = (bytes) => {
  if (bytes === 0) return '0 Bytes'
  const k = 1024
  const sizes = ['Bytes', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
}

const formatPrice = (price) => {
  return new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' }).format(price)
}

const downloadExampleCsv = () => {
  const plan = props.membershipPlans?.[0]
  const tarifName = plan?.name ?? 'Standardtarif'
  const price = plan ? parseFloat(plan.price).toFixed(2).replace('.', ',') : '29,90'

  const header = 'email;name;anrede;geburtsdatum;telefon;adresse_strasse;adresse_hausnummer;adresse_ort;adresse_plz;land;monatsbeitrag;tarif;kontoinhaber;iban'
  const rows = [
    `max.mustermann@example.com;Max Mustermann;Herr;15.03.1990;0170 1234567;Musterstraße;12;Musterstadt;12345;DE;${price};${tarifName};Max Mustermann;DE89370400440532013000`,
    `erika.musterfrau@example.com;Erika Musterfrau;Frau;22.07.1985;0171 9876543;Beispielweg;3a;Musterstadt;12345;AT;${price};${tarifName};Erika Musterfrau;DE02120300000000202051`,
  ]

  const csv = '\uFEFF' + header + '\n' + rows.join('\n') + '\n'
  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = 'beispiel_import.csv'
  a.click()
  URL.revokeObjectURL(url)
}
</script>
