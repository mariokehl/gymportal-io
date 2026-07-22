<template>
  <AppLayout :title="`${member.first_name} ${member.last_name}`" :hide-header-user-on-mobile="true">
    <template #header>
      <div class="flex items-center min-w-0 flex-1">
        <Link
          :href="route('members.index')"
          class="text-gray-500 hover:text-gray-700 mr-4 flex-shrink-0"
        >
          <ArrowLeft class="w-5 h-5" />
        </Link>

        <!-- On mobile the plain title swaps for a compact member identity once
             the profile card scrolls under the app bar. Desktop always shows the
             title. Both variants live in normal flow (toggled, not overlaid) so
             they render reliably inside the layout's <h1>. -->
        <div class="min-w-0 flex-1">
          <!-- Title (always on desktop; on mobile only until collapsed) -->
          <span
            class="block truncate"
            :class="showMobileIdentity ? 'hidden lg:block' : 'block'"
          >
            <template v-if="editMode">
              Mitglied bearbeiten
            </template>
            <template v-else>
              Mitglied anzeigen: {{ member.first_name }} {{ member.last_name }}
            </template>
          </span>

          <!-- Collapsed member identity (mobile only, view mode only) -->
          <div
            v-if="showMobileIdentity"
            class="lg:hidden flex items-center gap-2.5 min-w-0"
          >
            <MemberAvatar
              :initials="headerInitials"
              :is-guest="member.guest_access"
              size="sm"
            />
            <div class="min-w-0">
              <div class="text-sm font-bold text-gray-900 leading-tight truncate">
                {{ member.salutation ? member.salutation + ' ' : '' }}{{ member.first_name }} {{ member.last_name }}
              </div>
              <div class="flex items-center gap-1.5 mt-0.5 min-w-0">
                <span class="w-1.5 h-1.5 rounded-full flex-none" :class="headerStatusDotClass" />
                <span class="text-[11.5px] font-normal text-gray-500 truncate">
                  {{ headerStatusText }} · #{{ member.member_number }}
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- Mobile header actions (desktop keeps its actions in the profile card).
         View mode: the "⋯" overflow sheet. Edit mode: Abbrechen / Speichern. -->
    <template #header-actions>
      <div class="lg:hidden flex items-center">
        <template v-if="editMode">
          <button
            type="button"
            @click="headerCard?.requestCancelEdit()"
            class="px-2 py-2 text-sm font-medium text-gray-600"
          >
            Abbrechen
          </button>
          <button
            type="button"
            @click="savePersonalData"
            :disabled="!isDirty || isSaving"
            class="px-2 py-2 text-sm font-bold"
            :class="(!isDirty || isSaving) ? 'text-gray-400' : 'text-indigo-600'"
          >
            {{ isSaving ? 'Speichern...' : 'Speichern' }}
          </button>
        </template>
        <button
          v-else
          type="button"
          @click="headerCard?.openActionSheet()"
          class="-mr-1 w-11 h-11 rounded-xl flex items-center justify-center text-gray-600 hover:bg-gray-100 transition-colors"
          aria-label="Aktionen"
        >
          <MoreVertical class="w-6 h-6" />
        </button>
      </div>
    </template>

    <!-- Extra bottom space on mobile while editing so the sticky action bar
         does not cover the last form field -->
    <div ref="pageRoot" class="space-y-6" :class="editMode ? 'pb-24 lg:pb-0' : ''">
      <!-- Header card / member identity + actions -->
      <MemberHeaderCard
        ref="headerCard"
        :member="member"
        :edit-mode="editMode"
        :member-age="memberAge"
        v-model:member-number="memberNumber"
        :is-dirty="isDirty"
        :is-saving="isSaving"
        :verifying-age="verifyingAge"
        :toggling-guest-access="togglingGuestAccess"
        @edit="enterEditMode"
        @cancel="exitEditMode"
        @save="savePersonalData"
        @block="showBlockModal = true"
        @toggle-age="toggleAgeVerification"
        @toggle-guest="toggleGuestAccess"
        @topup="handleTopup"
        @status-changed="handleStatusChanged"
        @status-changing="handleStatusChanging"
      />

      <!-- Fraud-Warnbanner -->
      <div v-if="fraudCheck" class="bg-amber-50 border border-amber-300 rounded-lg shadow p-4">
        <div class="flex items-start gap-3">
          <AlertCircle class="w-5 h-5 text-amber-600 mt-0.5 flex-shrink-0" />
          <div class="flex-1">
            <h3 class="text-sm font-semibold text-amber-800">Verdächtige Registrierung erkannt</h3>
            <p class="mt-1 text-sm text-amber-700">
              Fraud-Score: <span class="font-semibold">{{ fraudCheck.fraud_score }}/100</span>
              — Übereinstimmungen:
              <span class="font-medium">
                {{ Object.keys(fraudCheck.matched_fields || {}).filter(k => k !== '_combination_bonus').join(', ') }}
              </span>
            </p>
            <p v-if="fraudCheck.blocklist_entry" class="mt-1 text-sm text-amber-700">
              Sperrgrund: {{ fraudCheck.blocklist_entry.reason === 'chargeback' ? 'Rücklastschrift' : (fraudCheck.blocklist_entry.reason === 'payment_failed' ? 'Zahlungsausfall' : fraudCheck.blocklist_entry.reason) }}
              <template v-if="fraudCheck.blocklist_entry.notes"> — {{ fraudCheck.blocklist_entry.notes }}</template>
            </p>
            <p class="mt-2 text-xs text-amber-600">
              Dieses Mitglied muss manuell aktiviert werden. Geprüft am {{ new Date(fraudCheck.checked_at).toLocaleDateString('de-DE') }}.
            </p>
          </div>
        </div>
      </div>

      <!-- Tabs navigation (pill rail, consistent across all breakpoints; hidden while editing).
           On mobile the rail sticks below the app header once the page is scrolled. -->
      <nav
        v-if="!editMode"
        class="flex gap-2 overflow-x-auto gp-tab-rail -mx-4 px-4 py-2 sm:mx-0 sm:px-0 sm:py-1 sticky z-20 bg-gray-100 border-b border-gray-200 lg:static lg:z-auto lg:bg-transparent lg:border-b-0"
        :style="{ top: 'var(--gp-header-height, 56px)' }"
      >
        <button
          v-for="tab in tabs"
          :key="tab.id"
          @click="activeTab = tab.id"
          :class="[
            activeTab === tab.id
              ? 'bg-indigo-600 text-white border-transparent shadow-[0_2px_8px_rgba(79,70,229,0.3)]'
              : 'bg-white text-gray-600 border-gray-200 shadow-sm hover:border-gray-300',
            'flex-none whitespace-nowrap flex items-center gap-1.5 px-4 py-2.5 rounded-full border text-sm font-medium transition-colors'
          ]"
        >
          <component :is="tab.icon" class="w-4 h-4" />
          {{ tab.name }}
          <span
            v-if="tab.id === 'history' && member.status_history?.length > 0"
            class="ml-0.5 text-xs font-bold px-1.5 py-0.5 rounded-full min-w-[18px] text-center"
            :class="activeTab === tab.id ? 'bg-white/25 text-white' : 'bg-indigo-100 text-indigo-700'"
          >{{ member.status_history.length }}</span>
          <span
            v-if="tab.id === 'payments' && outstandingBalance"
            class="ml-0.5 inline-flex items-center justify-center w-5 h-5 rounded-full"
            :class="activeTab === tab.id ? 'bg-white/25 text-white' : 'bg-amber-100 text-amber-600'"
          ><AlertTriangle class="w-3 h-3" /></span>
          <span
            v-if="tab.id === 'documents' && documentCount > 0"
            class="ml-0.5 text-xs font-bold px-1.5 py-0.5 rounded-full min-w-[18px] text-center"
            :class="activeTab === tab.id ? 'bg-white/25 text-white' : 'bg-indigo-100 text-indigo-700'"
          >{{ documentCount }}</span>
          <span
            v-if="tab.id === 'access' && activeAccessCount > 0"
            class="ml-0.5 text-xs font-bold px-1.5 py-0.5 rounded-full min-w-[18px] text-center"
            :class="activeTab === tab.id ? 'bg-white/25 text-white' : 'bg-green-100 text-green-600'"
          >{{ activeAccessCount }}</span>
        </button>
      </nav>

      <!-- Personal Data Tab (own component; sole panel while editing) -->
      <div v-show="editMode || activeTab === 'personal'">
        <MemberPersonalDataTab
          ref="personalDataTab"
          :member="member"
          :edit-mode="editMode"
          @request-cancel="headerCard?.requestCancelEdit()"
          @saved="onPersonalDataSaved"
        />
      </div>

      <!-- Membership Tab (rendered directly on the gray canvas; only the cards are white) -->
      <div v-if="!editMode" v-show="activeTab === 'membership'">
        <MembershipTab
          :member="member"
          :membership-plans="membershipPlans"
          :pausing-membership="pausingMembership"
          :resuming-membership="resumingMembership"
          :cancelling-membership="cancellingMembership"
          :revoking-cancellation="revokingCancellation"
          :activating-membership="activatingMembership"
          :aborting-membership="abortingMembership"
          :withdrawing-membership="withdrawingMembership"
          @activate="activateMembership"
          @pause="openPauseMembership"
          @resume="resumeMembership"
          @cancel="openCancelMembership"
          @revoke-cancellation="revokeCancellation"
          @abort="abortMembership"
          @withdraw="openWithdrawMembership"
          @force-status="handleForceStatus"
        />

        <!-- Booked add-ons (addon_membership) -->
        <div class="mt-6">
          <MemberAddons :member="member" :bookable-addons="bookableAddons" />
        </div>
      </div>

      <!-- Payments Tab (rendered directly on the gray canvas; only the cards are white) -->
      <div v-if="!editMode" v-show="activeTab === 'payments'">
        <PaymentsTab
          ref="paymentsTab"
          :member="member"
          :available-payment-methods="availablePaymentMethods"
        />
      </div>

      <!-- Documents Tab (rendered directly on the gray canvas; only the cards are white).
           Mounted eagerly (v-show, not v-if) whenever contracts are enabled so its
           document count loads on page load and the tab badge is accurate before the
           first click. -->
      <div v-if="!editMode && contractsEnabled" v-show="activeTab === 'documents'">
        <MemberDocumentsTab ref="memberDocumentsTab" :member="member" @documents-loaded="documentCount = $event" />
      </div>

      <!-- Access Control Tab (own component; rendered directly on the gray canvas).
           Mounted eagerly (v-show, not v-if) so its active-access count is available
           for the tab badge before the tab is first opened. -->
      <div v-if="!editMode" v-show="activeTab === 'access'">
        <AccessTab
          ref="accessTab"
          :member="member"
          :max-devices-per-member="maxDevicesPerMember"
        />
      </div>

      <!-- Check-ins Tab (own component; rendered directly on the gray canvas) -->
      <div v-if="!editMode" v-show="activeTab === 'checkins'">
        <CheckinsTab :member="member" />
      </div>

      <!-- Status History Tab (rendered directly on the gray canvas; only the cards are white) -->
      <div v-if="!editMode" v-show="activeTab === 'history'">
        <StatusHistory :member="member" />
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
                    Mindestlaufzeit: {{ selectedMembership.membership_plan?.commitment_months }} Monate ab {{ formatDate(selectedMembership.start_date) }}
                  </p>
                  <p v-if="selectedMembership?.membership_plan?.cancellation_period" class="mt-1 text-sm text-blue-600">
                    <Clock class="w-3 h-3 inline mr-1" />
                    Kündigungsfrist: {{ selectedMembership.membership_plan?.formatted_cancellation_period }}
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

    <!-- Modal für Mitgliedschaft widerrufen (§ 356a BGB) -->
    <teleport to="body">
      <div v-if="showWithdrawMembershipModal" class="fixed inset-0 bg-gray-500/75 overflow-y-auto h-full w-full z-50" @click="closeWithdrawMembership">
        <div class="relative top-20 mx-auto p-5 border border-gray-50 w-11/12 md:w-1/2 lg:w-1/3 shadow-lg rounded-md bg-white" @click.stop>
          <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
            <div class="mb-4">
              <h3 class="text-lg font-medium text-gray-900">
                Mitgliedschaft widerrufen
              </h3>
              <p class="text-sm text-gray-500 mt-1">
                Widerruf gemäß § 356a BGB innerhalb der 14-tägigen Widerrufsfrist.
              </p>
            </div>

            <div v-if="selectedMembership" class="space-y-4">
              <!-- Membership Info -->
              <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
                <h4 class="font-medium text-purple-900">{{ selectedMembership.membership_plan?.name }}</h4>
                <p class="text-sm text-purple-700 mt-1">
                  Vertragsbeginn: {{ formatDate(selectedMembership.contract_start_date || selectedMembership.start_date) }}
                </p>
                <p v-if="selectedMembership.withdrawal_deadline" class="text-sm text-purple-700">
                  Widerrufsfrist endet: {{ formatDate(selectedMembership.withdrawal_deadline) }}
                </p>
              </div>

              <!-- Warning -->
              <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                <div class="flex">
                  <AlertCircle class="w-5 h-5 text-yellow-600 mr-2 flex-shrink-0" />
                  <div class="text-sm text-yellow-800">
                    <p class="font-medium">Hinweis zum Widerruf:</p>
                    <ul class="mt-1 list-disc list-inside space-y-1">
                      <li>Der Widerruf ist unwiderruflich</li>
                      <li>Eine E-Mail-Bestätigung wird automatisch versendet</li>
                      <li>Bereits gezahlte Beträge werden innerhalb von 14 Tagen erstattet</li>
                    </ul>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
            <button
              type="button"
              @click="withdrawMembership"
              :disabled="withdrawingMembership"
              class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-purple-600 text-base font-medium text-white hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50"
            >
              {{ withdrawingMembership ? 'Wird widerrufen...' : 'Mitgliedschaft widerrufen' }}
            </button>
            <button
              type="button"
              @click="closeWithdrawMembership"
              class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
            >
              Abbrechen
            </button>
          </div>
        </div>
      </div>
    </teleport>

    <!-- Sperrliste Modal -->
    <teleport to="body">
      <div v-if="showBlockModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="showBlockModal = false">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6 space-y-4">
          <div class="flex items-center justify-between">
            <h3 class="font-semibold text-gray-900">Mitglied auf Sperrliste setzen</h3>
            <button @click="showBlockModal = false" class="text-gray-400 hover:text-gray-600">
              <X class="w-5 h-5" />
            </button>
          </div>

          <p class="text-sm text-gray-600">
            <strong>{{ member.first_name }} {{ member.last_name }}</strong> wird gesperrt.
            Alle Identifikatoren (IBAN, Telefon, Adresse, Name) werden gehashed und
            bei zukünftigen Registrierungen abgeglichen.
          </p>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Grund *</label>
            <select v-model="blockForm.reason" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
              <option value="payment_failed">Zahlungsausfall</option>
              <option value="chargeback">Rückbuchung</option>
              <option value="fraud">Betrugsverdacht</option>
              <option value="manual">Manuell</option>
            </select>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Begründung * (min. 10 Zeichen)</label>
            <textarea
              v-model="blockForm.notes"
              rows="3"
              placeholder="z.B. Mehrfache SEPA-Rücklastschriften, kein Kontakt möglich..."
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
            />
            <p v-if="blockForm.errors.notes" class="text-red-600 text-xs mt-1">{{ blockForm.errors.notes }}</p>
          </div>

          <div class="flex gap-3 justify-end">
            <button @click="showBlockModal = false" class="px-4 py-2 text-sm border border-gray-300 rounded-lg hover:bg-gray-50">
              Abbrechen
            </button>
            <button
              @click="submitBlock"
              :disabled="blockForm.processing || blockForm.notes.length < 10"
              class="px-4 py-2 text-sm bg-red-600 text-white rounded-lg hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              Auf Sperrliste setzen
            </button>
          </div>
        </div>
      </div>
    </teleport>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, nextTick } from 'vue'
