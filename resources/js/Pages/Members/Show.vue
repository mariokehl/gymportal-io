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
                {{ member.salutation ? member.salutation + ' ' : '' }}{{ member.first_name }} {{ member.last_name }}
              </h2>
              <p class="text-gray-600">Mitgliedsnummer: #{{ member.member_number }}</p>

              <div class="mt-2">
                <!-- Im Bearbeitungsmodus: Editierbare Status-Komponente -->
                <MemberStatusEditor
                  v-if="editMode"
                  :member="member"
                  :status="member.status"
                  @status-changed="handleStatusChanged"
                  @status-changing="handleStatusChanging"
                />

                <!-- Im Anzeigemodus: Readonly Badge -->
                <MemberStatusBadge
                  v-else
                  :status="member.status"
                  :show-icon="true"
                />
              </div>
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
          <nav class="flex space-x-8 px-6 overflow-x-auto">
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

              <!-- Badge für Status-History Tab -->
              <span
                v-if="tab.id === 'history' && member.status_history?.length > 0"
                class="ml-1 bg-gray-100 text-gray-600 text-xs px-2 py-0.5 rounded-full"
              >
                {{ member.status_history.length }}
              </span>

              <!-- Badge für Access Tab -->
              <span
                v-if="tab.id === 'access' && getActiveAccessCount() > 0"
                class="ml-1 bg-green-100 text-green-600 text-xs px-2 py-0.5 rounded-full"
              >
                {{ getActiveAccessCount() }}
              </span>
            </button>
          </nav>
        </div>

        <!-- Tab Content -->
        <div class="p-6">
          <!-- Personal Data Tab -->
          <div v-show="activeTab === 'personal'" class="space-y-6">
            <form @submit.prevent="updateMember">
              <!-- Anrede, Vorname, Nachname in einer Zeile -->
              <div class="grid grid-cols-1 md:grid-cols-8 gap-6 mb-6">
                <!-- Anrede (25% = 2/8 Spalten) -->
                <div class="md:col-span-2">
                  <label class="block text-sm font-medium text-gray-700 mb-2">
                    Anrede <span class="text-red-500">*</span>
                  </label>
                  <select
                    v-model="form.salutation"
                    :disabled="!editMode"
                    class="w-full px-3 py-2.5 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:bg-gray-50"
                  >
                    <option value="">Anrede auswählen</option>
                    <option value="Herr">Herr</option>
                    <option value="Frau">Frau</option>
                    <option value="Divers">Divers</option>
                  </select>
                  <div v-if="form.errors.salutation" class="text-red-500 text-sm mt-1">{{ form.errors.salutation }}</div>
                </div>
                <div class="md:col-span-3">
                  <label class="block text-sm font-medium text-gray-700 mb-2">Vorname <span class="text-red-500">*</span></label>
                  <input
                    v-model="form.first_name"
                    :disabled="!editMode"
                    type="text"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:bg-gray-50"
                  />
                  <div v-if="form.errors.first_name" class="text-red-500 text-sm mt-1">{{ form.errors.first_name }}</div>
                </div>
                <div class="md:col-span-3">
                  <label class="block text-sm font-medium text-gray-700 mb-2">Nachname <span class="text-red-500">*</span></label>
                  <input
                    v-model="form.last_name"
                    :disabled="!editMode"
                    type="text"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:bg-gray-50"
                  />
                  <div v-if="form.errors.last_name" class="text-red-500 text-sm mt-1">{{ form.errors.last_name }}</div>
                </div>
              </div>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
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
                      <p v-if="membership.membership_plan.commitment_months" class="text-sm">
                        <span class="font-medium">Mindestlaufzeit:</span> {{ membership.membership_plan.commitment_months }} Monate
                      </p>
                      <p v-if="membership.membership_plan.cancellation_period_days" class="text-sm">
                        <span class="font-medium">Kündigungsfrist:</span> {{ membership.membership_plan.cancellation_period_days }} Tage
                      </p>
                      <p v-if="membership.cancellation_date" class="text-sm text-red-600">
                        <span class="font-medium">Gekündigt zum:</span> {{ formatDate(membership.cancellation_date) }}
                      </p>
                    </div>
                  </div>
                  <div class="flex flex-col items-end">
                    <!-- Action buttons for memberships - above the price -->
                    <div v-if="membership.status === 'active' || membership.status === 'paused' || membership.status === 'pending'" class="flex items-center justify-end gap-2 sm:gap-3 mb-3">
                      <!-- Activate pending membership -->
                      <button
                        v-if="membership.status === 'pending'"
                        @click="activateMembership(membership)"
                        type="button"
                        class="text-sm text-green-600 hover:text-green-800 font-medium flex items-center gap-1 transition-colors"
                        :disabled="activatingMembership === membership.id"
                      >
                        <CheckCircle class="w-4 h-4" />
                        <span>{{ activatingMembership === membership.id ? 'Wird aktiviert...' : 'Aktivieren' }}</span>
                      </button>

                      <!-- Pause button -->
                      <button
                        v-if="membership.status === 'active' && !membership.cancellation_date"
                        @click="openPauseMembership(membership)"
                        type="button"
                        class="text-sm text-yellow-600 hover:text-yellow-800 font-medium flex items-center gap-1 transition-colors"
                        :disabled="pausingMembership === membership.id"
                      >
                        <Clock class="w-4 h-4" />
                        <span class="hidden sm:inline">{{ pausingMembership === membership.id ? 'Wird stillgelegt...' : 'Stilllegen' }}</span>
                        <span class="sm:hidden">{{ pausingMembership === membership.id ? '...' : 'Pause' }}</span>
                      </button>

                      <!-- Continue button -->
                      <button
                        v-if="membership.status === 'paused'"
                        @click="resumeMembership(membership)"
                        type="button"
                        class="text-sm text-green-600 hover:text-green-800 font-medium flex items-center gap-1 transition-colors"
                        :disabled="resumingMembership === membership.id"
                      >
                        <PlayCircle class="w-4 h-4" />
                        <span class="hidden sm:inline">{{ resumingMembership === membership.id ? 'Wird aktiviert...' : 'Fortsetzen' }}</span>
                        <span class="sm:hidden">{{ resumingMembership === membership.id ? '...' : 'Weiter' }}</span>
                      </button>

                      <!-- Dividing line -->
                      <div v-if="(membership.status === 'active' || membership.status === 'paused') && !membership.cancellation_date" class="hidden sm:block w-px h-4 bg-gray-300"></div>

                      <!-- Cancel button -->
                      <button
                        v-if="!membership.cancellation_date && membership.status !== 'pending'"
                        @click="openCancelMembership(membership)"
                        type="button"
                        class="text-sm text-red-600 hover:text-red-800 font-medium flex items-center gap-1 transition-colors"
                        :disabled="cancellingMembership === membership.id"
                      >
                        <XCircle class="w-4 h-4" />
                        <span class="hidden sm:inline">{{ cancellingMembership === membership.id ? 'Wird gekündigt...' : 'Kündigen' }}</span>
                        <span class="sm:hidden">{{ cancellingMembership === membership.id ? '...' : 'Kündigen' }}</span>
                      </button>

                      <!-- Cancel cancellation button -->
                      <button
                        v-if="membership.cancellation_date"
                        @click="revokeCancellation(membership)"
                        type="button"
                        class="text-sm text-blue-600 hover:text-blue-800 font-medium flex items-center gap-1 transition-colors"
                        :disabled="revokingCancellation === membership.id"
                      >
                        <RotateCcw class="w-4 h-4" />
                        <span class="hidden sm:inline">{{ revokingCancellation === membership.id ? 'Wird zurückgenommen...' : 'Kündigung zurücknehmen' }}</span>
                        <span class="sm:hidden">{{ revokingCancellation === membership.id ? '...' : 'Zurück' }}</span>
                      </button>
                    </div>

                    <!-- Price display -->
                    <div class="text-right">
                      <p class="text-2xl font-bold text-indigo-600">{{ formatCurrency(membership.membership_plan.price) }}</p>
                      <p class="text-sm text-gray-500">pro {{ getBillingCycleText(membership.membership_plan.billing_cycle) }}</p>
                    </div>
                  </div>
                </div>

                <div v-if="membership.status === 'pending'" class="mt-3 p-3 bg-orange-50 rounded-md">
                  <p class="text-sm text-orange-800">
                    <AlertCircle class="w-4 h-4 inline mr-1" />
                    Diese Mitgliedschaft wartet auf Aktivierung
                  </p>
                </div>

                <div v-if="membership.pause_start_date" class="mt-3 p-3 bg-yellow-50 rounded-md">
                  <p class="text-sm text-yellow-800">
                    <Clock class="w-4 h-4 inline mr-1" />
                    Pausiert vom {{ formatDate(membership.pause_start_date) }} bis {{ formatDate(membership.pause_end_date) }}
                  </p>
                </div>

                <div v-if="membership.cancellation_date" class="mt-3 p-3 bg-red-50 rounded-md">
                  <p class="text-sm text-red-800">
                    <AlertCircle class="w-4 h-4 inline mr-1" />
                    Kündigung wirksam zum {{ formatDate(membership.cancellation_date) }}
                    <span v-if="membership.cancellation_reason" class="block mt-1">
                      Grund: {{ membership.cancellation_reason }}
                    </span>
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

            <!-- Flash Messages -->
            <div v-if="$page.props.flash?.message" class="bg-green-50 border border-green-200 rounded-md p-4">
              <div class="flex">
                <CheckCircle class="w-5 h-5 text-green-400 mr-2" />
                <div class="text-sm text-green-800">{{ $page.props.flash.message }}</div>
              </div>
            </div>

            <div v-if="$page.props.flash?.error" class="bg-red-50 border border-red-200 rounded-md p-4">
              <div class="flex">
                <XCircle class="w-5 h-5 text-red-400 mr-2" />
                <div class="text-sm text-red-800">{{ $page.props.flash.error }}</div>
              </div>
            </div>

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
                          <h4 class="font-semibold text-gray-900">{{ paymentMethod.type_text }}</h4>
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
                          @click="sendSepaMandate(paymentMethod)"
                          :disabled="sendingMandate === paymentMethod.id"
                          class="bg-yellow-600 text-white px-3 py-1 rounded text-sm hover:bg-yellow-700 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                          {{ sendingMandate === paymentMethod.id ? 'Wird verarbeitet...' : 'Mandat versenden' }}
                        </button>
                        <button
                          type="button"
                          @click="markSepaMandateAsSigned(paymentMethod)"
                          :disabled="markingAsSigned === paymentMethod.id"
                          class="bg-green-600 text-white px-3 py-1 rounded text-sm hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                          {{ markingAsSigned === paymentMethod.id ? 'Wird markiert...' : 'Als unterschrieben markieren' }}
                        </button>
                      </div>
                    </div>
                  </div>

                  <!-- Zusätzliche Aktionen für unterschriebene Mandate -->
                  <div v-if="paymentMethod.requires_mandate && paymentMethod.sepa_mandate_status === 'signed'" class="mt-4 p-3 bg-blue-50 rounded-md">
                    <div class="flex items-center justify-between">
                      <div class="flex items-center">
                        <CheckCircle class="w-5 h-5 text-blue-600 mr-2" />
                        <span class="text-sm text-blue-800">SEPA-Mandat wurde unterschrieben und wartet auf Aktivierung</span>
                      </div>
                      <button
                        type="button"
                        @click="activateSepaMandate(paymentMethod)"
                        :disabled="activatingMandate === paymentMethod.id"
                        class="bg-blue-600 text-white px-3 py-1 rounded text-sm hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                      >
                        {{ activatingMandate === paymentMethod.id ? 'Wird aktiviert...' : 'Mandat aktivieren' }}
                      </button>
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

            <!-- Payment History Section with PaymentsTable -->
            <div class="space-y-6">
              <div class="flex justify-between items-center">
                <h3 class="text-lg font-semibold text-gray-900">Zahlungshistorie</h3>
                <div class="flex items-center space-x-2">
                  <!-- Filter -->
                  <select
                    v-model="paymentStatusFilter"
                    @change="filterPayments"
                    class="border border-gray-300 rounded-md px-3 py-1 text-sm"
                  >
                    <option value="">Alle Status</option>
                    <option value="paid">Bezahlt</option>
                    <option value="pending">Ausstehend</option>
                    <option value="failed">Fehlgeschlagen</option>
                  </select>

                  <!-- Add Payment Button -->
                  <button
                    @click="openAddPayment"
                    type="button"
                    class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 flex items-center gap-2"
                  >
                    <Plus class="w-4 h-4" />
                    Zahlung hinzufügen
                  </button>

                  <!-- Batch Execute Button -->
                  <button
                    v-if="selectedPaymentIds.length > 0 && hasPendingPaymentsSelected"
                    @click="executeSelectedPayments"
                    type="button"
                    class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
                    :disabled="executingBatch"
                  >
                    <PlayCircle v-if="!executingBatch" class="w-4 h-4" />
                    <div v-else class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
                    {{ executingBatch ? 'Wird ausgeführt...' : `Zahlungen ausführen (${selectedPendingPaymentIds.length})` }}
                  </button>
                </div>
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
                  <!-- Custom Actions Slot für zusätzliche Buttons -->
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
                      <td class="px-4 py-3 text-sm">{{ checkin.check_in_method_text || 'Unbekannt' }}</td>
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

          <!-- Access Control Tab -->
          <div v-show="activeTab === 'access'" class="space-y-6">
            <!-- Primary Access Methods -->
            <div>
              <h3 class="text-lg font-semibold text-gray-900 mb-4">Primäre Zugangsmethoden</h3>

              <!-- QR Code Section -->
              <div class="border border-gray-200 rounded-lg p-6 mb-4">
                <div class="flex items-start justify-between">
                  <div class="flex-1">
                    <div class="flex items-center gap-3 mb-2">
                      <div class="p-2 bg-indigo-100 rounded-lg">
                        <QrCode class="w-6 h-6 text-indigo-600" />
                      </div>
                      <div>
                        <h4 class="font-semibold text-gray-900">QR-Code Zugang</h4>
                        <p class="text-sm text-gray-500">Digitaler Zugang über Mitglieder-App</p>
                      </div>
                    </div>

                    <div v-if="accessForm.qr_code_enabled" class="mt-4 space-y-3">
                      <div class="bg-blue-50 p-3 rounded-lg">
                        <div class="flex items-start gap-2">
                          <Info class="w-5 h-5 text-blue-600 mt-0.5" />
                          <div class="text-sm text-blue-800">
                            <p class="font-medium mb-1">QR-Code ist aktiviert</p>
                            <p>Das Mitglied kann den QR-Code in der Mitglieder-App (PWA) einsehen und für den Check-in verwenden.</p>
                          </div>
                        </div>
                      </div>

                      <div class="flex items-center gap-3">
                        <button
                          @click="invalidateQrCode"
                          class="text-sm text-red-600 hover:text-red-800 flex items-center gap-1"
                        >
                          <XCircle class="w-4 h-4" />
                          QR-Code invalidieren
                        </button>
                        <button
                          @click="sendQrCodeToMember"
                          class="text-sm text-indigo-600 hover:text-indigo-800 flex items-center gap-1"
                        >
                          <Mail class="w-4 h-4" />
                          App-Link per E-Mail senden
                        </button>
                      </div>
                    </div>

                    <div v-else class="mt-4 p-3 bg-gray-50 rounded-lg">
                      <p class="text-sm text-gray-600">QR-Code-Zugang ist deaktiviert</p>
                    </div>
                  </div>

                  <div class="ml-4">
                    <label class="relative inline-flex items-center cursor-pointer">
                      <input
                        v-model="accessForm.qr_code_enabled"
                        type="checkbox"
                        class="sr-only peer"
                        @change="updateAccessSettings"
                      >
                      <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                    </label>
                  </div>
                </div>
              </div>

              <!-- NFC Tag Section -->
              <div class="border border-gray-200 rounded-lg p-6">
                <div class="flex items-start justify-between">
                  <div class="flex-1">
                    <div class="flex items-center gap-3 mb-2">
                      <div class="p-2 bg-purple-100 rounded-lg">
                        <Nfc class="w-6 h-6 text-purple-600" />
                      </div>
                      <div>
                        <h4 class="font-semibold text-gray-900">NFC-Tag Zugang</h4>
                        <p class="text-sm text-gray-500">Kontaktloser Zugang via NFC-Chip oder Karte</p>
                      </div>
                    </div>

                    <div v-if="accessForm.nfc_enabled" class="mt-4 space-y-3">
                      <div class="space-y-2">
                        <div class="flex items-center gap-3">
                          <input
                            v-model="nfcInputValue"
                            type="text"
                            placeholder="NFC ID eingeben..."
                            class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            :disabled="!editingNfc"
                            @input="validateNfcInput"
                          />
                          <button
                            v-if="!editingNfc"
                            @click="startNfcEdit"
                            class="px-4 py-2 text-indigo-600 border border-indigo-600 rounded-md hover:bg-indigo-50"
                          >
                            Bearbeiten
                          </button>
                          <template v-else>
                            <button
                              @click="saveNfcUid"
                              :disabled="!isNfcValid"
                              class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:bg-gray-300 disabled:cursor-not-allowed"
                            >
                              Speichern
                            </button>
                            <button
                              @click="cancelNfcEdit"
                              class="px-4 py-2 text-gray-600 border border-gray-300 rounded-md hover:bg-gray-50"
                            >
                              Abbrechen
                            </button>
                          </template>
                        </div>

                        <!-- Formathinweise -->
                        <div v-if="editingNfc" class="text-xs text-gray-500 space-y-1">
                          <p>Akzeptierte Formate:</p>
                          <ul class="ml-4 space-y-0.5">
                            <li>• Hex mit Trennzeichen: <code class="bg-gray-100 px-1 rounded">04:A1:B2:C3</code> oder <code class="bg-gray-100 px-1 rounded">04-A1-B2-C3</code></li>
                            <li>• Hex mit Prefix: <code class="bg-gray-100 px-1 rounded">0x04A1B2C3</code></li>
                            <li>• Reines Hex: <code class="bg-gray-100 px-1 rounded">04A1B2C3</code></li>
                            <li>• Dezimal: <code class="bg-gray-100 px-1 rounded">77856451</code></li>
                          </ul>
                        </div>

                        <!-- Validierungsfeedback -->
                        <div v-if="editingNfc && nfcInputValue && !isNfcValid" class="flex items-center gap-2 text-sm text-red-600">
                          <XCircle class="w-4 h-4" />
                          Ungültiges Format
                        </div>

                        <div v-if="editingNfc && normalizedNfcId && isNfcValid" class="flex items-center gap-2 text-sm text-green-600">
                          <CheckCircle class="w-4 h-4" />
                          Normalisiert: {{ formatNfcIdForDisplay(normalizedNfcId) }}
                        </div>
                      </div>

                      <div v-if="accessForm.nfc_uid && !editingNfc" class="flex items-center justify-between p-3 bg-purple-50 rounded-lg">
                        <div class="flex items-center gap-2">
                          <CheckCircle class="w-4 h-4 text-purple-600" />
                          <div>
                            <p class="text-sm font-medium text-purple-900">NFC-Tag registriert</p>
                            <p class="text-xs text-purple-700 font-mono">{{ formatNfcIdForDisplay(accessForm.nfc_uid) }}</p>
                          </div>
                        </div>
                        <button
                          @click="removeNfcTag"
                          class="text-sm text-red-600 hover:text-red-800"
                        >
                          Entfernen
                        </button>
                      </div>
                    </div>

                    <div v-else class="mt-4 p-3 bg-gray-50 rounded-lg">
                      <p class="text-sm text-gray-600">NFC-Zugang ist deaktiviert</p>
                    </div>
                  </div>

                  <div class="ml-4">
                    <label class="relative inline-flex items-center cursor-pointer">
                      <input
                        v-model="accessForm.nfc_enabled"
                        type="checkbox"
                        class="sr-only peer"
                        @change="updateAccessSettings"
                      >
                      <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-purple-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-purple-600"></div>
                    </label>
                  </div>
                </div>
              </div>
            </div>

            <!-- Additional Services -->
            <div>
              <h3 class="text-lg font-semibold text-gray-900 mb-4">Zusätzliche Services</h3>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Solarium Access -->
                <div class="border border-gray-200 rounded-lg p-4">
                  <div class="flex items-start justify-between">
                    <div class="flex items-center gap-3">
                      <div class="p-2 bg-yellow-100 rounded-lg">
                        <Sun class="w-5 h-5 text-yellow-600" />
                      </div>
                      <div>
                        <h5 class="font-medium text-gray-900">Solarium</h5>
                        <p class="text-sm text-gray-500">Zugang zur Sonnenbank</p>
                        <p v-if="accessForm.solarium_enabled" class="text-xs text-gray-400 mt-1">
                          {{ accessForm.solarium_minutes || 0 }} Minuten verfügbar
                        </p>
                      </div>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                      <input
                        v-model="accessForm.solarium_enabled"
                        type="checkbox"
                        class="sr-only peer"
                        @change="updateAccessSettings"
                      >
                      <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-yellow-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-yellow-500"></div>
                    </label>
                  </div>
                </div>

                <!-- Vending Machine -->
                <div class="border border-gray-200 rounded-lg p-4">
                  <div class="flex items-start justify-between">
                    <div class="flex items-center gap-3">
                      <div class="p-2 bg-green-100 rounded-lg">
                        <Package class="w-5 h-5 text-green-600" />
                      </div>
                      <div>
                        <h5 class="font-medium text-gray-900">Vending Machine</h5>
                        <p class="text-sm text-gray-500">Proteinriegel & Snacks</p>
                        <p v-if="accessForm.vending_enabled" class="text-xs text-gray-400 mt-1">
                          Guthaben: {{ formatCurrency(accessForm.vending_credit || 0) }}
                        </p>
                      </div>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                      <input
                        v-model="accessForm.vending_enabled"
                        type="checkbox"
                        class="sr-only peer"
                        @change="updateAccessSettings"
                      >
                      <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-green-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600"></div>
                    </label>
                  </div>
                </div>

                <!-- Massage Chair -->
                <div class="border border-gray-200 rounded-lg p-4">
                  <div class="flex items-start justify-between">
                    <div class="flex items-center gap-3">
                      <div class="p-2 bg-blue-100 rounded-lg">
                        <Armchair class="w-5 h-5 text-blue-600" />
                      </div>
                      <div>
                        <h5 class="font-medium text-gray-900">Massagestuhl</h5>
                        <p class="text-sm text-gray-500">Wellness & Entspannung</p>
                        <p v-if="accessForm.massage_enabled" class="text-xs text-gray-400 mt-1">
                          {{ accessForm.massage_sessions || 0 }} Sitzungen verfügbar
                        </p>
                      </div>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                      <input
                        v-model="accessForm.massage_enabled"
                        type="checkbox"
                        class="sr-only peer"
                        @change="updateAccessSettings"
                      >
                      <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                    </label>
                  </div>
                </div>

                <!-- Coffee Flat -->
                <div class="border border-gray-200 rounded-lg p-4">
                  <div class="flex items-start justify-between">
                    <div class="flex items-center gap-3">
                      <div class="p-2 bg-orange-100 rounded-lg">
                        <Coffee class="w-5 h-5 text-orange-600" />
                      </div>
                      <div>
                        <h5 class="font-medium text-gray-900">Kaffee-Flatrate</h5>
                        <p class="text-sm text-gray-500">Unbegrenzt Kaffee</p>
                        <p v-if="accessForm.coffee_flat_enabled" class="text-xs text-gray-400 mt-1">
                          Gültig bis: {{ accessForm.coffee_flat_expiry ? formatDate(accessForm.coffee_flat_expiry) : 'Unbegrenzt' }}
                        </p>
                      </div>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                      <input
                        v-model="accessForm.coffee_flat_enabled"
                        type="checkbox"
                        class="sr-only peer"
                        @change="updateAccessSettings"
                      >
                      <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-orange-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-orange-600"></div>
                    </label>
                  </div>
                </div>
              </div>
            </div>

            <!-- Access Log -->
            <div>
              <h3 class="text-lg font-semibold text-gray-900 mb-4">Zugangshistorie</h3>

              <div v-if="accessLogs && accessLogs.length > 0" class="border border-gray-200 rounded-lg overflow-hidden">
                <table class="w-full">
                  <thead class="bg-gray-50">
                    <tr>
                      <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Zeitpunkt</th>
                      <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Service</th>
                      <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Methode</th>
                      <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-200">
                    <tr v-for="log in accessLogs" :key="log.id" class="hover:bg-gray-50">
                      <td class="px-4 py-3 text-sm">{{ formatDateTime(log.accessed_at) }}</td>
                      <td class="px-4 py-3 text-sm">{{ log.service_name }}</td>
                      <td class="px-4 py-3 text-sm">
                        <span class="inline-flex items-center gap-1">
                          <component :is="getAccessMethodIcon(log.method)" class="w-4 h-4" />
                          {{ log.method }}
                        </span>
                      </td>
                      <td class="px-4 py-3 text-sm">
                        <span :class="log.success ? 'text-green-600' : 'text-red-600'">
                          {{ log.success ? 'Erfolgreich' : 'Verweigert' }}
                        </span>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <div v-else class="text-center py-8 bg-gray-50 rounded-lg">
                <Key class="w-12 h-12 text-gray-400 mx-auto mb-4" />
                <p class="text-gray-500">Noch keine Zugänge protokolliert</p>
              </div>
            </div>
          </div>

          <!-- Status History Tab -->
          <div v-show="activeTab === 'history'" class="space-y-4">
            <StatusHistory :member="member" />
          </div>

        </div>
      </div>
    </div>

    <!-- Modal für Mitgliedschaft pausieren -->
    <teleport to="body">
      <div v-if="showPauseMembershipModal" class="fixed inset-0 bg-gray-500/75 overflow-y-auto h-full w-full z-50" @click="closePauseMembership">
        <div class="relative top-20 mx-auto p-5 border border-gray-50 w-11/12 md:w-3/4 lg:w-1/3 shadow-lg rounded-md bg-white" @click.stop>
          <form @submit.prevent="pauseMembership">
            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
              <div class="mb-4">
                <h3 class="text-lg font-medium text-gray-900">
                  Mitgliedschaft stilllegen
                </h3>
                <p class="mt-2 text-sm text-gray-600">
                  Die Mitgliedschaft wird für den angegebenen Zeitraum pausiert.
                  Der Vertrag verlängert sich entsprechend.
                </p>
              </div>

              <div class="space-y-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">
                    Pausierung beginnt am <span class="text-red-500">*</span>
                  </label>
                  <input
                    v-model="pauseMembershipForm.pause_start_date"
                    type="date"
                    :min="new Date().toISOString().split('T')[0]"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    required
                  />
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">
                    Pausierung endet am <span class="text-red-500">*</span>
                  </label>
                  <input
                    v-model="pauseMembershipForm.pause_end_date"
                    type="date"
                    :min="pauseMembershipForm.pause_start_date"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    required
                  />
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">
                    Grund (optional)
                  </label>
                  <textarea
                    v-model="pauseMembershipForm.reason"
                    rows="3"
                    placeholder="z.B. Urlaub, Verletzung, etc."
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
                  ></textarea>
                </div>
              </div>

              <div v-if="pauseMembershipForm.errors && Object.keys(pauseMembershipForm.errors).length > 0" class="mt-4 p-3 bg-red-50 rounded-md">
                <div class="text-sm text-red-800">
                  <ul class="list-disc list-inside">
                    <li v-for="(error, field) in pauseMembershipForm.errors" :key="field">{{ error }}</li>
                  </ul>
                </div>
              </div>
            </div>

            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
              <button
                type="submit"
                :disabled="pauseMembershipForm.processing"
                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-yellow-600 text-base font-medium text-white hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50"
              >
                {{ pauseMembershipForm.processing ? 'Wird pausiert...' : 'Mitgliedschaft pausieren' }}
              </button>
              <button
                type="button"
                @click="closePauseMembership"
                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
              >
                Abbrechen
              </button>
            </div>
          </form>
        </div>
      </div>
    </teleport>

    <!-- Modal für Mitgliedschaft kündigen -->
    <teleport to="body">
      <div v-if="showCancelMembershipModal" class="fixed inset-0 bg-gray-500/75 overflow-y-auto h-full w-full z-50" @click="closeCancelMembership">
        <div class="relative top-20 mx-auto p-5 border border-gray-50 w-11/12 md:w-3/4 lg:w-1/3 shadow-lg rounded-md bg-white" @click.stop>
          <form @submit.prevent="cancelMembership">
            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
              <div class="mb-4">
                <h3 class="text-lg font-medium text-gray-900">
                  Mitgliedschaft kündigen
                </h3>
                <p class="mt-2 text-sm text-gray-600">
                  Die Mitgliedschaft wird zum angegebenen Datum beendet.
                </p>
              </div>

              <div class="space-y-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">
                    Kündigungsdatum <span class="text-red-500">*</span>
                  </label>
                  <input
                    v-model="cancelMembershipForm.cancellation_date"
                    type="date"
                    :min="cancelMembershipForm.min_cancellation_date || new Date().toISOString().split('T')[0]"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    required
                  />
                  <p class="mt-1 text-sm text-gray-500">
                    Die Mitgliedschaft endet zu diesem Datum.
                  </p>
                  <p v-if="selectedMembership?.membership_plan?.commitment_months" class="mt-1 text-sm text-yellow-600">
                    <AlertCircle class="w-3 h-3 inline mr-1" />
                    Mindestlaufzeit: {{ selectedMembership.membership_plan.commitment_months }} Monate ab {{ formatDate(selectedMembership.start_date) }}
                  </p>
                  <p v-if="selectedMembership?.membership_plan?.cancellation_period_days" class="mt-1 text-sm text-blue-600">
                    <Clock class="w-3 h-3 inline mr-1" />
                    Kündigungsfrist: {{ selectedMembership.membership_plan.cancellation_period_days }} Tage
                  </p>
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">
                    Kündigungsgrund <span class="text-red-500">*</span>
                  </label>
                  <select
                    v-model="cancelMembershipForm.cancellation_reason"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    required
                  >
                    <option value="">Bitte wählen...</option>
                    <option value="move">Umzug</option>
                    <option value="financial">Finanzielle Gründe</option>
                    <option value="health">Gesundheitliche Gründe</option>
                    <option value="dissatisfied">Unzufriedenheit</option>
                    <option value="no_time">Zeitmangel</option>
                    <option value="other">Sonstiges</option>
                  </select>
                </div>

                <div>
                  <label class="flex items-center">
                    <input
                      v-model="cancelMembershipForm.immediate"
                      type="checkbox"
                      class="rounded border-gray-300 text-red-600 focus:ring-red-500"
                    />
                    <span class="ml-2 text-sm text-gray-700">
                      Sofort kündigen (außerordentliche Kündigung)
                      <span v-if="selectedMembership?.membership_plan?.commitment_months" class="text-gray-500">
                        - umgeht die Mindestlaufzeit
                      </span>
                    </span>
                  </label>
                </div>
              </div>

              <div class="mt-4 p-3 bg-yellow-50 rounded-md">
                <div class="flex items-start">
                  <AlertCircle class="w-5 h-5 text-yellow-600 mr-2 mt-0.5" />
                  <div class="text-sm text-yellow-800">
                    <p class="font-medium">Wichtiger Hinweis:</p>
                    <p class="mt-1">
                      Diese Aktion kann rückgängig gemacht werden, solange das Kündigungsdatum noch nicht erreicht wurde.
                    </p>
                  </div>
                </div>
              </div>

              <div v-if="cancelMembershipForm.errors && Object.keys(cancelMembershipForm.errors).length > 0" class="mt-4 p-3 bg-red-50 rounded-md">
                <div class="text-sm text-red-800">
                  <ul class="list-disc list-inside">
                    <li v-for="(error, field) in cancelMembershipForm.errors" :key="field">{{ error }}</li>
                  </ul>
                </div>
              </div>
            </div>

            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
              <button
                type="submit"
                :disabled="cancelMembershipForm.processing"
                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50"
              >
                {{ cancelMembershipForm.processing ? 'Wird gekündigt...' : 'Mitgliedschaft kündigen' }}
              </button>
              <button
                type="button"
                @click="closeCancelMembership"
                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
              >
                Abbrechen
              </button>
            </div>
          </form>
        </div>
      </div>
    </teleport>

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
                :disabled="paymentMethodForm.processing ||
                          (isSepaType(paymentMethodForm.type) && !ibanValidation.editPaymentMethod.isValid)"
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
      <div v-if="showAddPaymentModal" class="fixed inset-0 bg-gray-500/75 overflow-y-auto h-full w-full z-50" @click="closeAddPayment">
        <div class="relative top-20 mx-auto p-5 border border-gray-50 w-11/12 md:w-3/4 lg:w-1/3 shadow-lg rounded-md bg-white" @click.stop>
          <form @submit.prevent="createPayment">
            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
              <div class="mb-4">
                <h3 class="text-lg font-medium text-gray-900">
                  Neue Zahlung hinzufügen
                </h3>
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
                    <option
                      v-for="method in availablePaymentMethodTypes"
                      :key="method.key"
                      :value="method.key"
                    >
                      {{ method.name }}
                    </option>
                  </select>
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
                {{ newPaymentForm.processing ? 'Hinzufügen...' : 'Hinzufügen' }}
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
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useForm, Link, router, usePage } from '@inertiajs/vue3'
import { useInertiaPayments } from '@/composables/useInertiaPayments'
import AppLayout from '@/Layouts/AppLayout.vue'
import MemberStatusBadge from '@/Components/MemberStatusBadge.vue'
import MemberStatusEditor from '@/Components/MemberStatusEditor.vue'
import StatusHistory from '@/Components/StatusHistory.vue'
import PaymentsTable from '@/Components/PaymentsTable.vue'
import IbanInput from '@/Components/IbanInput.vue'
import {
  User, FileText, Clock, CreditCard, Plus, Edit,
  UserX, ArrowLeft, Wallet, AlertCircle, CheckCircle,
  Download, Building2, Banknote, PlayCircle, WalletCards,
  XCircle, RotateCcw, History, Key, QrCode, Nfc,
  Sun, Package, Armchair, Coffee, Info, Mail
} from 'lucide-vue-next'

