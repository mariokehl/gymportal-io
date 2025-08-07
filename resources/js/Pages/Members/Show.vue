<template>
  <AppLayout :title="`${member.first_name} ${member.last_name}`">
    <template #header>
      <div class="flex items-center">
        <Link
          :href="route('members.index')"
          class="text-gray-500 hover:text-gray-700 mr-4"
        >
          <ArrowLeft class="w-5 h-5" />
        </Link>
        Mitglied {{ !editMode ? 'anzeigen' : 'bearbeiten' }}: {{ member.first_name }} {{ member.last_name }}
      </div>
    </template>

    <div class="space-y-6">
      <!-- Header Section -->
      <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center justify-between">
          <div class="flex items-center space-x-4">
            <div class="h-16 w-16 rounded-full bg-indigo-500 flex items-center justify-center text-white text-xl font-bold">
              {{ getInitials(member.first_name, member.last_name) }}
            </div>
            <div>
              <h2 class="text-2xl font-bold text-gray-900">
                {{ member.first_name }} {{ member.last_name }}
              </h2>
              <p class="text-gray-600">Mitgliedsnummer: #{{ member.member_number }}</p>
              <span :class="getStatusBadgeClass(member.status)" class="inline-flex px-3 py-1 text-sm font-semibold rounded-full mt-2">
                {{ getStatusText(member.status) }}
              </span>
            </div>
          </div>
          <div class="flex items-center space-x-3">
            <Link
              :href="route('members.create')"
              class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 flex items-center gap-2"
            >
              <Plus class="w-4 h-4" />
              Neues Mitglied
            </Link>
            <button
              @click="editMode = !editMode"
              class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 flex items-center gap-2"
            >
              <Edit class="w-4 h-4" />
              {{ editMode ? 'Bearbeitung beenden' : 'Bearbeiten' }}
            </button>
          </div>
        </div>
      </div>

      <!-- Tabs Navigation -->
      <div class="bg-white rounded-lg shadow">
        <div class="border-b border-gray-200">
          <nav class="flex space-x-8 px-6">
            <button
              v-for="tab in tabs"
              :key="tab.id"
              @click="activeTab = tab.id"
              :class="[
                activeTab === tab.id
                  ? 'border-indigo-500 text-indigo-600'
                  : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
                'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm flex items-center gap-2'
              ]"
            >
              <component :is="tab.icon" class="w-4 h-4" />
              {{ tab.name }}
            </button>
          </nav>
        </div>

        <!-- Tab Content -->
        <div class="p-6">
          <!-- Personal Data Tab -->
          <div v-show="activeTab === 'personal'" class="space-y-6">
            <form @submit.prevent="updateMember">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Vorname <span class="text-red-500">*</span></label>
                  <input
                    v-model="form.first_name"
                    :disabled="!editMode"
                    type="text"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:bg-gray-50"
                  />
                  <div v-if="form.errors.first_name" class="text-red-500 text-sm mt-1">{{ form.errors.first_name }}</div>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Nachname <span class="text-red-500">*</span></label>
                  <input
                    v-model="form.last_name"
                    :disabled="!editMode"
                    type="text"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:bg-gray-50"
                  />
                  <div v-if="form.errors.last_name" class="text-red-500 text-sm mt-1">{{ form.errors.last_name }}</div>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">E-Mail <span class="text-red-500">*</span></label>
                  <input
                    v-model="form.email"
                    :disabled="!editMode"
                    type="email"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:bg-gray-50"
                  />
                  <div v-if="form.errors.email" class="text-red-500 text-sm mt-1">{{ form.errors.email }}</div>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Mobilfunknummer</label>
                  <input
                    v-model="form.phone"
                    :disabled="!editMode"
                    type="tel"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:bg-gray-50"
                  />
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Geburtsdatum</label>
                  <input
                    v-model="form.birth_date"
                    :disabled="!editMode"
                    type="date"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:bg-gray-50"
                  />
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Beitrittsdatum <span class="text-red-500">*</span></label>
                  <input
                    v-model="form.joined_date"
                    :disabled="!editMode"
                    type="date"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:bg-gray-50"
                  />
                </div>
                <div class="md:col-span-2">
                  <label class="block text-sm font-medium text-gray-700 mb-2">Straße und Hausnummer</label>
                  <input
                    v-model="form.address"
                    :disabled="!editMode"
                    type="text"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:bg-gray-50"
                  />
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">PLZ</label>
                  <input
                    v-model="form.postal_code"
                    :disabled="!editMode"
                    type="text"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:bg-gray-50"
                  />
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Stadt</label>
                  <input
                    v-model="form.city"
                    :disabled="!editMode"
                    type="text"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:bg-gray-50"
                  />
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Land</label>
                  <select
                    id="country"
                    v-model="form.country"
                    :disabled="!editMode"
                    class="w-full p-2 border border-gray-300 rounded-md bg-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                  >
                    <option value="DE">Deutschland</option>
                    <option value="AT">Österreich</option>
                    <option value="CH">Schweiz</option>
                  </select>
                </div>
                <div class="md:col-start-1">
                  <label class="block text-sm font-medium text-gray-700 mb-2">Notfallkontakt Name</label>
                  <input
                    v-model="form.emergency_contact_name"
                    :disabled="!editMode"
                    type="text"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:bg-gray-50"
                  />
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Notfallkontakt Telefon</label>
                  <input
                    v-model="form.emergency_contact_phone"
                    :disabled="!editMode"
                    type="tel"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:bg-gray-50"
                  />
                </div>
                <div class="md:col-span-2">
                  <label class="block text-sm font-medium text-gray-700 mb-2">Notizen</label>
                  <textarea
                    v-model="form.notes"
                    :disabled="!editMode"
                    rows="3"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:bg-gray-50"
                  ></textarea>
                </div>
              </div>
              <div v-if="editMode" class="mt-6 flex justify-end space-x-3">
                <button
                  type="button"
                  @click="cancelEdit"
                  class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
                >
                  Abbrechen
                </button>
                <button
                  type="submit"
                  :disabled="form.processing"
                  class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:opacity-50"
                >
                  {{ form.processing ? 'Speichern...' : 'Speichern' }}
                </button>
              </div>
            </form>
          </div>

          <!-- Membership Tab -->
          <div v-show="activeTab === 'membership'" class="space-y-6">
            <div v-if="member.memberships && member.memberships.length > 0">
              <div v-for="membership in member.memberships" :key="membership.id" class="border border-gray-200 rounded-lg p-4 mb-4">
                <div class="flex justify-between items-start">
                  <div>
                    <h4 class="text-lg font-semibold">
                        {{ membership.membership_plan.name }}
                        <span :class="getStatusBadgeClass(membership.status)" class="inline-flex px-2 py-1 text-xs font-semibold rounded-full ml-1">
                          {{ getStatusText(membership.status) }}
                        </span>
                    </h4>
                    <p class="text-gray-600">{{ membership.membership_plan.description }}</p>
                    <div class="mt-2 space-y-1">
                      <p class="text-sm"><span class="font-medium">Laufzeit:</span> {{ formatDate(membership.start_date) }} - {{ formatDate(membership.end_date) }}</p>
                    </div>
                  </div>
                  <div class="text-right">
                    <p class="text-2xl font-bold text-indigo-600">{{ formatCurrency(membership.membership_plan.price) }}</p>
                    <p class="text-sm text-gray-500">pro {{ getBillingCycleText(membership.membership_plan.billing_cycle) }}</p>
                  </div>
                </div>
                <div v-if="membership.pause_start_date" class="mt-3 p-3 bg-yellow-50 rounded-md">
                  <p class="text-sm text-yellow-800">
                    <Clock class="w-4 h-4 inline mr-1" />
                    Pausiert vom {{ formatDate(membership.pause_start_date) }} bis {{ formatDate(membership.pause_end_date) }}
                  </p>
                </div>
              </div>
            </div>
            <div v-else class="text-center py-8">
              <UserX class="w-12 h-12 text-gray-400 mx-auto mb-4" />
              <p class="text-gray-500">Keine Mitgliedschaften vorhanden</p>
            </div>
          </div>

          <!-- Payments & Payment Methods Tab -->
          <div v-show="activeTab === 'payments'" class="space-y-8">

            <!-- Payment Methods Section -->
            <div class="space-y-6">
              <div class="flex justify-between items-center">
                <h3 class="text-lg font-semibold text-gray-900">Zahlungsmethoden</h3>
                <button
                  @click="openAddPaymentMethod"
                  type="button"
                  class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 flex items-center gap-2"
                >
                  <Plus class="w-4 h-4" />
                  Neue Zahlungsmethode
                </button>
              </div>

              <div v-if="member.payment_methods && member.payment_methods.length > 0" class="space-y-4">
                <div
                  v-for="paymentMethod in member.payment_methods"
                  :key="paymentMethod.id"
                  class="border border-gray-200 rounded-lg p-4"
                >
                  <div class="flex justify-between items-start">
                    <div class="flex items-center space-x-4">
                      <div class="p-2 rounded-lg" :class="getPaymentMethodIconClass(paymentMethod.type)">
                        <component :is="getPaymentMethodIcon(paymentMethod.type)" class="w-6 h-6" />
                      </div>
                      <div>
                        <div class="flex items-center gap-2">
                          <h4 class="font-semibold text-gray-900">{{ getPaymentMethodName(paymentMethod.type) }}</h4>
                          <span v-if="paymentMethod.is_default" class="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full">
                            Standard
                          </span>
                          <span :class="getStatusBadgeClass(paymentMethod.status)" class="inline-flex px-2 py-1 text-xs font-semibold rounded-full">
                            {{ getStatusText(paymentMethod.status) }}
                          </span>
                        </div>

                        <!-- SEPA Details -->
                        <div v-if="isSepaType(paymentMethod.type)" class="mt-1 space-y-1">
                          <p class="text-sm text-gray-600">
                            IBAN: {{ paymentMethod.masked_iban || '****' }}
                          </p>
                          <div v-if="paymentMethod.sepa_mandate_reference" class="text-sm text-gray-600">
                            Mandatsreferenz: {{ paymentMethod.sepa_mandate_reference }}
                          </div>
                          <div v-if="paymentMethod.sepa_mandate_status" class="flex items-center gap-2">
                            <span class="text-sm text-gray-500">SEPA-Mandat:</span>
                            <span
                              :class="getSepaMandateStatusClass(paymentMethod.sepa_mandate_status)"
                              class="inline-flex px-2 py-1 text-xs font-semibold rounded-full"
                            >
                              {{ getSepaMandateStatusText(paymentMethod.sepa_mandate_status) }}
                            </span>
                          </div>
                          <div v-if="paymentMethod.sepa_mandate_signed_at" class="text-sm text-gray-500">
                            Unterschrieben am: {{ formatDate(paymentMethod.sepa_mandate_signed_at) }}
                          </div>
                        </div>

                        <!-- Credit Card Details -->
                        <div v-else-if="isCreditCardType(paymentMethod.type)" class="mt-1 space-y-1">
                          <p class="text-sm text-gray-600">
                            **** **** **** {{ paymentMethod.last_four }}
                          </p>
                          <p v-if="paymentMethod.cardholder_name" class="text-sm text-gray-600">
                            {{ paymentMethod.cardholder_name }}
                          </p>
                          <p v-if="paymentMethod.expiry_date" class="text-sm text-gray-600">
                            Gültig bis: {{ formatMonthYear(paymentMethod.expiry_date) }}
                          </p>
                        </div>

                        <!-- Bank Transfer Details -->
                        <div v-else-if="isBankTransferType(paymentMethod.type)" class="mt-1">
                          <p v-if="paymentMethod.bank_name" class="text-sm text-gray-600">{{ paymentMethod.bank_name }}</p>
                        </div>

                        <!-- Credit Card Details -->
                        <div v-else-if="paymentMethod.type === 'creditcard'" class="mt-1 space-y-1">
                          <p class="text-sm text-gray-600">
                            **** **** **** {{ paymentMethod.last_four }}
                          </p>
                          <p v-if="paymentMethod.cardholder_name" class="text-sm text-gray-600">
                            {{ paymentMethod.cardholder_name }}
                          </p>
                          <p v-if="paymentMethod.expiry_date" class="text-sm text-gray-600">
                            Gültig bis: {{ formatMonthYear(paymentMethod.expiry_date) }}
                          </p>
                        </div>

                        <!-- Bank Transfer Details -->
                        <div v-else-if="paymentMethod.type === 'banktransfer'" class="mt-1">
                          <p v-if="paymentMethod.bank_name" class="text-sm text-gray-600">{{ paymentMethod.bank_name }}</p>
                        </div>
                      </div>
                    </div>

                    <div class="flex items-center space-x-2">
                      <button
                        v-if="!paymentMethod.is_default && paymentMethod.status === 'active'"
                        @click="setAsDefault(paymentMethod)"
                        type="button"
                        class="text-sm text-indigo-600 hover:text-indigo-800"
                        :disabled="settingDefault === paymentMethod.id"
                      >
                        {{ settingDefault === paymentMethod.id ? 'Wird gesetzt...' : 'Als Standard setzen' }}
                      </button>
                      <button
                        @click="openEditPaymentMethod(paymentMethod)"
                        type="button"
                        class="text-sm text-gray-600 hover:text-gray-800"
                      >
                        Bearbeiten
                      </button>
                      <button
                        v-if="paymentMethod.status === 'active'"
                        @click="deactivatePaymentMethod(paymentMethod)"
                        type="button"
                        class="text-sm text-red-600 hover:text-red-800"
                        :disabled="deactivating === paymentMethod.id"
                      >
                        {{ deactivating === paymentMethod.id ? 'Deaktivieren...' : 'Deaktivieren' }}
                      </button>
                    </div>
                  </div>

                  <!-- SEPA Mandate Actions -->
                  <div v-if="paymentMethod.requires_mandate && paymentMethod.sepa_mandate_status === 'pending'" class="mt-4 p-3 bg-yellow-50 rounded-md">
                    <div class="flex items-center justify-between">
                      <div class="flex items-center">
                        <AlertCircle class="w-5 h-5 text-yellow-600 mr-2" />
                        <span class="text-sm text-yellow-800">SEPA-Mandat muss noch unterschrieben werden</span>
                      </div>
                      <div class="flex space-x-2">
                        <button
                          type="button"
                          class="bg-yellow-600 text-white px-3 py-1 rounded text-sm hover:bg-yellow-700"
                        >
                          Mandat versenden
                        </button>
                        <button
                          type="button"
                          class="bg-green-600 text-white px-3 py-1 rounded text-sm hover:bg-green-700"
                        >
                          Als unterschrieben markieren
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
                  class="mt-3 bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 flex items-center gap-2 mx-auto"
                >
                  <Plus class="w-4 h-4" />
                  Erste Zahlungsmethode hinzufügen
                </button>
              </div>
            </div>

            <!-- Divider -->
            <div class="border-t border-gray-200"></div>

            <!-- Payment History Section -->
            <div class="space-y-6">
              <div class="flex justify-between items-center">
                <h3 class="text-lg font-semibold text-gray-900">Zahlungshistorie</h3>
                <div class="flex items-center space-x-2">
                  <select class="border border-gray-300 rounded-md px-3 py-1 text-sm">
                    <option value="">Alle Status</option>
                    <option value="paid">Bezahlt</option>
                    <option value="pending">Ausstehend</option>
                    <option value="failed">Fehlgeschlagen</option>
                  </select>
                  <button
                    type="button"
                    class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 flex items-center gap-2"
                  >
                    <Plus class="w-4 h-4" />
                    Zahlung hinzufügen
                  </button>
                </div>
              </div>

              <div v-if="member.payments && member.payments.length > 0">
                <div class="overflow-x-auto">
                  <table class="w-full">
                    <thead class="bg-gray-50">
                      <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Datum</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Betrag</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Beschreibung</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Zahlungsmethode</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aktionen</th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                      <tr v-for="payment in member.payments" :key="payment.id" class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm">
                          <div>{{ formatDate(payment.paid_date || payment.due_date) }}</div>
                          <div v-if="payment.due_date && payment.status === 'pending'" class="text-xs text-gray-500">
                            Fällig: {{ formatDate(payment.due_date) }}
                          </div>
                        </td>
                        <td class="px-4 py-3 text-sm font-medium">{{ formatCurrency(payment.amount) }}</td>
                        <td class="px-4 py-3 text-sm">
                          <div>{{ payment.description }}</div>
                          <div v-if="payment.transaction_id" class="text-xs text-gray-500">
                            ID: {{ payment.transaction_id }}
                          </div>
                        </td>
                        <td class="px-4 py-3 text-sm">
                          <span :class="getPaymentStatusClass(payment.status)" class="inline-flex px-2 py-1 text-xs font-semibold rounded-full">
                            {{ getPaymentStatusText(payment.status) }}
                          </span>
                          <div v-if="payment.status === 'pending' && isPaymentOverdue(payment)" class="text-xs text-red-600 mt-1">
                            Überfällig
                          </div>
                        </td>
                        <td class="px-4 py-3 text-sm">
                          <div class="flex items-center gap-2">
                            <component :is="getPaymentMethodIcon(payment.payment_method)" class="w-4 h-4" />
                            {{ getPaymentMethodName(payment.payment_method) }}
                          </div>
                        </td>
                        <td class="px-4 py-3 text-sm">
                          <div class="flex items-center space-x-2">
                            <button
                              v-if="payment.status === 'pending'"
                              type="button"
                              class="text-green-600 hover:text-green-800"
                              title="Als bezahlt markieren"
                            >
                              <CheckCircle class="w-4 h-4" />
                            </button>
                            <button
                              type="button"
                              class="text-gray-600 hover:text-gray-800"
                              title="Details anzeigen"
                            >
                              <Eye class="w-4 h-4" />
                            </button>
                            <button
                              v-if="payment.status === 'paid'"
                              type="button"
                              class="text-blue-600 hover:text-blue-800"
                              title="Rechnung herunterladen"
                            >
                              <Download class="w-4 h-4" />
                            </button>
                          </div>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
              <div v-else class="text-center py-8 bg-gray-50 rounded-lg">
                <CreditCard class="w-12 h-12 text-gray-400 mx-auto mb-4" />
                <p class="text-gray-500">Keine Zahlungen vorhanden</p>
                <button
                  type="button"
                  class="mt-3 bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 flex items-center gap-2 mx-auto"
                >
                  <Plus class="w-4 h-4" />
                  Erste Zahlung hinzufügen
                </button>
              </div>
            </div>
          </div>

          <!-- Check-ins Tab -->
          <div v-show="activeTab === 'checkins'" class="space-y-4">
            <div v-if="member.check_ins && member.check_ins.length > 0">
              <div class="overflow-x-auto">
                <table class="w-full">
                  <thead class="bg-gray-50">
                    <tr>
                      <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Datum</th>
                      <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Check-In</th>
                      <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Check-Out</th>
                      <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Dauer</th>
                      <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Methode</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-200">
                    <tr v-for="checkin in member.check_ins" :key="checkin.id" class="hover:bg-gray-50">
                      <td class="px-4 py-3 text-sm">{{ formatDate(checkin.check_in_time) }}</td>
                      <td class="px-4 py-3 text-sm">{{ formatTime(checkin.check_in_time) }}</td>
                      <td class="px-4 py-3 text-sm">
                        {{ checkin.check_out_time ? formatTime(checkin.check_out_time) : '-' }}
                      </td>
                      <td class="px-4 py-3 text-sm">
                        {{ checkin.check_out_time ? calculateDuration(checkin.check_in_time, checkin.check_out_time) : '-' }}
                      </td>
                      <td class="px-4 py-3 text-sm">{{ checkin.check_in_method || 'Unbekannt' }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
            <div v-else class="text-center py-8">
              <Clock class="w-12 h-12 text-gray-400 mx-auto mb-4" />
              <p class="text-gray-500">Keine Check-Ins vorhanden</p>
            </div>
          </div>
        </div>
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
                    :value="getPaymentMethodName(paymentMethodForm.type)"
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
                    <label class="block text-sm font-medium text-gray-700 mb-2">IBAN</label>
                    <input
                      v-model="paymentMethodForm.iban"
                      type="text"
                      placeholder="DE89 3704 0044 0532 0130 00"
                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
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
                :disabled="paymentMethodForm.processing"
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
                    <input
                      v-model="newPaymentMethodForm.iban"
                      type="text"
                      placeholder="DE89 3704 0044 0532 0130 00"
                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
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
                      />
                      <span class="ml-2 text-sm text-gray-700">SEPA-Mandat wurde zur Kenntnis genommen</span>
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
                :disabled="newPaymentMethodForm.processing || !newPaymentMethodForm.type"
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
  </AppLayout>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useForm, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import {
  User, FileText, Clock, CreditCard, Plus, Edit,
  UserX, ArrowLeft, Wallet, AlertCircle, CheckCircle,
  Eye, Download, Building2, Smartphone, Banknote, X
} from 'lucide-vue-next'

const props = defineProps({
  member: Object,
  availablePaymentMethods: {
    type: Array,
    default: () => []
  }
})

const editMode = ref(false)
const activeTab = ref('personal')
const showEditPaymentMethodModal = ref(false)
const showAddPaymentMethodModal = ref(false)
const settingDefault = ref(null)
const deactivating = ref(null)

const tabs = [
  { id: 'personal', name: 'Persönliche Daten', icon: User },
  { id: 'membership', name: 'Mitgliedschaften', icon: FileText },
  { id: 'payments', name: 'Zahlungen', icon: CreditCard },
  { id: 'checkins', name: 'Check-Ins', icon: Clock },
]

const formatDateForInput = (dateString) => {
  return dateString ? dateString.split('T')[0] : '';
};

// Computed property für verfügbare Zahlungsmethoden
const availablePaymentMethodTypes = computed(() => {
  // Wenn availablePaymentMethods als Prop übergeben wurde
  if (props.availablePaymentMethods && props.availablePaymentMethods.length > 0) {
    return props.availablePaymentMethods
  }

  // Wenn member.gym.enabled_payment_methods vorhanden ist
  if (props.member?.gym?.enabled_payment_methods) {
    return props.member.gym.enabled_payment_methods
  }

  // Fallback auf leeres Array
  return []
})

// Helper um zu prüfen ob ein Payment Method Type SEPA ist
const isSepaType = (type) => {
  return type === 'sepa_direct_debit' ||
         type === 'sepa' ||
         type === 'mollie_directdebit'
}

// Helper um zu prüfen ob ein Payment Method Type Kreditkarte ist
const isCreditCardType = (type) => {
  return type === 'creditcard' ||
         type === 'mollie_creditcard' ||
         type?.includes('creditcard')
}

// Helper um zu prüfen ob ein Payment Method Type Banküberweisung ist
const isBankTransferType = (type) => {
  return type === 'banktransfer' ||
         type === 'mollie_banktransfer' ||
         type?.includes('banktransfer')
}

const form = useForm({
  member_number: props.member.member_number,
  first_name: props.member.first_name,
  last_name: props.member.last_name,
  email: props.member.email,
  phone: props.member.phone,
  birth_date: formatDateForInput(props.member.birth_date),
  address: props.member.address,
  city: props.member.city,
  postal_code: props.member.postal_code,
  country: props.member.country,
  status: props.member.status,
  emergency_contact_name: props.member.emergency_contact_name,
  emergency_contact_phone: props.member.emergency_contact_phone,
  notes: props.member.notes,
  joined_date: formatDateForInput(props.member.joined_date),
})

const paymentMethodForm = useForm({
  id: null,
  type: '',
  status: 'active',
  is_default: false,
  // SEPA fields
  iban: '',
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
  paymentMethodForm.status = paymentMethod.status
  paymentMethodForm.is_default = paymentMethod.is_default

  // SEPA fields
  if (isSepaType(paymentMethod.type)) {
    paymentMethodForm.iban = paymentMethod.iban || ''
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
  // Finde die gewählte Payment Method aus den verfügbaren Methoden
  const selectedMethod = availablePaymentMethodTypes.value.find(m => m.key === newPaymentMethodForm.type)

  // Nur relevante Felder für den gewählten Typ senden
  const dataToSend = {
    type: newPaymentMethodForm.type,
    status: newPaymentMethodForm.status,
    is_default: newPaymentMethodForm.is_default,
    requires_mandate: selectedMethod?.requires_mandate || false,
  }

  // Je nach Typ nur die relevanten Felder hinzufügen
  if (isSepaType(newPaymentMethodForm.type)) {
    dataToSend.iban = newPaymentMethodForm.iban
    dataToSend.bank_name = newPaymentMethodForm.bank_name
    dataToSend.account_holder = newPaymentMethodForm.account_holder
    dataToSend.sepa_mandate_acknowledged = newPaymentMethodForm.sepa_mandate_acknowledged
    dataToSend.requires_mandate = true
  } else if (isCreditCardType(newPaymentMethodForm.type)) {
    // Nur die letzten 4 Ziffern speichern
    const cardNumber = newPaymentMethodForm.card_number.replace(/\s+/g, '')
    dataToSend.last_four = cardNumber.slice(-4)
    dataToSend.cardholder_name = newPaymentMethodForm.cardholder_name
    dataToSend.expiry_date = newPaymentMethodForm.expiry_date
    // CVV wird normalerweise nicht gespeichert, nur für die Validierung verwendet
  } else if (isBankTransferType(newPaymentMethodForm.type)) {
    dataToSend.bank_name = newPaymentMethodForm.bank_name
    dataToSend.notes = newPaymentMethodForm.notes
  }

  // Für Mollie-Methoden zusätzliche Informationen hinzufügen
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

const getInitials = (firstName, lastName) => {
  return `${firstName?.charAt(0) || ''}${lastName?.charAt(0) || ''}`.toUpperCase()
}

const getStatusBadgeClass = (status) => {
  const classes = {
    active: 'bg-green-100 text-green-800',
    inactive: 'bg-gray-100 text-gray-800',
    suspended: 'bg-yellow-100 text-yellow-800',
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
    suspended: 'Pausiert',
    cancelled: 'Gekündigt',
    paid: 'Bezahlt',
    pending: 'Ausstehend',
    failed: 'Fehlgeschlagen',
    expired: 'Abgelaufen'
  }
  return texts[status] || status
}

const getBillingCycleText = (cycle) => {
  const cycles = {
    'monthly': 'Monat',
    'quarterly': 'Quartal',
    'yearly': 'Jahr'
  }
  return cycles[cycle] || cycle
}

// Payment Method Helper Functions
const getPaymentMethodName = (type) => {
  // Erst in den verfügbaren Zahlungsmethoden suchen
  const availableMethod = availablePaymentMethodTypes.value.find(m => m.key === type)
  if (availableMethod) {
    return availableMethod.name
  }

  // Fallback auf statische Namen
  const names = {
    'sepa_direct_debit': 'SEPA-Lastschrift',
    'sepa': 'SEPA-Lastschrift',
    'creditcard': 'Kreditkarte',
    'mollie_creditcard': 'Mollie: Kreditkarte',
    'banktransfer': 'Banküberweisung',
    'cash': 'Barzahlung',
    'invoice': 'Rechnung',
  }
  return names[type] || type
}

const getPaymentMethodIcon = (type) => {
  const icons = {
    'sepa_direct_debit': Building2,
    'sepa': Building2,
    'creditcard': CreditCard,
    'banktransfer': Building2,
    'cash': Banknote,
    'invoice': FileText
  }
  return icons[type] || CreditCard
}

const getPaymentMethodIconClass = (type) => {
  const classes = {
    'sepa_direct_debit': 'bg-blue-100 text-blue-600',
    'sepa': 'bg-blue-100 text-blue-600',
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

const getPaymentStatusClass = (status) => getStatusBadgeClass(status)

const getPaymentStatusText = (status) => {
  const texts = {
    paid: 'Bezahlt',
    pending: 'Ausstehend',
    failed: 'Fehlgeschlagen',
    cancelled: 'Storniert',
    refunded: 'Erstattet'
  }
  return texts[status] || status
}

const formatDate = (date) => {
  if (!date) return '-'
  return new Date(date).toLocaleDateString('de-DE')
}

const formatTime = (datetime) => {
  if (!datetime) return '-'
  return new Date(datetime).toLocaleTimeString('de-DE', {
    hour: '2-digit',
    minute: '2-digit'
  })
}

const formatMonthYear = (date) => {
  if (!date) return '-'
  return new Date(date).toLocaleDateString('de-DE', {
    month: '2-digit',
    year: '2-digit'
  })
}

const formatCurrency = (amount) => {
  return new Intl.NumberFormat('de-DE', {
    style: 'currency',
    currency: 'EUR'
  }).format(amount)
}

const calculateDuration = (checkIn, checkOut) => {
  if (!checkIn || !checkOut) return '-'
  const duration = new Date(checkOut) - new Date(checkIn)
  const hours = Math.floor(duration / (1000 * 60 * 60))
  const minutes = Math.floor((duration % (1000 * 60 * 60)) / (1000 * 60))
  return `${hours}h ${minutes}m`
}

const isPaymentOverdue = (payment) => {
  if (payment.status !== 'pending' || !payment.due_date) return false
  return new Date(payment.due_date) < new Date()
}

const updateMember = () => {
  form.put(route('members.update', props.member.id), {
    onSuccess: () => {
      editMode.value = false
    }
  })
}

const cancelEdit = () => {
  form.reset()
  editMode.value = false
}

onMounted(() => {
  const urlParams = new URLSearchParams(window.location.search)
  if (urlParams.get('edit') === 'true') {
    editMode.value = true
  }
})
</script>