import { useForm, Link, router } from '@inertiajs/vue3'
import { useInertiaPayments } from '@/composables/useInertiaPayments'
import AppLayout from '@/Layouts/AppLayout.vue'
import StatusHistory from '@/Components/StatusHistory.vue'
import MembershipTab from '@/Components/Members/MembershipTab.vue'
import PaymentsTab from '@/Components/Members/PaymentsTab.vue'
import MemberAddons from '@/Components/Members/MemberAddons.vue'
import MemberDocumentsTab from '@/Components/MemberDocumentsTab.vue'
import MemberPersonalDataTab from '@/Components/Members/MemberPersonalDataTab.vue'
import MemberHeaderCard from '@/Components/Members/MemberHeaderCard.vue'
import AccessTab from '@/Components/Members/AccessTab.vue'
import CheckinsTab from '@/Components/Members/CheckinsTab.vue'
import MemberAvatar from '@/Components/MemberAvatar.vue'
import {
  User, FileText, Clock, CreditCard,
  ArrowLeft, AlertCircle,
  History, Key, FolderOpen,
  X, AlertTriangle, MoreVertical
} from 'lucide-vue-next'
import { formatDate, formatDateForInput } from '@/utils/formatters'
import { getStatusText, getStatusColor } from '@/utils/memberStatus'