const page = usePage()

const props = defineProps({
  member: Object,
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

const editMode = ref(false)
const activeTab = ref('personal')

// Access Control state
const editingNfc = ref(false)
const nfcInputValue = ref('')
const normalizedNfcId = ref('')
const isNfcValid = ref(false)
const accessLogs = ref([])

// Access form for managing permissions
const accessForm = useForm({
  // Primary access methods
  qr_code_enabled: props.member.access_config?.qr_code_enabled ?? props.member?.gym?.pwa_enabled,
  nfc_enabled: props.member.access_config?.nfc_enabled,
  nfc_uid: props.member.access_config?.nfc_uid || '',

  // Additional services
  solarium_enabled: props.member.access_config?.solarium_enabled || false,
  solarium_minutes: props.member.access_config?.solarium_minutes || 0,

  vending_enabled: props.member.access_config?.vending_enabled || false,
  vending_credit: props.member.access_config?.vending_credit || 0,

  massage_enabled: props.member.access_config?.massage_enabled || false,
  massage_sessions: props.member.access_config?.massage_sessions || 0,

  coffee_flat_enabled: props.member.access_config?.coffee_flat_enabled || false,
  coffee_flat_expiry: props.member.access_config?.coffee_flat_expiry || null,
})

// Membership-related state
const pausingMembership = ref(null)
const resumingMembership = ref(null)
const cancellingMembership = ref(null)
const revokingCancellation = ref(null)
const activatingMembership = ref(null)
const showPauseMembershipModal = ref(false)
const showCancelMembershipModal = ref(false)
const selectedMembership = ref(null)

// PaymentMethod-related state
const showEditPaymentMethodModal = ref(false)
const showAddPaymentMethodModal = ref(false)
const showAddPaymentModal = ref(false)
const settingDefault = ref(null)
const deactivating = ref(null)
const markingAsSigned = ref(null)
const sendingMandate = ref(null)
const activatingMandate = ref(null)

const tabs = [
  { id: 'personal', name: 'Persönliche Daten', icon: User },
  { id: 'membership', name: 'Mitgliedschaften', icon: FileText },
  { id: 'payments', name: 'Zahlungen', icon: CreditCard },
  { id: 'checkins', name: 'Check-Ins', icon: Clock },
  { id: 'access', name: 'Zugangsverwaltung', icon: Key },
  { id: 'history', name: 'Status-Verlauf', icon: History },
]

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

const formatDateForInput = (dateString) => {
  return dateString ? dateString.split('T')[0] : '';
};

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

const currentMonth = computed(() => {
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    return `${year}-${month}`;
})

const filteredPayments = computed(() => {
  let paymentList = payments.value || []

  if (paymentStatusFilter.value) {
    paymentList = paymentList.filter(p => p.status === paymentStatusFilter.value)
  }

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

// Access Control functions
const getActiveAccessCount = () => {
  let count = 0
  if (accessForm.qr_code_enabled) count++
  if (accessForm.nfc_enabled) count++
  if (accessForm.solarium_enabled) count++
  if (accessForm.vending_enabled) count++
  if (accessForm.massage_enabled) count++
  if (accessForm.coffee_flat_enabled) count++
  return count
}

// NFC ID Normalisierung
const normalizeCardId = (cardId) => {
  if (!cardId) return null

  // Whitespace entfernen und in Großbuchstaben
  cardId = cardId.trim().toUpperCase()

  // 1. UID-Format mit Trennzeichen (04:A1:B2:C3 oder 04-A1-B2-C3)
  if (cardId.includes(':') || cardId.includes('-')) {
    // Trennzeichen entfernen
    const normalized = cardId.replace(/[:-]/g, '')
    // Prüfen ob gültiges Hex
    if (/^[0-9A-F]+$/.test(normalized)) {
      return normalized
    }
  }

  // 2. Hexadezimal mit 0x Prefix
  else if (cardId.startsWith('0X')) {
    const hexPart = cardId.substring(2)
    if (/^[0-9A-F]+$/.test(hexPart)) {
      return hexPart
    }
  }

  // 3. Reines Hexadezimal (nur A-F, 0-9)
  else if (/^[0-9A-F]+$/.test(cardId)) {
    return cardId
  }

  // 4. Reine Dezimalzahl
  else if (/^[0-9]+$/.test(cardId)) {
    // Dezimal zu Hex konvertieren für einheitliche Speicherung
    return parseInt(cardId, 10).toString(16).toUpperCase()
  }

  return null
}

// Format NFC ID für Anzeige (mit Doppelpunkten für bessere Lesbarkeit)
const formatNfcIdForDisplay = (nfcId) => {
  if (!nfcId) return ''
  // Normalisierte ID in 2er-Gruppen mit Doppelpunkt trennen
  return nfcId.match(/.{1,2}/g)?.join(':') || nfcId
}

const invalidateQrCode = () => {
  if (confirm('Möchten Sie wirklich den QR-Code invalidieren? Das Mitglied kann sich dann nicht mehr per QR-Code einloggen, bis ein neuer Code generiert wird.')) {
    router.post(route('members.access.invalidate-qr', props.member.id), {}, {
      preserveScroll: true,
      onSuccess: () => {
        // Status wird automatisch aktualisiert
      }
    })
  }
}

const sendQrCodeToMember = () => {
  if (confirm(`Möchten Sie dem Mitglied einen Link zur Mitglieder-App per E-Mail an ${props.member.email} senden?`)) {
    router.post(route('members.access.send-app-link', props.member.id), {}, {
      preserveScroll: true,
      onSuccess: () => {
        alert('E-Mail wurde erfolgreich versendet.')
      }
    })
  }
}

const validateNfcInput = () => {
  const normalized = normalizeCardId(nfcInputValue.value)
  normalizedNfcId.value = normalized || ''
  isNfcValid.value = normalized !== null
}

const startNfcEdit = () => {
  editingNfc.value = true
  nfcInputValue.value = formatNfcIdForDisplay(accessForm.nfc_uid) || ''
  validateNfcInput()
}

const saveNfcUid = () => {
  if (!isNfcValid.value) return

  // Speichere die normalisierte Version
  accessForm.nfc_uid = normalizedNfcId.value

  accessForm.put(route('members.access.update', props.member.id), {
    preserveScroll: true,
    onSuccess: () => {
      editingNfc.value = false
      nfcInputValue.value = ''
      normalizedNfcId.value = ''
    },
    onError: (errors) => {
      console.error('Fehler beim Speichern der NFC-ID:', errors)
      alert('Die NFC-ID konnte nicht gespeichert werden. Möglicherweise ist diese ID bereits einem anderen Mitglied zugeordnet.')
    }
  })
}

const cancelNfcEdit = () => {
  nfcInputValue.value = formatNfcIdForDisplay(accessForm.nfc_uid) || ''
  normalizedNfcId.value = ''
  isNfcValid.value = false
  editingNfc.value = false
}

const removeNfcTag = () => {
  if (confirm('Möchten Sie den NFC-Tag wirklich entfernen?')) {
    accessForm.nfc_uid = ''
    nfcInputValue.value = ''
    normalizedNfcId.value = ''

    accessForm.put(route('members.access.update', props.member.id), {
      preserveScroll: true,
      onSuccess: () => {
        // Erfolgreich entfernt
      }
    })
  }
}

const updateAccessSettings = () => {
  accessForm.put(route('members.access.update', props.member.id), {
    preserveScroll: true
  })
}

const getAccessMethodIcon = (method) => {
  const icons = {
    'QR-Code': QrCode,
    'NFC': Nfc,
    'Manual': Key
  }
  return icons[method] || Key
}

const formatDateTime = (datetime) => {
  if (!datetime) return '-'
  return new Date(datetime).toLocaleString('de-DE', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

// Helper functions
const isSepaType = (type) => {
  return type === 'sepa_direct_debit' ||
         type === 'sepa' ||
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

// Forms
const form = useForm({
  member_number: props.member.member_number,
  salutation: props.member.salutation,
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

// Forms für Mitgliedschafts-Aktionen
const pauseMembershipForm = useForm({
  membership_id: null,
  pause_start_date: '',
  pause_end_date: '',
  reason: ''
})

const cancelMembershipForm = useForm({
  membership_id: null,
  cancellation_date: '',
  cancellation_reason: '',
  immediate: false,
  min_cancellation_date: null
})

// Forms für Zahlungsmethoden
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

const newPaymentForm = useForm({
  amount: '',
  description: '',
  due_date: '',
  paid_date: '',
  payment_method: '',
  status: 'pending',
  notes: ''
})

// IBAN validation state für beide Forms
const ibanValidation = ref({
  newPaymentMethod: { isValid: false },
  editPaymentMethod: { isValid: false }
})

// IBAN validation handlers
const handleIbanValidation = (validation, context) => {
  ibanValidation.value[context] = validation
  console.log(`IBAN validation for ${context}:`, validation)
}

// Status-Change Handler
const handleStatusChanged = (newStatus) => {
  console.log('Status wurde geändert zu:', newStatus)
  // Die Seite wird automatisch durch Inertia aktualisiert
}

const handleStatusChanging = (newStatus) => {
  console.log('Status wird geändert zu:', newStatus)
  // Optional: Zeige Loading-Indicator
}

// Mitgliedschafts-Aktionen
const activateMembership = (membership) => {
  if (!confirm('Möchten Sie diese Mitgliedschaft aktivieren?')) {
    return
  }

  activatingMembership.value = membership.id

  router.put(route('members.memberships.activate', {
    member: props.member.id,
    membership: membership.id
  }), {}, {
    preserveScroll: true,
    onSuccess: () => {
      activatingMembership.value = null
    },
    onError: () => {
      activatingMembership.value = null
      alert('Die Mitgliedschaft konnte nicht aktiviert werden.')
    }
  })
}

const openPauseMembership = (membership) => {
  selectedMembership.value = membership
  pauseMembershipForm.membership_id = membership.id
  pauseMembershipForm.pause_start_date = new Date().toISOString().split('T')[0]

  // Standardmäßig für 1 Monat pausieren
  const endDate = new Date()
  endDate.setMonth(endDate.getMonth() + 1)
  pauseMembershipForm.pause_end_date = endDate.toISOString().split('T')[0]

  showPauseMembershipModal.value = true
}

const closePauseMembership = () => {
  showPauseMembershipModal.value = false
  pauseMembershipForm.reset()
  selectedMembership.value = null
}

const pauseMembership = () => {
  pauseMembershipForm.put(route('members.memberships.pause', {
    member: props.member.id,
    membership: pauseMembershipForm.membership_id
  }), {
    preserveScroll: true,
    onSuccess: () => {
      closePauseMembership()
      pausingMembership.value = null
    },
    onError: () => {
      pausingMembership.value = null
    }
  })
}

const resumeMembership = (membership) => {
  if (!confirm('Möchten Sie diese Mitgliedschaft wirklich wieder aufnehmen?')) {
    return
  }

  resumingMembership.value = membership.id

  router.put(route('members.memberships.resume', {
    member: props.member.id,
    membership: membership.id
  }), {}, {
    preserveScroll: true,
    onSuccess: () => {
      resumingMembership.value = null
    },
    onError: () => {
      resumingMembership.value = null
      alert('Die Mitgliedschaft konnte nicht wieder aufgenommen werden.')
    }
  })
}

const openCancelMembership = (membership) => {
  selectedMembership.value = membership
  cancelMembershipForm.membership_id = membership.id

  // Mindestlaufzeit und Kündigungsfrist berücksichtigen
  let minCancellationDate = new Date()

  // Kündigungsfrist berücksichtigen
  if (membership.membership_plan.cancellation_period_days) {
    minCancellationDate.setDate(minCancellationDate.getDate() + membership.membership_plan.cancellation_period_days)
  }

  // Mindestlaufzeit berücksichtigen
  if (membership.membership_plan.commitment_months) {
    const startDate = new Date(membership.start_date)
    const minEndDate = new Date(startDate)
    minEndDate.setMonth(minEndDate.getMonth() + membership.membership_plan.commitment_months)

    if (minEndDate > minCancellationDate) {
      minCancellationDate = minEndDate
    }
  }

  // Standardmäßig zum Ende der Laufzeit oder Mindestlaufzeit kündigen
  if (membership.end_date) {
    const endDate = new Date(membership.end_date)
    if (endDate > minCancellationDate) {
      cancelMembershipForm.cancellation_date = formatDateForInput(membership.end_date)
    } else {
      cancelMembershipForm.cancellation_date = minCancellationDate.toISOString().split('T')[0]
    }
  } else {
    cancelMembershipForm.cancellation_date = minCancellationDate.toISOString().split('T')[0]
  }

  // Store minimum date for validation
  cancelMembershipForm.min_cancellation_date = minCancellationDate.toISOString().split('T')[0]

  showCancelMembershipModal.value = true
}

const closeCancelMembership = () => {
  showCancelMembershipModal.value = false
  cancelMembershipForm.reset()
  cancelMembershipForm.min_cancellation_date = null
  selectedMembership.value = null
}

const cancelMembership = () => {
  cancelMembershipForm.put(route('members.memberships.cancel', {
    member: props.member.id,
    membership: cancelMembershipForm.membership_id
  }), {
    preserveScroll: true,
    onSuccess: () => {
      closeCancelMembership()
      cancellingMembership.value = null
    },
    onError: () => {
      cancellingMembership.value = null
    }
  })
}

const revokeCancellation = (membership) => {
  if (!confirm('Möchten Sie die Kündigung wirklich zurücknehmen?')) {
    return
  }

  revokingCancellation.value = membership.id

  router.put(route('members.memberships.revoke-cancellation', {
    member: props.member.id,
    membership: membership.id
  }), {}, {
    preserveScroll: true,
    onSuccess: () => {
      revokingCancellation.value = null
    },
    onError: () => {
      revokingCancellation.value = null
      alert('Die Kündigung konnte nicht zurückgenommen werden.')
    }
  })
}

// Payment methods
const filterPayments = () => {
  // Filter wird automatisch durch computed property angewendet
  console.log(`Showing ${filteredPayments.value.total} payments`)
}

const openAddPayment = () => {
  newPaymentForm.reset()
  newPaymentForm.due_date = new Date().toISOString().split('T')[0]
  showAddPaymentModal.value = true
}

const closeAddPayment = () => {
  showAddPaymentModal.value = false
  newPaymentForm.reset()
}

const createPayment = () => {
  newPaymentForm.post(route('members.payments.store', props.member.id), {
    preserveScroll: true,
    onSuccess: () => {
      closeAddPayment()
      // Reload member data and re-apply filters
      router.reload({
        only: ['member'],
        preserveScroll: true,
        onSuccess: () => {
          filterPayments()
        }
      })
    }
  })
}

const handleExecutePayment = (payment) => {
  executePayment(payment)
    .then((result) => {
      if (result && result.success) {
        console.log('Payment executed successfully:', result.message)
      }
    })
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
        console.log(`${result.count} payments executed successfully`)
      }
    })
    .catch((error) => {
      console.error('Batch execution failed:', error)
    })
}

const downloadInvoice = (payment) => {
  // Placeholder für Rechnung-Download
  window.open(route('members.payments.invoice', {
    member: props.member.id,
    payment: payment.id
  }), '_blank')
}

const handlePaymentMarkedPaid = (payment) => {
  // Update the payment status in the local state
  const paymentList = payments.value
  const paymentIndex = paymentList.findIndex(p => p.id === payment.id)

  if (paymentIndex !== -1) {
    // Create new array with updated payment
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

const handleBeforeMarkPaid = (event) => {
  // Here we can add additional validation if needed
  // event.preventDefault = true would prevent the action

  // Optional: Show loading state or confirm dialog
  // if (!confirm('Möchten Sie diese Zahlung als bezahlt markieren?')) {
  //   event.preventDefault = true
  // }
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
    onSuccess: (page) => {
      markingAsSigned.value = null
      if (page.props.flash?.success) {
        console.log('Erfolg:', page.props.flash.success)
      }
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
    onSuccess: (page) => {
      activatingMandate.value = null
      if (page.props.flash?.success) {
        console.log('Erfolg:', page.props.flash.success)
      }
    },
    onError: (errors) => {
      activatingMandate.value = null
      console.error('Fehler:', errors)
      alert('Das SEPA-Mandat konnte nicht aktiviert werden.')
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
const getInitials = (firstName, lastName) => {
  return `${firstName?.charAt(0) || ''}${lastName?.charAt(0) || ''}`.toUpperCase()
}

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

const getBillingCycleText = (cycle) => {
  const cycles = {
    'monthly': 'Monat',
    'quarterly': 'Quartal',
    'yearly': 'Jahr'
  }
  return cycles[cycle] || cycle
}

const getPaymentMethodIcon = (type) => {
  const icons = {
    'sepa_direct_debit': Building2,
    'sepa': Building2,
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

watch(paymentStatusFilter, () => {
  filterPayments()
})

// Lifecycle
onMounted(() => {
  const urlParams = new URLSearchParams(window.location.search)
  if (urlParams.get('edit') === 'true') {
    editMode.value = true
  }

  // Initial payments laden
  if (props.member?.payments) {
    updateLocalPayments(props.member.payments)
  }

  // Load access logs if available
  if (props.member?.access_logs) {
    accessLogs.value = props.member.access_logs
  }

  // Initialize NFC value with proper formatting
  if (props.member?.access_config?.nfc_uid) {
    accessForm.nfc_uid = props.member.access_config.nfc_uid
    nfcInputValue.value = formatNfcIdForDisplay(props.member.access_config.nfc_uid)
  }

  // Initial filter anwenden
  filterPayments()
})
</script>
