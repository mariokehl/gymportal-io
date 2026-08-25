<template>
  <div class="flex flex-col gap-6">
    <!-- Payment Methods Section -->
    <div class="flex flex-col gap-3">
      <div class="flex justify-between items-center gap-3">
        <h3 class="text-lg font-bold text-gray-900">Zahlungsmethoden</h3>
        <button
          @click="openAddPaymentMethod"
          type="button"
          class="hidden sm:inline-flex items-center gap-2 bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition-colors text-sm font-semibold whitespace-nowrap"
        >
          <Plus class="w-4 h-4" />
          Neue Zahlungsmethode
        </button>
      </div>

      <!-- Full-width add button on mobile -->
      <button
        @click="openAddPaymentMethod"
        type="button"
        class="sm:hidden inline-flex items-center justify-center gap-2 bg-indigo-600 text-white px-3 py-2.5 rounded-lg hover:bg-indigo-700 transition-colors text-sm font-semibold"
      >
        <Plus class="w-[17px] h-[17px]" />
        Neue Zahlungsmethode
      </button>

      <div v-if="member.payment_methods && member.payment_methods.length > 0" class="flex flex-col gap-3">
        <!-- Collapsible payment-method card (accordion) -->
        <div
          v-for="paymentMethod in member.payment_methods"
          :key="paymentMethod.id"
          class="rounded-lg border border-gray-200 bg-white shadow-sm overflow-hidden"
        >
          <button
            type="button"
            @click="toggleMethod(paymentMethod.id)"
            class="w-full flex items-center gap-3 p-4 text-left"
          >
            <div class="p-2 rounded-lg flex-none" :class="getPaymentMethodIconClass(paymentMethod.type)">
              <component :is="getPaymentMethodIcon(paymentMethod.type)" class="w-6 h-6" />
            </div>
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 flex-wrap">
                <h4 class="font-semibold text-gray-900">{{ paymentMethod.type_text }}</h4>
                <span v-if="paymentMethod.is_default" class="bg-blue-100 text-blue-800 text-xs px-2 py-0.5 rounded-full">
                  Standard
                </span>
                <span :class="getStatusBadgeClass(paymentMethod.status)" class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full">
                  {{ getStatusText(paymentMethod.status) }}
                </span>
              </div>
            </div>
            <ChevronDown
              class="w-5 h-5 text-gray-400 flex-none transition-transform"
              :class="expandedMethodId === paymentMethod.id ? 'rotate-180' : ''"
            />
          </button>

          <!-- Expanded details -->
          <div v-show="expandedMethodId === paymentMethod.id" class="px-4 pb-4 -mt-1">
            <div class="border-t border-gray-100 pt-3 space-y-3">
              <!-- SEPA Details -->
              <div v-if="isSepaType(paymentMethod.type)" class="space-y-1.5 text-sm">
                <div class="flex items-center gap-1.5">
                  <span class="text-gray-500">IBAN:</span>
                  <span class="font-mono text-gray-900">{{ paymentMethod.masked_iban || '****' }}</span>
                  <Tooltip
                    v-if="isMollieManaged(paymentMethod)"
                    position="top"
                    text="IBAN wird von Mollie verwaltet"
                  >
                    <Link2
                      class="w-4 h-4 text-indigo-600"
                      aria-label="IBAN wird von Mollie verwaltet"
                    />
                  </Tooltip>
                  <Tooltip
                    v-else-if="isMollieLinkBroken(paymentMethod)"
                    position="top"
                    text="Keine Verknüpfung zu Mollie"
                  >
                    <Unlink
                      class="w-4 h-4 text-red-600"
                      aria-label="Keine Verknüpfung zu Mollie"
                    />
                  </Tooltip>
                </div>
                <div v-if="paymentMethod.sepa_mandate_reference" class="flex gap-1.5">
                  <span class="text-gray-500">Mandatsreferenz:</span>
                  <span class="text-gray-900">{{ paymentMethod.sepa_mandate_reference }}</span>
                </div>
                <div v-if="paymentMethod.sepa_mandate_status" class="flex items-center gap-2 flex-wrap">
                  <span class="text-gray-500">SEPA-Mandat:</span>
                  <span
                    :class="getSepaMandateStatusClass(paymentMethod.sepa_mandate_status)"
                    class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full"
                  >
                    {{ getSepaMandateStatusText(paymentMethod.sepa_mandate_status) }}
                  </span>
                  <!-- Mollie holds the account data, so a locally corrected IBAN
                       only reaches it once the mandate is re-issued. -->
                  <button
                    v-if="canSyncMollieMandate(paymentMethod)"
                    type="button"
                    @click="syncMollieMandate(paymentMethod)"
                    :disabled="syncingMandate === paymentMethod.id"
                    class="inline-flex items-center gap-1 text-xs font-semibold text-indigo-600 hover:text-indigo-800 hover:underline disabled:opacity-50 disabled:cursor-not-allowed disabled:no-underline"
                  >
                    <RefreshCw
                      class="w-3.5 h-3.5"
                      :class="syncingMandate === paymentMethod.id ? 'animate-spin' : ''"
                    />
                    {{ syncingMandate === paymentMethod.id ? 'Wird übertragen …' : 'IBAN zu Mollie übertragen' }}
                  </button>
                </div>
                <div v-if="paymentMethod.sepa_mandate_signed_at" class="flex gap-1.5">
                  <span class="text-gray-500">Unterschrieben am:</span>
                  <span class="text-gray-900">{{ formatDate(paymentMethod.sepa_mandate_signed_at) }}</span>
                </div>
              </div>

              <!-- Credit Card Details -->
              <div v-else-if="isCreditCardType(paymentMethod.type)" class="space-y-1.5 text-sm">
                <p class="text-gray-600 font-mono">**** **** **** {{ paymentMethod.last_four }}</p>
                <p v-if="paymentMethod.cardholder_name" class="text-gray-600">{{ paymentMethod.cardholder_name }}</p>
                <p v-if="paymentMethod.expiry_date" class="text-gray-600">
                  Gültig bis: {{ formatMonthYear(paymentMethod.expiry_date) }}
                </p>
              </div>

              <!-- Bank Transfer Details -->
              <div v-else-if="isBankTransferType(paymentMethod.type) && paymentMethod.bank_name" class="text-sm">
                <p class="text-gray-600">{{ paymentMethod.bank_name }}</p>
              </div>

              <!-- SEPA Mandate Actions -->
              <div v-if="paymentMethod.requires_mandate && paymentMethod.sepa_mandate_status === 'pending'" class="bg-yellow-100 border border-yellow-200 rounded-xl p-3.5">
                <div class="flex gap-2.5">
                  <AlertCircle class="w-[18px] h-[18px] text-yellow-700 flex-none mt-0.5" />
                  <span class="text-[13.5px] font-semibold text-yellow-700 leading-snug">SEPA-Mandat muss noch unterschrieben werden</span>
                </div>
                <div class="flex flex-col gap-2 mt-3">
                  <button
                    type="button"
                    @click="sendSepaMandate(paymentMethod)"
                    :disabled="sendingMandate === paymentMethod.id"
                    class="inline-flex items-center justify-center gap-2 w-full min-h-11 px-3.5 py-2.5 rounded-[10px] bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                  >
                    <Send class="w-4 h-4" />
                    {{ sendingMandate === paymentMethod.id ? 'Wird verarbeitet...' : 'Mandat versenden' }}
                  </button>
                  <button
                    type="button"
                    @click="markSepaMandateAsSigned(paymentMethod)"
                    :disabled="markingAsSigned === paymentMethod.id"
                    class="inline-flex items-center justify-center gap-2 w-full min-h-11 px-3.5 py-2.5 rounded-[10px] bg-white border border-green-600 text-green-700 text-sm font-semibold hover:bg-green-50 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                  >
                    <Check class="w-4 h-4" />
                    {{ markingAsSigned === paymentMethod.id ? 'Wird markiert...' : 'Als unterschrieben markieren' }}
                  </button>
                </div>
              </div>

              <!-- Signed mandate actions -->
              <div v-if="paymentMethod.requires_mandate && paymentMethod.sepa_mandate_status === 'signed'" class="p-3 bg-blue-50 rounded-md">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                  <div class="flex items-center">
                    <CheckCircle class="w-5 h-5 text-blue-600 mr-2 flex-none" />
                    <span class="text-sm text-blue-800">SEPA-Mandat wurde unterschrieben und wartet auf Aktivierung</span>
                  </div>
                  <button
                    type="button"
                    @click="activateSepaMandate(paymentMethod)"
                    :disabled="activatingMandate === paymentMethod.id"
                    class="bg-blue-600 text-white px-3 py-1 rounded text-sm hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors whitespace-nowrap"
                  >
                    {{ activatingMandate === paymentMethod.id ? 'Wird aktiviert...' : 'Mandat aktivieren' }}
                  </button>
                </div>
              </div>

              <!-- Action links -->
              <div class="flex flex-wrap items-center gap-4 pt-1">
                <button
                  v-if="!paymentMethod.is_default && paymentMethod.status === 'active'"
                  @click="setAsDefault(paymentMethod)"
                  type="button"
                  class="text-sm font-semibold text-indigo-600 hover:text-indigo-800"
                  :disabled="settingDefault === paymentMethod.id"
                >
                  {{ settingDefault === paymentMethod.id ? 'Wird gesetzt...' : 'Als Standard setzen' }}
                </button>
                <button
                  @click="openEditPaymentMethod(paymentMethod)"
                  type="button"
                  class="text-sm font-semibold text-indigo-600 hover:text-indigo-800"
                >
                  Bearbeiten
                </button>
                <button
                  v-if="paymentMethod.status === 'active'"
                  @click="deactivatePaymentMethod(paymentMethod)"
                  type="button"
                  class="text-sm font-semibold text-red-600 hover:text-red-800"
                  :disabled="deactivating === paymentMethod.id"
                >
                  {{ deactivating === paymentMethod.id ? 'Deaktivieren...' : 'Deaktivieren' }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div v-else class="text-center py-8 bg-gray-50 rounded-lg">
        <Wallet class="w-12 h-12 text-gray-400 mx-auto mb-4" />
        <p class="text-gray-500">Keine Zahlungsmethoden vorhanden</p>
        <button
          @click="openAddPaymentMethod"
          type="button"
          class="mt-3 bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 inline-flex items-center gap-2"
        >
          <Plus class="w-4 h-4" />
          Erste Zahlungsmethode hinzufügen
        </button>
      </div>
    </div>

    <!-- Payment History Section -->
    <div class="flex flex-col gap-3">
      <div class="flex flex-wrap justify-between items-center gap-2">
        <div class="flex items-center gap-3">
          <h3 class="text-lg font-bold text-gray-900">Zahlungshistorie</h3>
          <span v-if="outstandingBalance" class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium rounded-full bg-amber-50 text-amber-700 border border-amber-200">
            <AlertTriangle class="w-3 h-3" />
            Offene Posten: {{ outstandingBalance.toLocaleString('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }} €
          </span>
        </div>
      </div>

      <!-- Controls: filter + add + batch (per design: select fills, button hugs) -->
      <div class="flex flex-wrap items-center gap-2.5">
        <select
          v-model="paymentStatusFilter"
          class="flex-1 min-w-0 border border-gray-300 rounded-md px-3 py-2 text-sm bg-white"
        >
          <option value="">Alle Status</option>
          <option value="paid">Bezahlt</option>
          <option value="pending">Ausstehend</option>
          <option value="failed">Fehlgeschlagen</option>
        </select>

        <button
          @click="openAddPayment"
          type="button"
          class="flex-none bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 inline-flex items-center gap-2 text-sm font-semibold whitespace-nowrap"
        >
          <Plus class="w-4 h-4" />
          Zahlung
        </button>

        <button
          v-if="selectedPaymentIds.length > 0 && hasPendingPaymentsSelected"
          @click="executeSelectedPayments"
          type="button"
          class="flex-none bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 inline-flex items-center gap-2 text-sm font-semibold disabled:opacity-50 disabled:cursor-not-allowed whitespace-nowrap"
          :disabled="executingBatch"
        >
          <PlayCircle v-if="!executingBatch" class="w-4 h-4" />
          <div v-else class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
          {{ executingBatch ? 'Wird ausgeführt...' : `Zahlungen ausführen (${selectedPendingPaymentIds.length})` }}
        </button>
      </div>

      <div v-if="filteredPayments?.data?.length > 0">
        <PaymentsTable
          :payments="filteredPayments"
          :columns="paymentTableColumns"
          v-model:selectedIds="selectedPaymentIds"
          :show-checkboxes="true"
          :show-csv-export="false"
          :show-sepa-export="false"
          :show-pagination="false"
          :executing-payment-id="executingPaymentId"
          :batch-executing-payments="executingBatch"
          @payment-marked-paid="handlePaymentMarkedPaid"
        >
          <template #actions="{ payment }">
            <button
              v-if="payment.invoice_id"
              @click="downloadInvoice(payment)"
              type="button"
              class="text-blue-600 hover:text-blue-800"
              title="Rechnung herunterladen"
            >
              <Download class="w-4 h-4" />
            </button>
            <button
              v-if="payment.status === 'pending'"
              @click="handleExecutePayment(payment)"
              type="button"
              :disabled="isPaymentExecuting(payment.id) || executingBatch || payment.mollie_payment_id"
              class="text-indigo-600 hover:text-indigo-800 disabled:opacity-50 disabled:cursor-not-allowed"
              :title="isPaymentExecuting(payment.id) ? 'Wird ausgeführt...' : 'Zahlung ausführen'"
            >
              <PlayCircle v-if="!isPaymentExecuting(payment.id)" class="w-4 h-4" />
              <div v-else class="w-4 h-4 border-2 border-indigo-600 border-t-transparent rounded-full animate-spin"></div>
            </button>
          </template>

        </PaymentsTable>
      </div>
      <div v-else class="text-center py-8 bg-gray-50 rounded-lg">
        <CreditCard class="w-12 h-12 text-gray-400 mx-auto mb-4" />
        <p class="text-gray-500">Keine Zahlungen vorhanden</p>
        <button
          @click="openAddPayment"
          type="button"
          class="mt-3 bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 inline-flex items-center gap-2"
        >
          <Plus class="w-4 h-4" />
          Erste Zahlung hinzufügen
        </button>
      </div>
    </div>

    <!-- Edit Payment Method Modal -->
    <teleport to="body">
      <div v-if="showEditPaymentMethodModal" class="fixed inset-0 bg-gray-500/75 overflow-y-auto h-full w-full z-50" @click="closeEditPaymentMethod">
        <div class="relative top-20 mx-auto p-5 border border-gray-50 w-11/12 md:w-3/4 lg:w-1/3 shadow-lg rounded-md bg-white" @click.stop>
          <form @submit.prevent="updatePaymentMethod">
            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
              <div class="mb-4">
                <h3 class="text-lg font-medium text-gray-900">
                  Zahlungsmethode bearbeiten
                </h3>
              </div>

              <div class="space-y-4">
                <!-- Type (nicht änderbar) -->
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Typ</label>
                  <input
                    :value="paymentMethodForm.type_text"
                    disabled
                    class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50 text-gray-500"
                  />
                </div>

                <!-- Status -->
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                  <select
                    v-model="paymentMethodForm.status"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
                  >
                    <option value="active">Aktiv</option>
                    <option value="pending">Ausstehend</option>
                    <option value="expired">Abgelaufen</option>
                    <option value="failed">Fehlgeschlagen</option>
                  </select>
                </div>

                <!-- SEPA-spezifische Felder -->
                <template v-if="isSepaType(paymentMethodForm.type)">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Kontoinhaber</label>
                    <input
                      v-model="paymentMethodForm.account_holder"
                      type="text"
                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">IBAN</label>
                    <IbanInput
                      v-model="paymentMethodForm.iban"
                      placeholder="DE89 3704 0044 0532 0130 00"
                      @validation-change="(validation) => handleIbanValidation(validation, 'editPaymentMethod')"
                    />
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Bank Name</label>
                    <input
                      v-model="paymentMethodForm.bank_name"
                      type="text"
                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">SEPA-Mandat Status</label>
                    <select
                      v-model="paymentMethodForm.sepa_mandate_status"
                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    >
                      <option value="pending">Unterschrift ausstehend</option>
                      <option value="signed">Unterschrieben</option>
                      <option value="active">Aktiv</option>
                      <option value="revoked">Widerrufen</option>
                      <option value="expired">Abgelaufen</option>
                    </select>
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">SEPA-Mandatsreferenz</label>
                    <input
                      v-model="paymentMethodForm.sepa_mandate_reference"
                      type="text"
                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                  </div>
                </template>

                <!-- Kreditkarten-spezifische Felder -->
                <template v-if="isCreditCardType(paymentMethodForm.type)">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Letzte 4 Ziffern</label>
                    <input
                      v-model="paymentMethodForm.last_four"
                      type="text"
                      maxlength="4"
                      pattern="[0-9]{4}"
                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Karteninhaber</label>
                    <input
                      v-model="paymentMethodForm.cardholder_name"
                      type="text"
                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Ablaufdatum</label>
                    <input
                      v-model="paymentMethodForm.expiry_date"
                      type="date"
                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                  </div>
                </template>

                <!-- Banküberweisung-spezifische Felder -->
                <template v-if="isBankTransferType(paymentMethodForm.type)">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Bank Name</label>
                    <input
                      v-model="paymentMethodForm.bank_name"
                      type="text"
                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                  </div>
                </template>

                <!-- Standard-Zahlungsmethode -->
                <div>
                  <label class="flex items-center">
                    <input
                      v-model="paymentMethodForm.is_default"
                      type="checkbox"
                      class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                    />
                    <span class="ml-2 text-sm text-gray-700">Als Standard-Zahlungsmethode setzen</span>
                  </label>
                </div>
              </div>

              <div v-if="paymentMethodForm.errors && Object.keys(paymentMethodForm.errors).length > 0" class="mt-4 p-3 bg-red-50 rounded-md">
                <div class="text-sm text-red-800">
                  <ul class="list-disc list-inside">
                    <li v-for="(error, field) in paymentMethodForm.errors" :key="field">{{ error }}</li>
                  </ul>
                </div>
              </div>
            </div>

            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
              <button
                type="submit"
                :disabled="paymentMethodForm.processing || !canSubmitEditedPaymentMethod"
                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50"
              >
                {{ paymentMethodForm.processing ? 'Speichern...' : 'Speichern' }}
              </button>
              <button
                type="button"
                @click="closeEditPaymentMethod"
                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
              >
                Abbrechen
              </button>
            </div>
          </form>
        </div>
      </div>
    </teleport>

    <!-- Add Payment Method Modal -->
    <teleport to="body">
      <div v-if="showAddPaymentMethodModal" class="fixed inset-0 bg-gray-500/75 overflow-y-auto h-full w-full z-50" @click="closeAddPaymentMethod">
        <div class="relative top-20 mx-auto p-5 border border-gray-50 w-11/12 md:w-3/4 lg:w-1/3 shadow-lg rounded-md bg-white" @click.stop>
          <form @submit.prevent="createPaymentMethod">
            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
              <div class="mb-4">
                <h3 class="text-lg font-medium text-gray-900">
                  Neue Zahlungsmethode hinzufügen
                </h3>
              </div>

              <div class="space-y-4">
                <!-- Type (auswählbar bei neuer Zahlungsmethode) -->
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Zahlungsmethode <span class="text-red-500">*</span></label>
                  <select
                    v-model="newPaymentMethodForm.type"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
                  >
                    <option value="">Bitte wählen...</option>
                    <option
                      v-for="method in availablePaymentMethodTypes"
                      :key="method.key"
                      :value="method.key"
                    >
                      {{ method.name }}
                    </option>
                  </select>
                </div>

                <!-- SEPA-spezifische Felder -->
                <template v-if="isSepaType(newPaymentMethodForm.type)">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">IBAN <span class="text-red-500">*</span></label>
                    <IbanInput
                      v-model="newPaymentMethodForm.iban"
                      placeholder="DE89 3704 0044 0532 0130 00"
                      :required="true"
                      @validation-change="(validation) => handleIbanValidation(validation, 'newPaymentMethod')"
                    />
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Kontoinhaber</label>
                    <input
                      v-model="newPaymentMethodForm.account_holder"
                      type="text"
                      placeholder="Max Mustermann"
                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Bank Name</label>
                    <input
                      v-model="newPaymentMethodForm.bank_name"
                      type="text"
                      placeholder="Commerzbank"
                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                  </div>
                  <div>
                    <label class="flex items-center">
                      <input
                        v-model="newPaymentMethodForm.sepa_mandate_acknowledged"
                        type="checkbox"
                        class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                        :disabled="!ibanValidation.newPaymentMethod.isValid"
                      />
                      <span class="ml-2 text-sm text-gray-700">
                        SEPA-Mandat wurde zur Kenntnis genommen
                        <span v-if="!ibanValidation.newPaymentMethod.isValid" class="text-gray-400">
                          (erst nach gültiger IBAN verfügbar)
                        </span>
                      </span>
                    </label>
                  </div>
                </template>

                <!-- Kreditkarten-spezifische Felder -->
                <template v-if="isCreditCardType(newPaymentMethodForm.type)">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Kartennummer <span class="text-red-500">*</span></label>
                    <input
                      v-model="newPaymentMethodForm.card_number"
                      type="text"
                      placeholder="**** **** **** 1234"
                      maxlength="19"
                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Karteninhaber <span class="text-red-500">*</span></label>
                    <input
                      v-model="newPaymentMethodForm.cardholder_name"
                      type="text"
                      placeholder="Max Mustermann"
                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                  </div>
                  <div class="grid grid-cols-2 gap-4">
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-2">Ablaufdatum <span class="text-red-500">*</span></label>
                      <input
                        v-model="newPaymentMethodForm.expiry_date"
                        type="month"
                        :min="currentMonth"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
                      />
                    </div>
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-2">CVV <span class="text-red-500">*</span></label>
                      <input
                        v-model="newPaymentMethodForm.cvv"
                        type="text"
                        maxlength="4"
                        pattern="[0-9]{3,4}"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
                      />
                    </div>
                  </div>
                </template>

                <!-- Banküberweisung-spezifische Felder -->
                <template v-if="isBankTransferType(newPaymentMethodForm.type)">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Bank Name</label>
                    <input
                      v-model="newPaymentMethodForm.bank_name"
                      type="text"
                      placeholder="Commerzbank"
                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Notizen</label>
                    <textarea
                      v-model="newPaymentMethodForm.notes"
                      rows="2"
                      placeholder="z.B. Verwendungszweck-Vorgaben"
                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    ></textarea>
                  </div>
                </template>

                <!-- Standard-Zahlungsmethode -->
                <div v-if="newPaymentMethodForm.type">
                  <label class="flex items-center">
                    <input
                      v-model="newPaymentMethodForm.is_default"
                      type="checkbox"
                      class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                    />
                    <span class="ml-2 text-sm text-gray-700">Als Standard-Zahlungsmethode setzen</span>
                  </label>
                </div>
              </div>

              <div v-if="newPaymentMethodForm.errors && Object.keys(newPaymentMethodForm.errors).length > 0" class="mt-4 p-3 bg-red-50 rounded-md">
                <div class="text-sm text-red-800">
                  <ul class="list-disc list-inside">
                    <li v-for="(error, field) in newPaymentMethodForm.errors" :key="field">{{ error }}</li>
                  </ul>
                </div>
              </div>
            </div>

            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
              <button
                type="submit"
                :disabled="newPaymentMethodForm.processing ||
                          !newPaymentMethodForm.type ||
                          (isSepaType(newPaymentMethodForm.type) && !ibanValidation.newPaymentMethod.isValid)"
                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {{ newPaymentMethodForm.processing ? 'Hinzufügen...' : 'Hinzufügen' }}
              </button>
              <button
                type="button"
                @click="closeAddPaymentMethod"
                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
              >
                Abbrechen
              </button>
            </div>
          </form>
        </div>
      </div>
    </teleport>

    <!-- Add Payment Modal -->
    <teleport to="body">
      <div v-if="showAddPaymentModal" class="fixed inset-0 bg-gray-500/75 overflow-y-auto h-full w-full z-50 flex justify-center items-start py-12 sm:py-20 px-2" @click="closeAddPayment">
        <div class="relative mx-auto p-5 border border-gray-50 w-11/12 md:w-3/4 lg:w-1/2 xl:w-2/5 shadow-lg rounded-md bg-white" @click.stop>
          <form @submit.prevent="createPayment">
            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
              <div class="mb-4">
                <h3 class="text-lg font-medium text-gray-900">
                  {{ newPaymentForm.payment_type === 'topup' ? 'Guthaben aufladen' : 'Neue Zahlung hinzufügen' }}
                </h3>
              </div>

              <!-- Payment type selector -->
              <div class="mb-5">
                <div class="text-sm font-semibold text-gray-700 mb-2">Zahlungstyp</div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <button
                    type="button"
                    @click="newPaymentForm.payment_type = 'regular'"
                    class="text-left rounded-lg border px-4 py-3.5 transition-colors"
                    :class="newPaymentForm.payment_type === 'regular'
                      ? 'border-indigo-600 bg-indigo-50 text-indigo-700'
                      : 'border-gray-200 bg-white text-gray-700 hover:border-gray-300'"
                  >
                    <div class="flex items-center gap-2.5">
                      <CreditCard class="w-[18px] h-[18px]" />
                      <span class="font-semibold text-sm">Reguläre Zahlung</span>
                    </div>
                    <div class="text-xs text-gray-500 mt-1.5">Forderung über eine Zahlungsmethode.</div>
                  </button>
                  <button
                    type="button"
                    @click="newPaymentForm.payment_type = 'topup'"
                    class="text-left rounded-lg border px-4 py-3.5 transition-colors"
                    :class="newPaymentForm.payment_type === 'topup'
                      ? 'border-indigo-600 bg-indigo-50 text-indigo-700'
                      : 'border-gray-200 bg-white text-gray-700 hover:border-gray-300'"
                  >
                    <div class="flex items-center gap-2.5">
                      <Wallet class="w-[18px] h-[18px]" />
                      <span class="font-semibold text-sm">Guthaben-Aufladung</span>
                    </div>
                    <div class="text-xs text-gray-500 mt-1.5">Betrag als Guthaben gutschreiben.</div>
                  </button>
                </div>
              </div>

              <!-- Top-up branch -->
              <template v-if="newPaymentForm.payment_type === 'topup'">
                <div class="flex gap-3 bg-indigo-50 border border-indigo-100 rounded-lg px-4 py-3.5 mb-5">
                  <Info class="w-5 h-5 text-indigo-600 flex-none" />
                  <div class="text-[13px] text-indigo-800 leading-relaxed">
                    Der Betrag wird dem Mitgliedskonto als Guthaben gutgeschrieben und bei zukünftigen Abbuchungen
                    <strong>automatisch vorrangig</strong> verwendet. Ideal für Voraus- und Jahreszahlungen per Überweisung.
                  </div>
                </div>

                <div class="space-y-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Betrag <span class="text-red-500">*</span></label>
                    <input
                      v-model="newPaymentForm.amount"
                      type="number"
                      step="0.01"
                      min="0"
                      placeholder="0.00"
                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Beschreibung <span class="text-red-500">*</span></label>
                    <input
                      v-model="newPaymentForm.description"
                      type="text"
                      placeholder="z.B. Guthaben-Aufladung: Überweisung"
                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                  </div>
                  <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-2">Eingangsdatum</label>
                      <input
                        v-model="newPaymentForm.paid_date"
                        type="date"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
                      />
                    </div>
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-2">Zahlungsart</label>
                      <select
                        v-model="newPaymentForm.payment_method"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
                      >
                        <option
                          v-for="method in topupPaymentMethods"
                          :key="method.key"
                          :value="method.key"
                        >
                          {{ method.name }}
                        </option>
                      </select>
                    </div>
                  </div>
                  <div v-if="isMollieTopupMethod" class="flex gap-2.5 bg-amber-100 rounded-md px-3.5 py-3">
                    <Info class="w-[17px] h-[17px] text-amber-600 flex-none" />
                    <div class="text-xs text-amber-700 leading-relaxed">
                      Die Aufladung wird zunächst als <strong>ausstehend</strong> vermerkt. Das Guthaben wird erst
                      gutgeschrieben, sobald die Zahlung über den Zahlungsanbieter bestätigt wurde.
                    </div>
                  </div>
                  <div class="flex items-center justify-between gap-3 bg-gray-50 border border-dashed border-gray-300 rounded-md px-4 py-3.5">
                    <span class="text-sm text-gray-600">{{ isMollieTopupMethod ? 'Guthaben-Stand nach Zahlungseingang' : 'Neuer Guthaben-Stand nach Aufladung' }}</span>
                    <span class="text-lg font-bold text-indigo-600 whitespace-nowrap flex-none">{{ newBalancePreview }}</span>
                  </div>
                </div>
              </template>

              <!-- Regular branch -->
              <template v-else>
                <div class="space-y-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Betrag <span class="text-red-500">*</span></label>
                    <input
                      v-model="newPaymentForm.amount"
                      type="number"
                      step="0.01"
                      min="0"
                      placeholder="0.00"
                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Beschreibung <span class="text-red-500">*</span></label>
                    <input
                      v-model="newPaymentForm.description"
                      type="text"
                      placeholder="z.B. Monatsbeitrag Januar 2024"
                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Fälligkeitsdatum</label>
                    <input
                      v-model="newPaymentForm.due_date"
                      type="date"
                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Zahlungsmethode</label>
                    <select
                      v-model="newPaymentForm.payment_method"
                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    >
                      <option value="">Bitte wählen...</option>
                      <option value="credit">Guthaben (verfügbar: {{ creditBalanceFormatted }}) — vorrangig</option>
                      <option
                        v-for="method in availablePaymentMethodTypes"
                        :key="method.key"
                        :value="method.key"
                      >
                        {{ method.name }}
                      </option>
                    </select>
                  </div>

                  <div v-if="newPaymentForm.payment_method === 'credit'" class="flex gap-2.5 bg-amber-100 rounded-md px-3.5 py-3">
                    <Info class="w-[17px] h-[17px] text-amber-600 flex-none" />
                    <div class="text-xs text-amber-700 leading-relaxed">
                      Reicht das Guthaben nicht aus, wird der Restbetrag automatisch über die Standard-Zahlungsmethode abgebucht.
                    </div>
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                    <select
                      v-model="newPaymentForm.status"
                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    >
                      <option value="pending">Ausstehend</option>
                      <option value="paid">Bezahlt</option>
                    </select>
                  </div>

                  <div v-if="newPaymentForm.status === 'paid'">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Bezahlt am</label>
                    <input
                      v-model="newPaymentForm.paid_date"
                      type="date"
                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Notizen</label>
                    <textarea
                      v-model="newPaymentForm.notes"
                      rows="2"
                      placeholder="Optionale Notizen zur Zahlung"
                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    ></textarea>
                  </div>
                </div>
              </template>

              <div v-if="newPaymentForm.errors && Object.keys(newPaymentForm.errors).length > 0" class="mt-4 p-3 bg-red-50 rounded-md">
                <div class="text-sm text-red-800">
                  <ul class="list-disc list-inside">
                    <li v-for="(error, field) in newPaymentForm.errors" :key="field">{{ error }}</li>
                  </ul>
                </div>
              </div>
            </div>

            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
              <button
                type="submit"
                :disabled="newPaymentForm.processing || !newPaymentForm.amount || !newPaymentForm.description"
                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed"
              >
                <template v-if="newPaymentForm.processing">Wird gespeichert...</template>
                <template v-else>{{ newPaymentForm.payment_type === 'topup' ? 'Als Guthaben verbuchen' : 'Hinzufügen' }}</template>
              </button>
              <button
                type="button"
                @click="closeAddPayment"
                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
              >
                Abbrechen
              </button>
            </div>
          </form>
        </div>
      </div>
    </teleport>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import { useInertiaPayments } from '@/composables/useInertiaPayments'
import PaymentsTable from '@/Components/PaymentsTable.vue'
import IbanInput from '@/Components/IbanInput.vue'
import {
  CreditCard, Plus, Wallet, AlertCircle, CheckCircle, XCircle,
  Download, Building2, Banknote, PlayCircle, WalletCards,
  FileText, AlertTriangle, ChevronDown, Send, Check, Info, Link2, Unlink, RefreshCw
} from 'lucide-vue-next'
import Tooltip from '@/Components/Tooltip.vue'
import { formatDate, formatMonthYear, formatDateForInput } from '@/utils/formatters'
import { sortByScheduledDate } from '@/utils/payments'

const props = defineProps({
  member: {
    type: Object,
    required: true
  },
  availablePaymentMethods: {
    type: Array,
    default: () => []
  }
})

const {
  payments,
  executePayment,
  executeBatchPayments,
  executingPaymentId,
  executingBatch,
  updateLocalPayments,
  isPaymentExecuting
} = useInertiaPayments(props.member.id)

// Accordion state for payment-method cards
const expandedMethodId = ref(null)
const toggleMethod = (id) => {
  expandedMethodId.value = expandedMethodId.value === id ? null : id
}

const outstandingBalance = computed(() => {
  const list = payments.value || props.member.payments || []
  const chargebacks = list.filter(p => p.status === 'chargeback')
  const sum = chargebacks.reduce((acc, cb) => {
    const hasSettlement = list.some(p => p.status === 'paid' && p.notes === cb.mollie_payment_id)
    return hasSettlement ? acc : acc + Math.abs(parseFloat(cb.amount))
  }, 0)
  return sum > 0 ? sum : null
})

// Payment table columns configuration
const paymentStatusFilter = ref('')
const selectedPaymentIds = ref([])
const paymentTableColumns = ref([
  { key: 'id', label: 'ID', sortable: true, nowrap: true, visible: false },
  { key: 'created_at', label: 'Datum', sortable: true, nowrap: true },
  { key: 'amount', label: 'Betrag', sortable: true, nowrap: true },
  { key: 'description', label: 'Beschreibung', sortable: false },
  { key: 'status', label: 'Status', sortable: false, nowrap: true },
  { key: 'payment_method', label: 'Zahlungsmethode', sortable: false, nowrap: true },
  { key: 'due_date', label: 'Fälligkeitsdatum', sortable: false, nowrap: true }
])

// PaymentMethod-related state
const showEditPaymentMethodModal = ref(false)
const showAddPaymentMethodModal = ref(false)
const showAddPaymentModal = ref(false)
const settingDefault = ref(null)
const deactivating = ref(null)
const markingAsSigned = ref(null)
const sendingMandate = ref(null)
const activatingMandate = ref(null)
const syncingMandate = ref(null)

// Computed properties
const availablePaymentMethodTypes = computed(() => {
  if (props.availablePaymentMethods && props.availablePaymentMethods.length > 0) {
    return props.availablePaymentMethods
  }
  if (props.member?.gym?.enabled_payment_methods) {
    return props.member.gym.enabled_payment_methods
  }
  return []
})

// Whether Mollie is enabled for the gym (any mollie_ method is available).
const isMollieEnabled = computed(() =>
  availablePaymentMethodTypes.value.some(m => String(m.key || '').startsWith('mollie_'))
)

// Payment methods offered for a credit top-up: the manual (non-Mollie) methods
// plus a single "Mollie: Zahlungslink" option when Mollie is enabled.
const topupPaymentMethods = computed(() => {
  const manual = availablePaymentMethodTypes.value.filter(
    m => !String(m.key || '').startsWith('mollie_')
  )
  if (isMollieEnabled.value) {
    manual.push({ key: 'mollie_paymentlink', name: 'Mollie: Zahlungslink' })
  }
  return manual
})

// A Mollie method for a top-up is collected asynchronously: the credit is only
// granted once the webhook confirms the payment.
const isMollieTopupMethod = computed(() =>
  String(newPaymentForm.payment_method || '').startsWith('mollie_')
)

const currentMonth = computed(() => {
  const now = new Date()
  const year = now.getFullYear()
  const month = String(now.getMonth() + 1).padStart(2, '0')
  return `${year}-${month}`
})

const filteredPayments = computed(() => {
  let paymentList = payments.value || []

  if (paymentStatusFilter.value) {
    paymentList = paymentList.filter(p => p.status === paymentStatusFilter.value)
  }

  paymentList = sortByScheduledDate(paymentList)

  return {
    data: paymentList,
    total: paymentList.length
  }
})

const selectedPendingPaymentIds = computed(() => {
  return selectedPaymentIds.value.filter(id => {
    const payment = payments.value.find(p => p.id === id)
    return payment && payment.status === 'pending'
  })
})

const hasPendingPaymentsSelected = computed(() => {
  return selectedPendingPaymentIds.value.length > 0
})

// Helper functions
const isSepaType = (type) => {
  return type === 'sepa_direct_debit' ||
         type === 'mollie_directdebit'
}

const isCreditCardType = (type) => {
  return type === 'creditcard' ||
         type === 'mollie_creditcard' ||
         type?.includes('creditcard')
}

const isBankTransferType = (type) => {
  return type === 'banktransfer' ||
         type === 'mollie_banktransfer' ||
         type?.includes('banktransfer')
}

// Both ids together mean Mollie holds the account data and the mandate: the
// IBAN shown here is only a local copy that must not be edited on our side.
const isMollieManaged = paymentMethod =>
  Boolean(paymentMethod.mollie_customer_id && paymentMethod.mollie_mandate_id)

// A Mollie direct debit is supposed to carry both ids. Missing either one means
// the method cannot be collected through Mollie, which the operator has to fix.
const isMollieLinkBroken = paymentMethod =>
  paymentMethod.type === 'mollie_directdebit' && !isMollieManaged(paymentMethod)

// Mollie stores the account data itself and the local iban is cleared once a
// mandate is in place. A value sitting here again means the operator corrected
// the IBAN afterwards, so it still has to be pushed over to Mollie.
const canSyncMollieMandate = paymentMethod =>
  paymentMethod.type === 'mollie_directdebit' &&
  paymentMethod.sepa_mandate_status === 'active' &&
  Boolean(paymentMethod.iban)

// Forms für Zahlungsmethoden
const paymentMethodForm = useForm({
  id: null,
  type: '',
  status: 'active',
  is_default: false,
  // SEPA fields
  iban: '',
  account_holder: '',
  bank_name: '',
  sepa_mandate_status: 'pending',
  sepa_mandate_reference: '',
  // Credit card fields
  last_four: '',
  cardholder_name: '',
  expiry_date: '',
})

const newPaymentMethodForm = useForm({
  type: '',
  status: 'active',
  is_default: false,
  // SEPA fields
  iban: '',
  account_holder: '',
  bank_name: '',
  sepa_mandate_acknowledged: false,
  // Credit card fields
  card_number: '',
  cardholder_name: '',
  expiry_date: '',
  cvv: '',
  // Bank transfer fields
  notes: '',
})

const newPaymentForm = useForm({
  payment_type: 'regular',
  amount: '',
  description: '',
  due_date: '',
  paid_date: '',
  payment_method: '',
  status: 'pending',
  notes: ''
})

// Credit balance surfaced by the backend (verified server-side).
const creditBalanceCents = computed(() => props.member?.credit_balance_cents ?? 0)
const creditBalanceFormatted = computed(() => props.member?.credit_balance_formatted ?? '0,00 €')

const formatEuro = (cents) =>
  (cents / 100).toLocaleString('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €'

// Live preview of the balance after a top-up.
const newBalancePreview = computed(() => {
  const amount = parseFloat(String(newPaymentForm.amount).replace(',', '.'))
  const addCents = Number.isFinite(amount) ? Math.round(amount * 100) : 0
  return formatEuro(creditBalanceCents.value + Math.max(0, addCents))
})

// IBAN validation state für beide Forms
const ibanValidation = ref({
  newPaymentMethod: { isValid: false },
  editPaymentMethod: { isValid: false }
})

// IBAN validation handlers
const handleIbanValidation = (validation, context) => {
  ibanValidation.value[context] = validation
}

// A SEPA method normally needs a valid IBAN to be saved. Expiring it is the one
// case where an empty field is the point: the operator retires the method and
// clears the account data with it, so the IBAN is not demanded any more.
const canSubmitEditedPaymentMethod = computed(() => {
  if (!isSepaType(paymentMethodForm.type)) {
    return true
  }

  if (paymentMethodForm.status === 'expired' && !paymentMethodForm.iban) {
    return true
  }

  return ibanValidation.value.editPaymentMethod.isValid
})

// Payment history
const openAddPayment = () => {
  newPaymentForm.reset()
  newPaymentForm.payment_type = 'regular'
  newPaymentForm.due_date = new Date().toISOString().split('T')[0]
  showAddPaymentModal.value = true
}

// Opened from the header "Aufladen" action (exposed to the parent).
const openTopup = () => {
  newPaymentForm.reset()
  newPaymentForm.payment_type = 'topup'
  newPaymentForm.description = 'Guthaben-Aufladung: Überweisung'
  // Prefer bank transfer; fall back to the first available top-up method.
  const methods = topupPaymentMethods.value
  newPaymentForm.payment_method = methods.some(m => m.key === 'banktransfer')
    ? 'banktransfer'
    : (methods[0]?.key ?? 'banktransfer')
  newPaymentForm.paid_date = new Date().toISOString().split('T')[0]
  showAddPaymentModal.value = true
}

const closeAddPayment = () => {
  showAddPaymentModal.value = false
  newPaymentForm.reset()
}

const createPayment = () => {
  // A top-up is always booked as a completed credit deposit.
  if (newPaymentForm.payment_type === 'topup') {
    newPaymentForm.status = 'paid'
  }

  newPaymentForm.post(route('members.payments.store', props.member.id), {
    preserveScroll: true,
    onSuccess: () => {
      // No reload needed: the controller redirects back, so the response
      // already carries fresh props including the recalculated credit balance.
      // Reloading here would remount the layout and replay the flash toast.
      closeAddPayment()
    }
  })
}

defineExpose({ openTopup, openAddPayment })

const handleExecutePayment = (payment) => {
  executePayment(payment)
    .catch((error) => {
      console.error('Payment execution failed:', error)
    })
}

const executeSelectedPayments = () => {
  if (selectedPendingPaymentIds.value.length === 0) {
    return
  }

  executeBatchPayments(selectedPendingPaymentIds.value)
    .then((result) => {
      if (result && result.success) {
        selectedPaymentIds.value = []
      }
    })
    .catch((error) => {
      console.error('Batch execution failed:', error)
    })
}

const downloadInvoice = (payment) => {
  window.open(route('members.payments.invoice', {
    member: props.member.id,
    payment: payment.id
  }), '_blank')
}

const handlePaymentMarkedPaid = (payment) => {
  const paymentList = payments.value
  const paymentIndex = paymentList.findIndex(p => p.id === payment.id)

  if (paymentIndex !== -1) {
    const updatedPayments = [...paymentList]
    updatedPayments[paymentIndex] = {
      ...updatedPayments[paymentIndex],
      status: 'paid',
      status_text: 'Bezahlt',
      status_color: 'green',
      paid_date: new Date().toISOString()
    }

    updateLocalPayments(updatedPayments)
  }
}

// Payment Method Functions
const setAsDefault = (paymentMethod) => {
  settingDefault.value = paymentMethod.id

  router.put(route('members.payment-methods.set-default', {
    member: props.member.id,
    paymentMethod: paymentMethod.id
  }), {}, {
    preserveScroll: true,
    onSuccess: () => {
      settingDefault.value = null
    },
    onError: () => {
      settingDefault.value = null
    }
  })
}

const deactivatePaymentMethod = (paymentMethod) => {
  if (!confirm('Möchten Sie diese Zahlungsmethode wirklich deaktivieren?')) {
    return
  }

  deactivating.value = paymentMethod.id

  router.put(route('members.payment-methods.deactivate', {
    member: props.member.id,
    paymentMethod: paymentMethod.id
  }), {}, {
    preserveScroll: true,
    onSuccess: () => {
      deactivating.value = null
    },
    onError: () => {
      deactivating.value = null
    }
  })
}

const openEditPaymentMethod = (paymentMethod) => {
  paymentMethodForm.id = paymentMethod.id
  paymentMethodForm.type = paymentMethod.type
  paymentMethodForm.type_text = paymentMethod.type_text
  paymentMethodForm.status = paymentMethod.status
  paymentMethodForm.is_default = paymentMethod.is_default

  // SEPA fields
  if (isSepaType(paymentMethod.type)) {
    paymentMethodForm.iban = paymentMethod.iban || ''
    paymentMethodForm.account_holder = paymentMethod.account_holder || ''
    paymentMethodForm.bank_name = paymentMethod.bank_name || ''
    paymentMethodForm.sepa_mandate_status = paymentMethod.sepa_mandate_status || 'pending'
    paymentMethodForm.sepa_mandate_reference = paymentMethod.sepa_mandate_reference || ''
  }

  // Credit card fields
  if (isCreditCardType(paymentMethod.type)) {
    paymentMethodForm.last_four = paymentMethod.last_four || ''
    paymentMethodForm.cardholder_name = paymentMethod.cardholder_name || ''
    paymentMethodForm.expiry_date = formatDateForInput(paymentMethod.expiry_date)
  }

  // Bank transfer fields
  if (isBankTransferType(paymentMethod.type)) {
    paymentMethodForm.bank_name = paymentMethod.bank_name || ''
  }

  showEditPaymentMethodModal.value = true
}

const closeEditPaymentMethod = () => {
  showEditPaymentMethodModal.value = false
  paymentMethodForm.reset()
}

const updatePaymentMethod = () => {
  paymentMethodForm.put(route('members.payment-methods.update', {
    member: props.member.id,
    paymentMethod: paymentMethodForm.id
  }), {
    preserveScroll: true,
    onSuccess: () => {
      closeEditPaymentMethod()
    }
  })
}

const openAddPaymentMethod = () => {
  newPaymentMethodForm.reset()
  showAddPaymentMethodModal.value = true
}

const closeAddPaymentMethod = () => {
  showAddPaymentMethodModal.value = false
  newPaymentMethodForm.reset()
}

const createPaymentMethod = () => {
  const selectedMethod = availablePaymentMethodTypes.value.find(m => m.key === newPaymentMethodForm.type)

  const dataToSend = {
    type: newPaymentMethodForm.type,
    status: newPaymentMethodForm.status,
    is_default: newPaymentMethodForm.is_default,
    requires_mandate: selectedMethod?.requires_mandate || false,
  }

  if (isSepaType(newPaymentMethodForm.type)) {
    dataToSend.iban = newPaymentMethodForm.iban
    dataToSend.bank_name = newPaymentMethodForm.bank_name
    dataToSend.account_holder = newPaymentMethodForm.account_holder
    dataToSend.sepa_mandate_acknowledged = newPaymentMethodForm.sepa_mandate_acknowledged
    dataToSend.requires_mandate = true
  } else if (isCreditCardType(newPaymentMethodForm.type)) {
    const cardNumber = newPaymentMethodForm.card_number.replace(/\s+/g, '')
    dataToSend.last_four = cardNumber.slice(-4)
    dataToSend.cardholder_name = newPaymentMethodForm.cardholder_name
    dataToSend.expiry_date = newPaymentMethodForm.expiry_date
  } else if (isBankTransferType(newPaymentMethodForm.type)) {
    dataToSend.bank_name = newPaymentMethodForm.bank_name
    dataToSend.notes = newPaymentMethodForm.notes
  }

  if (newPaymentMethodForm.type.startsWith('mollie_')) {
    dataToSend.mollie_method_id = selectedMethod?.mollie_method_id || newPaymentMethodForm.type.replace('mollie_', '')
  }

  newPaymentMethodForm.transform(() => dataToSend).post(
    route('members.payment-methods.store', props.member.id),
    {
      preserveScroll: true,
      onSuccess: () => {
        closeAddPaymentMethod()
      }
    }
  )
}

const markSepaMandateAsSigned = (paymentMethod) => {
  if (!confirm('Möchten Sie dieses SEPA-Mandat als unterschrieben markieren?\n\nDies sollte nur erfolgen, wenn Sie die unterschriebene Mandatserteilung vom Kunden erhalten haben.')) {
    return
  }

  markingAsSigned.value = paymentMethod.id

  router.put(route('members.payment-methods.mark-signed', {
    member: props.member.id,
    paymentMethod: paymentMethod.id
  }), {}, {
    preserveScroll: true,
    onSuccess: () => {
      markingAsSigned.value = null
    },
    onError: (errors) => {
      markingAsSigned.value = null
      console.error('Fehler:', errors)
      alert('Das SEPA-Mandat konnte nicht als unterschrieben markiert werden.')
    }
  })
}

const activateSepaMandate = (paymentMethod) => {
  if (!confirm('Möchten Sie dieses SEPA-Mandat aktivieren?\n\nNach der Aktivierung können Lastschriften eingezogen werden.')) {
    return
  }

  activatingMandate.value = paymentMethod.id

  router.put(route('members.payment-methods.activate-mandate', {
    member: props.member.id,
    paymentMethod: paymentMethod.id
  }), {}, {
    preserveScroll: true,
    onSuccess: () => {
      activatingMandate.value = null
    },
    onError: (errors) => {
      activatingMandate.value = null
      console.error('Fehler:', errors)
      alert('Das SEPA-Mandat konnte nicht aktiviert werden.')
    }
  })
}

const syncMollieMandate = (paymentMethod) => {
  if (!confirm('Möchten Sie die geänderte IBAN an Mollie übertragen?\n\nDas bestehende SEPA-Mandat wird dabei widerrufen und mit den neuen Kontodaten neu erteilt.')) {
    return
  }

  syncingMandate.value = paymentMethod.id

  router.put(route('members.payment-methods.sync-mollie-mandate', {
    member: props.member.id,
    paymentMethod: paymentMethod.id
  }), {}, {
    preserveScroll: true,
    onSuccess: () => {
      syncingMandate.value = null
    },
    onError: (errors) => {
      syncingMandate.value = null
      console.error('Fehler:', errors)
      alert('Die IBAN konnte nicht an Mollie übertragen werden.')
    }
  })
}

const sendSepaMandate = (paymentMethod) => {
  sendingMandate.value = paymentMethod.id

  const message = `Diese Funktion ist noch nicht implementiert.

Das SEPA-Mandat kann aktuell nur manuell versendet werden:
1. Generieren Sie das Mandat-PDF
2. Versenden Sie es per E-Mail an: ${props.member.email}
3. Nach Erhalt der Unterschrift markieren Sie es als "unterschrieben"

Diese Funktion wird in einem zukünftigen Update automatisiert.`

  alert(message)

  setTimeout(() => {
    sendingMandate.value = null
  }, 500)
}

// Utility functions
const getStatusBadgeClass = (status) => {
  const classes = {
    active: 'bg-green-100 text-green-800',
    inactive: 'bg-gray-100 text-gray-800',
    paused: 'bg-yellow-100 text-yellow-800',
    cancelled: 'bg-red-100 text-red-800',
    paid: 'bg-green-100 text-green-800',
    pending: 'bg-orange-100 text-orange-800',
    failed: 'bg-red-100 text-red-800',
    expired: 'bg-gray-100 text-gray-800'
  }
  return classes[status] || 'bg-gray-100 text-gray-800'
}

const getStatusText = (status) => {
  const texts = {
    active: 'Aktiv',
    inactive: 'Inaktiv',
    paused: 'Pausiert',
    cancelled: 'Gekündigt',
    paid: 'Bezahlt',
    pending: 'Ausstehend',
    failed: 'Fehlgeschlagen',
    expired: 'Abgelaufen'
  }
  return texts[status] || status
}

const getPaymentMethodIcon = (type) => {
  const icons = {
    'sepa_direct_debit': Building2,
    'creditcard': CreditCard,
    'banktransfer': Building2,
    'cash': Banknote,
    'invoice': FileText,
    'mollie_creditcard': CreditCard,
    'mollie_directdebit': Building2,
    'mollie_paypal': WalletCards,
    'mollie_klarna': FileText,
  }
  return icons[type] || CreditCard
}

const getPaymentMethodIconClass = (type) => {
  const classes = {
    'sepa_direct_debit': 'bg-blue-100 text-blue-600',
    'creditcard': 'bg-purple-100 text-purple-600',
    'banktransfer': 'bg-green-100 text-green-600',
    'cash': 'bg-yellow-100 text-yellow-600',
    'invoice': 'bg-gray-100 text-gray-600'
  }
  return classes[type] || 'bg-gray-100 text-gray-600'
}

const getSepaMandateStatusClass = (status) => {
  const classes = {
    'pending': 'bg-yellow-100 text-yellow-800',
    'signed': 'bg-blue-100 text-blue-800',
    'active': 'bg-green-100 text-green-800',
    'revoked': 'bg-red-100 text-red-800',
    'expired': 'bg-gray-100 text-gray-800'
  }
  return classes[status] || 'bg-gray-100 text-gray-800'
}

const getSepaMandateStatusText = (status) => {
  const texts = {
    'pending': 'Unterschrift ausstehend',
    'signed': 'Unterschrieben',
    'active': 'Aktiv',
    'revoked': 'Widerrufen',
    'expired': 'Abgelaufen'
  }
  return texts[status] || status
}

// Watchers
watch(() => showAddPaymentMethodModal.value, (isOpen) => {
  if (isOpen && !newPaymentMethodForm.expiry_date) {
    newPaymentMethodForm.expiry_date = currentMonth.value
  }
})

watch(() => props.member?.payments, (newPayments) => {
  if (newPayments && Array.isArray(newPayments)) {
    updateLocalPayments(newPayments)
  }
}, { deep: true, immediate: true })

// Lifecycle
onMounted(() => {
  if (props.member?.payments) {
    updateLocalPayments(props.member.payments)
  }
})
</script>