const props = defineProps({
  member: Object,
  availablePaymentMethods: {
    type: Array,
    default: () => []
  },
  membershipPlans: {
    type: Array,
    default: () => []
  },
  // Bookable add-ons keyed by membership id.
  bookableAddons: {
    type: Object,
    default: () => ({})
  },
  contractsEnabled: {
    type: Boolean,
    default: false
  },
  maxDevicesPerMember: {
    type: Number,
    default: 2
  },
  fraudCheck: {
    type: Object,
    default: null
  }
})

// Payments themselves live in the PaymentsTab component; the parent only reads
// them to drive the outstanding-balance badge on the payments tab.
const { payments } = useInertiaPayments(props.member.id)

const outstandingBalance = computed(() => {
  const list = payments.value || props.member.payments || []
  const chargebacks = list.filter(p => p.status === 'chargeback')
  const sum = chargebacks.reduce((acc, cb) => {
    const hasSettlement = list.some(p => p.status === 'paid' && p.notes === cb.mollie_payment_id)
    return hasSettlement ? acc : acc + Math.abs(parseFloat(cb.amount))
  }, 0)
  return sum > 0 ? sum : null
})

const editMode = ref(false)

// Mobile collapsing header: the app-bar title morphs into a compact member
// identity once the page is scrolled past the profile card. Desktop is
// unaffected (see the lg: overrides in the template).
const headerCollapsed = ref(false)

// Show the compact identity in the app bar only when scrolled and not editing.
const showMobileIdentity = computed(() => headerCollapsed.value && !editMode.value)

const headerInitials = computed(() =>
  `${props.member.first_name?.charAt(0) || ''}${props.member.last_name?.charAt(0) || ''}`.toUpperCase()
)
const headerStatusText = computed(() => getStatusText(props.member.status))
const headerStatusDotClass = computed(() => {
  const dotColors = {
    green: 'bg-green-500',
    gray: 'bg-gray-400',
    yellow: 'bg-yellow-500',
    orange: 'bg-orange-500',
    red: 'bg-red-500',
    black: 'bg-gray-900',
  }
  return dotColors[getStatusColor(props.member.status)] || 'bg-gray-400'
})

// Component refs: the header card owns the header UI (incl. discard dialog),
// the personal-data tab owns the personal-data form and its dirty state.
const headerCard = ref(null)
const personalDataTab = ref(null)

// Bridge the header controls to the personal-data component
const isDirty = computed(() => personalDataTab.value?.isDirty ?? false)
const isSaving = computed(() => personalDataTab.value?.form?.processing ?? false)

// Member number lives in the personal-data form but is edited in the header
const memberNumber = computed({
  get: () => personalDataTab.value?.form?.member_number ?? props.member.member_number,
  set: (value) => {
    if (personalDataTab.value?.form) {
      personalDataTab.value.form.member_number = value
    }
  },
})

// Sperrliste
const showBlockModal = ref(false)
const blockForm = useForm({
  reason: 'payment_failed',
  notes: '',
})

const submitBlock = () => {
  blockForm.post(route('blocklist.block-member', { member: props.member.id }), {
    onSuccess: () => { showBlockModal.value = false },
  })
}
const activeTab = ref('personal')
const documentCount = ref(0)
const memberDocumentsTab = ref(null)
const paymentsTab = ref(null)

// Credit top-up: switch to the payments tab and open its modal in top-up mode.
const handleTopup = () => {
  activeTab.value = 'payments'
  nextTick(() => paymentsTab.value?.openTopup())
}

// Access-control UI lives in the AccessTab component; the parent only reads its
// active-access count to drive the tab badge.
const accessTab = ref(null)
const activeAccessCount = computed(() => accessTab.value?.activeAccessCount ?? 0)

// Age verification state
const verifyingAge = ref(false)

// Guest access state
const togglingGuestAccess = ref(false)

// Membership-related state
const pausingMembership = ref(null)
const resumingMembership = ref(null)
const cancellingMembership = ref(null)
const revokingCancellation = ref(null)
const activatingMembership = ref(null)
const abortingMembership = ref(null)
const withdrawingMembership = ref(null)
const showPauseMembershipModal = ref(false)
const showCancelMembershipModal = ref(false)
const showWithdrawMembershipModal = ref(false)
const selectedMembership = ref(null)

// Age is derived from the persisted member record (the form lives in the tab component)
const memberAge = computed(() => {
  const birthDate = props.member.birth_date
  if (!birthDate) return null
  const birth = new Date(birthDate)
  const today = new Date()
  let age = today.getFullYear() - birth.getFullYear()
  const monthDiff = today.getMonth() - birth.getMonth()
  if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) {
    age--
  }
  return age
})

const tabs = computed(() => {
  const baseTabs = [
    { id: 'personal', name: 'Persönliche Daten', icon: User },
    { id: 'membership', name: 'Mitgliedschaften', icon: FileText },
    { id: 'payments', name: 'Zahlungen', icon: CreditCard },
  ]
  if (props.contractsEnabled) {
    baseTabs.push({ id: 'documents', name: 'Dokumente', icon: FolderOpen })
  }
  baseTabs.push(
    { id: 'checkins', name: 'Check-Ins', icon: Clock },
    { id: 'access', name: 'Zugänge', icon: Key },
    { id: 'history', name: 'Verlauf', icon: History },
  )
  return baseTabs
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

// Status-Change Handler
const handleStatusChanged = (newStatus) => {
  console.log('Status wurde geändert zu:', newStatus)
  // Die Seite wird automatisch durch Inertia aktualisiert
  // Dokumente-Tab neu laden, da bei Aktivierung ggf. Verträge generiert werden
  if (newStatus === 'active') {
    memberDocumentsTab.value?.fetchDocuments()
  }
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
      memberDocumentsTab.value?.fetchDocuments()
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

const abortMembership = (membership) => {
  if (!confirm('Möchten Sie diesen Gratis-Testzeitraum wirklich abbrechen? Der Zeitraum wird sofort beendet.')) {
    return
  }

  abortingMembership.value = membership.id

  router.put(route('members.memberships.abort', {
    member: props.member.id,
    membership: membership.id
  }), {}, {
    preserveScroll: true,
    onSuccess: () => {
      abortingMembership.value = null
    },
    onError: () => {
      abortingMembership.value = null
      alert('Der Gratis-Testzeitraum konnte nicht abgebrochen werden.')
    }
  })
}

// Widerruf gemäß § 356a BGB
const forceWithdraw = ref(false)

const openWithdrawMembership = (membership, force = false) => {
  selectedMembership.value = membership
  forceWithdraw.value = force
  showWithdrawMembershipModal.value = true
}

const closeWithdrawMembership = () => {
  showWithdrawMembershipModal.value = false
  selectedMembership.value = null
  forceWithdraw.value = false
}

const withdrawMembership = () => {
  if (!confirm('Möchten Sie diese Mitgliedschaft wirklich widerrufen? Der Widerruf ist unwiderruflich und löst eine E-Mail-Bestätigung aus.')) {
    return
  }

  withdrawingMembership.value = selectedMembership.value.id

  router.put(route('members.memberships.withdraw', {
    member: props.member.id,
    membership: selectedMembership.value.id
  }), {
    force: forceWithdraw.value,
  }, {
    preserveScroll: true,
    onSuccess: () => {
      withdrawingMembership.value = null
      closeWithdrawMembership()
    },
    onError: () => {
      withdrawingMembership.value = null
    }
  })
}

const openCancelMembership = (membership) => {
  selectedMembership.value = membership
  cancelMembershipForm.membership_id = membership.id
  cancelMembershipForm.cancellation_date = formatDateForInput(membership.default_cancellation_date)
  cancelMembershipForm.min_cancellation_date = formatDateForInput(membership.min_cancellation_date)
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

// Force-Status Handler für Mitgliedschaften
const forcingMembershipStatus = ref(null)

const handleForceStatus = (membership, newStatus) => {
  // Für bestimmte Status die vorhandenen Modals als Bestätigung nutzen
  if (newStatus === 'paused') {
    openPauseMembership(membership)
    return
  }

  if (newStatus === 'cancelled') {
    openCancelMembership(membership)
    return
  }

  if (newStatus === 'withdrawn') {
    openWithdrawMembership(membership, true)
    return
  }

  // Für alle anderen Stati: direkte Bestätigung und API-Call
  const statusLabels = {
    'active': 'Aktiv',
    'expired': 'Abgelaufen',
    'pending': 'Ausstehend',
  }

  if (!confirm(`Möchten Sie den Status dieser Mitgliedschaft wirklich auf "${statusLabels[newStatus] || newStatus}" forcieren?`)) {
    return
  }

  forcingMembershipStatus.value = membership.id

  router.put(route('members.memberships.force-status', {
    member: props.member.id,
    membership: membership.id
  }), {
    status: newStatus
  }, {
    preserveScroll: true,
    onSuccess: () => {
      forcingMembershipStatus.value = null
    },
    onError: () => {
      forcingMembershipStatus.value = null
      alert('Der Status konnte nicht geändert werden.')
    }
  })
}

const enterEditMode = () => {
  activeTab.value = 'personal'
  editMode.value = true
  // Snapshot the form once the component is mounted/updated with editMode on
  nextTick(() => personalDataTab.value?.enterEdit())
}

// Leave edit mode and discard local edits (the discard confirmation, if any,
// is handled by the header card before this is emitted).
const exitEditMode = () => {
  personalDataTab.value?.resetForm()
  editMode.value = false
}

// Trigger the save from the header button
const savePersonalData = () => {
  personalDataTab.value?.save()
}

// Called after the personal-data form was persisted successfully
const onPersonalDataSaved = () => {
  editMode.value = false
}

const toggleAgeVerification = () => {
  verifyingAge.value = true

  router.post(route('members.toggle-age-verification', props.member.id), {}, {
    preserveScroll: true,
    onSuccess: () => {
      verifyingAge.value = false
    },
    onError: () => {
      verifyingAge.value = false
    }
  })
}

const toggleGuestAccess = () => {
  togglingGuestAccess.value = true

  router.post(route('members.toggle-guest-access', props.member.id), {}, {
    preserveScroll: true,
    onSuccess: () => {
      togglingGuestAccess.value = false
    },
    onError: () => {
      togglingGuestAccess.value = false
    }
  })
}

// Mobile collapsing-header wiring: watch the AppLayout scroll container and
// flip `headerCollapsed` once the profile card has scrolled under the app bar.
// Also publishes the app-bar height as a CSS variable so the sticky tab rail
// can offset itself correctly below it.
const pageRoot = ref(null)
let scrollContainer = null
let appHeader = null

// Walk up from our own root to the nearest actually-scrollable ancestor. This
// is more robust than matching Tailwind class names on the AppLayout container.
const findScrollParent = (el) => {
  let node = el?.parentElement
  while (node && node !== document.body) {
    const oy = getComputedStyle(node).overflowY
    if ((oy === 'auto' || oy === 'scroll') && node.scrollHeight > node.clientHeight) {
      return node
    }
    node = node.parentElement
  }
  return null
}

const updateHeaderHeight = () => {
  if (appHeader) {
    document.documentElement.style.setProperty('--gp-header-height', `${appHeader.offsetHeight}px`)
  }
}

const onLayoutScroll = () => {
  if (!scrollContainer) return
  headerCollapsed.value = scrollContainer.scrollTop > 96
}

onMounted(() => {
  const urlParams = new URLSearchParams(window.location.search)
  if (urlParams.get('edit') === 'true') {
    enterEditMode()
  }

  scrollContainer = findScrollParent(pageRoot.value)
    || document.querySelector('.flex-1.overflow-y-auto')
  appHeader = scrollContainer?.querySelector('header')
    || document.querySelector('header')
  updateHeaderHeight()
  window.addEventListener('resize', updateHeaderHeight)
  if (scrollContainer) {
    scrollContainer.addEventListener('scroll', onLayoutScroll, { passive: true })
    onLayoutScroll()
  }
})

onBeforeUnmount(() => {
  window.removeEventListener('resize', updateHeaderHeight)
  scrollContainer?.removeEventListener('scroll', onLayoutScroll)
  document.documentElement.style.removeProperty('--gp-header-height')
})
</script>

<style scoped>
/* Hide the horizontal scrollbar on the tab rail (mobile pill / desktop underline) */
.gp-tab-rail {
  scrollbar-width: none;
  -ms-overflow-style: none;
}
.gp-tab-rail::-webkit-scrollbar {
  display: none;
}
</style>
