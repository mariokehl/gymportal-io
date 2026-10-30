<template>
  <div :class="['flex flex-col gap-6', showMobileTotal ? 'max-sm:pb-24' : '']">
    <!-- Active Memberships: overview tiles (master) + selected contract with its services (detail) -->
    <div class="grid gap-5 lg:grid-cols-[260px_minmax(0,1fr)] lg:items-start">
      <div class="flex flex-col gap-3 lg:gap-2.5 min-w-0">
        <div class="flex items-center justify-between gap-2.5">
          <h3 class="text-xs lg:text-lg font-medium lg:font-bold uppercase lg:normal-case tracking-wider lg:tracking-normal text-gray-500 lg:text-gray-900">
            Mitgliedschaften<span class="lg:hidden"> · {{ membershipEntries.length }}</span>
            <span
              v-if="membershipEntries.length > 0"
              class="hidden lg:inline-flex ml-1.5 align-middle text-xs font-bold px-1.5 py-0.5 rounded-full min-w-[18px] justify-center bg-indigo-100 text-indigo-700"
            >{{ membershipEntries.length }}</span>
          </h3>
          <div class="flex gap-1 lg:gap-1.5">
            <button
              @click="openFreePeriodModal()"
              type="button"
              title="Gratis-Zeitraum ohne Mitgliedschaft"
              class="inline-flex items-center justify-center gap-1 h-9 px-2 text-[13px] font-medium text-green-700 transition-colors lg:w-9 lg:px-0 lg:rounded-lg lg:border lg:border-gray-300 lg:bg-white lg:hover:bg-gray-50"
            >
              <Gift class="w-3.5 h-3.5 lg:w-4 lg:h-4 flex-none" />
              <span class="lg:hidden">Gratis</span>
            </button>
            <button
              @click="openAddMembershipModal"
              type="button"
              title="Neue Mitgliedschaft"
              class="inline-flex items-center justify-center gap-1 h-9 pl-2 text-[13px] font-medium text-indigo-600 transition-colors lg:w-9 lg:px-0 lg:rounded-lg lg:bg-indigo-600 lg:text-white lg:hover:bg-indigo-700"
            >
              <Plus class="w-3.5 h-3.5 lg:w-4 lg:h-4 flex-none" />
              <span class="lg:hidden">Neu</span>
            </button>
          </div>
        </div>

        <!-- All tiles: one horizontal picker with vertical dividers (mobile) / vertical list (desktop) -->
        <div class="flex gap-2.5 overflow-x-auto snap-x scroll-px-1 -m-1 p-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden lg:m-0 lg:flex-col lg:overflow-visible lg:snap-none lg:p-0">
          <!-- Current contracts -->
          <button
            v-for="entry in membershipEntries"
            :key="entry.membership.id"
            @click="selectedMembershipId = entry.membership.id"
            type="button"
            :aria-pressed="entry === selectedEntry"
            :class="[
              'flex-none w-[250px] lg:w-auto snap-start text-left rounded-lg border bg-white px-3.5 lg:px-4 py-3 lg:py-3.5 flex flex-col gap-2 cursor-pointer transition-colors',
              entry === selectedEntry ? 'border-indigo-600 ring-1 ring-indigo-600' : 'border-gray-200 hover:border-gray-300'
            ]"
          >
            <span class="flex items-center justify-between gap-2">
              <span class="text-[15px] font-semibold text-gray-900 truncate">{{ entry.membership.membership_plan?.name || 'Unbekannter Vertrag' }}</span>
              <span :class="['flex-none px-2 py-0.5 rounded-full text-xs font-medium', membershipStatusOption(entry.membership.status).badgeClass]">
                {{ membershipStatusOption(entry.membership.status).label }}
              </span>
            </span>
            <span class="text-xs text-gray-500">{{ formatTerm(entry.membership) }}</span>
            <span v-if="entry.freeTrial" class="flex items-center gap-1.5 text-xs font-medium text-green-700">
              <Gift class="w-3.5 h-3.5" /> bis {{ formatDate(entry.freeTrial.end_date) }} kostenfrei
            </span>
            <span class="flex items-center justify-between gap-2 pt-2 border-t border-gray-100">
              <span class="flex items-center gap-1.5 text-xs lg:text-[13px] text-gray-600">
                <Puzzle class="w-3.5 h-3.5" /> {{ addonCountLabel(entry.membership) }}
              </span>
              <span class="text-sm lg:text-[15px] font-bold text-gray-900">{{ formatCurrency(entryTotal(entry.membership)) }}</span>
            </span>
          </button>

          <!-- Free periods that are not linked to a membership -->
          <template v-if="standaloneFreeTrials.length > 0">
            <div class="lg:hidden flex-none self-stretch flex items-center gap-1.5 px-1" aria-hidden="true">
              <span class="w-px self-stretch bg-gray-300"></span>
              <span class="flex flex-col items-center text-[10px] font-semibold leading-[1.15] text-gray-400">
                <span v-for="(letter, index) in 'OHNE'" :key="index">{{ letter }}</span>
              </span>
            </div>
            <div class="hidden lg:block mt-1.5 text-xs font-medium uppercase tracking-wider text-gray-500">Ohne Mitgliedschaft</div>
            <div
              v-for="freeTrial in standaloneFreeTrials"
              :key="freeTrial.id"
              role="button"
              tabindex="0"
              :aria-pressed="selectedEntry?.membership.id === freeTrial.id"
              @click="selectedMembershipId = freeTrial.id"
              @keydown.enter.self.prevent="selectedMembershipId = freeTrial.id"
              @keydown.space.self.prevent="selectedMembershipId = freeTrial.id"
              :class="[
                'flex-none w-[250px] lg:w-auto snap-start rounded-lg border bg-white shadow-sm px-3.5 py-3 flex flex-col gap-2 cursor-pointer transition-colors',
                selectedEntry?.membership.id === freeTrial.id ? 'border-indigo-600 ring-1 ring-indigo-600' : 'border-gray-200 hover:border-gray-300'
              ]"
            >
              <div class="flex items-center justify-between gap-2">
                <span class="flex items-center gap-2 min-w-0 text-[15px] font-semibold text-gray-900">
                  <Gift class="w-4 h-4 flex-none text-green-700" />
                  <span class="truncate">{{ freeTrial.membership_plan?.name || 'Gratis-Zeitraum' }}</span>
                </span>
                <span :class="['flex-none px-2 py-0.5 rounded-full text-xs font-medium', membershipStatusOption(freeTrial.status).badgeClass]">
                  {{ membershipStatusOption(freeTrial.status).label }}
                </span>
              </div>
              <div class="text-xs text-gray-500">{{ formatTerm(freeTrial) }}</div>
              <div class="flex items-center justify-between gap-2 pt-2 border-t border-gray-100">
                <button
                  v-if="freeTrial.status === 'pending'"
                  @click.stop="$emit('activate', freeTrial)"
                  type="button"
                  :disabled="activatingMembership === freeTrial.id"
                  class="inline-flex items-center gap-1.5 text-[13px] font-medium text-green-700 hover:text-green-800 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  <CheckCircle class="w-3.5 h-3.5" />
                  {{ activatingMembership === freeTrial.id ? 'Wird aktiviert...' : 'Aktivieren' }}
                </button>
                <button
                  v-else-if="freeTrial.status === 'active'"
                  @click.stop="$emit('abort', freeTrial)"
                  type="button"
                  :disabled="abortingMembership === freeTrial.id"
                  class="inline-flex items-center gap-1.5 text-[13px] font-medium text-red-600 hover:text-red-800 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  <StopCircle class="w-3.5 h-3.5" />
                  {{ abortingMembership === freeTrial.id ? 'Wird gestoppt...' : 'Stoppen' }}
                </button>
                <span v-else></span>
                <span class="text-sm lg:text-[15px] font-bold text-gray-900">{{ formatCurrency(0) }}</span>
              </div>
            </div>
          </template>

          <!-- Past contracts: always in the picker (mobile) / collapsed by default (desktop) -->
          <template v-if="pastEntries.length > 0">
            <div class="lg:hidden flex-none self-stretch flex items-center gap-1.5 px-1" aria-hidden="true">
              <span class="w-px self-stretch bg-gray-300"></span>
              <span class="flex flex-col items-center text-[10px] font-semibold leading-[1.15] text-gray-400">
                <span v-for="(letter, index) in 'FRÜHER'" :key="index">{{ letter }}</span>
              </span>
            </div>
            <button
              @click="showPastMemberships = !showPastMemberships"
              type="button"
              :aria-expanded="showPastMemberships"
              class="hidden lg:flex items-center justify-between gap-2 mt-1.5 py-1 text-xs font-medium uppercase tracking-wider text-gray-500 hover:text-gray-700 cursor-pointer transition-colors"
            >
              <span>Frühere Mitgliedschaften</span>
              <ChevronDown :class="['w-4 h-4 transition-transform', showPastMemberships ? 'rotate-180' : '']" />
            </button>
            <button
              v-for="entry in pastEntries"
              :key="entry.membership.id"
              @click="selectedMembershipId = entry.membership.id"
              type="button"
              :aria-pressed="entry === selectedEntry"
              :class="[
                'flex-none w-[250px] lg:w-auto snap-start text-left rounded-lg border px-3.5 lg:px-4 py-3 flex flex-col gap-1.5 cursor-pointer transition-colors',
                entry === selectedEntry ? 'bg-white border-indigo-600 ring-1 ring-indigo-600' : 'bg-gray-50 border-gray-200 hover:border-gray-300',
                showPastMemberships ? '' : 'lg:hidden'
              ]"
            >
              <span class="flex items-center justify-between gap-2">
                <span class="text-sm font-semibold text-gray-600 truncate">{{ entry.membership.membership_plan?.name || 'Unbekannter Vertrag' }}</span>
                <span :class="['flex-none px-2 py-0.5 rounded-full text-xs font-medium', membershipStatusOption(entry.membership.status).badgeClass]">
                  {{ membershipStatusOption(entry.membership.status).label }}
                </span>
              </span>
              <span class="text-xs text-gray-500">{{ formatTerm(entry.membership) }}</span>
              <span v-if="addonCount(entry.membership) > 0" class="flex items-center gap-1.5 text-xs text-gray-500">
                <Puzzle class="w-3.5 h-3.5" /> {{ addonCountLabel(entry.membership) }}
              </span>
            </button>
          </template>
        </div>
      </div>

      <!-- Selected contract -->
      <div v-if="selectedEntry" class="flex flex-col rounded-lg border border-gray-200 bg-white sm:overflow-hidden min-w-0">
        <MembershipCard
          :membership="selectedEntry.membership"
          :is-secondary="selectedIsPast"
          :show-price="false"
          detail
          :resumingMembership="resumingMembership"
          :revokingCancellation="revokingCancellation"
          :activatingMembership="activatingMembership"
          :abortingMembership="abortingMembership"
          :withdrawingMembership="withdrawingMembership"
          :forcingMembershipStatus="forcingMembershipStatus"
          @activate="$emit('activate', $event)"
          @pause="$emit('pause', $event)"
          @resume="$emit('resume', $event)"
          @cancel="$emit('cancel', $event)"
          @revoke-cancellation="$emit('revoke-cancellation', $event)"
          @abort="$emit('abort', $event)"
          @withdraw="$emit('withdraw', $event)"
          @force-status="(m, s) => $emit('force-status', m, s)"
        >
          <template v-if="selectedIsPast" #actions>
            <button
              @click="$emit('ignore', selectedEntry.membership)"
              type="button"
              :disabled="ignoringMembership === selectedEntry.membership.id"
              class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md border border-gray-300 bg-white text-sm font-medium text-red-600 hover:bg-gray-50 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <EyeOff class="w-4 h-4" />
              <span>{{ ignoringMembership === selectedEntry.membership.id ? 'Ignorieren...' : 'Ignorieren' }}</span>
            </button>
          </template>
        </MembershipCard>

        <!-- Services: free period, base fee and add-ons of this contract -->
        <div v-if="!selectedEntry.membership.is_free_trial" class="sm:px-6 pt-4 sm:pt-5 pb-4 sm:pb-5 flex flex-col sm:gap-3">
          <div class="flex items-center justify-between gap-3 flex-wrap px-4 sm:px-0 pb-1.5 sm:pb-0">
            <h4 class="text-xs sm:text-base font-medium sm:font-semibold uppercase sm:normal-case tracking-wider sm:tracking-normal text-gray-500 sm:text-gray-900">Leistungen</h4>
            <div class="hidden sm:flex gap-2 flex-wrap">
              <button
                v-if="canPrependFreePeriod(selectedEntry.membership)"
                @click="openFreePeriodModal(selectedEntry.membership.id)"
                type="button"
                class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg border border-gray-300 bg-white text-green-700 text-sm font-medium hover:bg-gray-50 transition-colors"
              >
                <Gift class="w-4 h-4" /> Gratis-Zeitraum vorschalten
              </button>
              <button
                v-if="!selectedIsPast && selectedBookableAddons.length > 0"
                @click="memberAddons?.openBookingDialog()"
                type="button"
                class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700 transition-colors"
              >
                <Plus class="w-4 h-4" /> Add-on hinzufügen
              </button>
            </div>
          </div>

          <div class="sm:rounded-lg sm:border border-gray-200 overflow-hidden">
            <div class="hidden sm:grid grid-cols-[minmax(0,1fr)_7rem] gap-3 px-4 py-2.5 bg-gray-50 text-xs font-medium uppercase tracking-wider text-gray-500">
              <span>Leistung</span><span class="text-right">Betrag</span>
            </div>

            <!-- Linked free period in front of the contract -->
            <div
              v-if="selectedEntry.freeTrial"
              class="grid grid-cols-[minmax(0,1fr)_auto] sm:grid-cols-[minmax(0,1fr)_7rem] gap-x-3 gap-y-2 items-center px-4 py-3.5 border-t border-gray-100"
            >
              <div class="flex gap-3 items-center min-w-0">
                <span class="w-8 h-8 rounded-lg flex items-center justify-center flex-none bg-green-100 text-green-700">
                  <Gift class="w-4 h-4" />
                </span>
                <div class="min-w-0">
                  <div class="flex items-center gap-2 flex-wrap">
                    <span class="text-sm font-semibold text-gray-900">{{ selectedEntry.freeTrial.membership_plan?.name || 'Gratis-Zeitraum' }}</span>
                    <span :class="['px-2 py-0.5 rounded-full text-xs font-medium', membershipStatusOption(selectedEntry.freeTrial.status).badgeClass]">
                      {{ membershipStatusOption(selectedEntry.freeTrial.status).label }}
                    </span>
                  </div>
                  <div class="text-xs text-gray-500 mt-0.5">vorgeschaltet · {{ formatTerm(selectedEntry.freeTrial) }}</div>
                </div>
              </div>
              <span class="text-right text-sm font-semibold text-gray-900">{{ formatCurrency(0) }}</span>
              <div v-if="['pending', 'active'].includes(selectedEntry.freeTrial.status)" class="col-span-2 flex pl-11">
                <button
                  v-if="selectedEntry.freeTrial.status === 'pending'"
                  @click="$emit('activate', selectedEntry.freeTrial)"
                  type="button"
                  :disabled="activatingMembership === selectedEntry.freeTrial.id"
                  class="inline-flex items-center gap-1.5 text-[13px] font-medium text-green-700 hover:text-green-800 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  <CheckCircle class="w-3.5 h-3.5" />
                  {{ activatingMembership === selectedEntry.freeTrial.id ? 'Wird aktiviert...' : 'Aktivieren' }}
                </button>
                <button
                  v-if="selectedEntry.freeTrial.status === 'active'"
                  @click="$emit('abort', selectedEntry.freeTrial)"
                  type="button"
                  :disabled="abortingMembership === selectedEntry.freeTrial.id"
                  class="inline-flex items-center gap-1.5 text-[13px] font-medium text-red-600 hover:text-red-800 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  <StopCircle class="w-3.5 h-3.5" />
                  {{ abortingMembership === selectedEntry.freeTrial.id ? 'Wird gestoppt...' : 'Stoppen' }}
                </button>
              </div>
            </div>

            <!-- Base fee -->
            <div class="grid grid-cols-[minmax(0,1fr)_auto] sm:grid-cols-[minmax(0,1fr)_7rem] gap-x-3 items-center px-4 py-3.5 border-t border-gray-100">
              <div class="flex gap-3 items-center min-w-0">
                <span class="w-8 h-8 rounded-lg flex items-center justify-center flex-none bg-gray-100 text-gray-600">
                  <FileText class="w-4 h-4" />
                </span>
                <div class="min-w-0">
                  <div class="text-sm font-semibold text-gray-900">Grundbeitrag {{ selectedEntry.membership.membership_plan?.name }}</div>
                  <div class="text-xs text-gray-500 mt-0.5">
                    Tarif · seit {{ formatDate(selectedEntry.membership.start_date) }}<template v-if="selectedNextBillingAt"> · nächste Abrechnung {{ formatDate(selectedNextBillingAt) }}</template>
                  </div>
                </div>
              </div>
              <div class="text-right">
                <div class="text-sm font-semibold text-gray-900">{{ formatCurrency(selectedEntry.membership.membership_plan?.price || 0) }}</div>
                <div class="text-xs text-gray-500">pro {{ formatBillingCycle(selectedEntry.membership.membership_plan?.billing_cycle || 'monthly') }}</div>
              </div>
            </div>

            <!-- Booked add-ons -->
            <MemberAddons
              ref="memberAddons"
              :key="selectedEntry.membership.id"
              :member="member"
              :membership="selectedEntry.membership"
              :bookable-addons="selectedBookableAddons"
              :readonly="selectedIsPast"
            />

            <!-- Monthly total -->
            <div
              v-if="!selectedIsPast && selectedTotal !== null"
              class="hidden sm:grid grid-cols-[minmax(0,1fr)_auto] sm:grid-cols-[minmax(0,1fr)_7rem] gap-x-3 items-baseline px-4 py-3.5 border-t border-gray-200 bg-gray-50"
            >
              <span class="text-sm font-semibold text-gray-900">
                Summe pro Monat
                <span class="font-normal text-gray-500">· {{ totalBreakdown(selectedEntry.membership) }}</span>
              </span>
              <span class="text-right text-lg font-bold text-gray-900">{{ formatCurrency(selectedTotal) }}</span>
            </div>
          </div>

          <p class="hidden sm:block text-[13px] text-gray-500">Add-ons laufen synchron zum Beitrag und enden spätestens mit diesem Vertrag.</p>

          <!-- Add actions below the services (mobile) -->
          <div
            v-if="!selectedIsPast && (selectedBookableAddons.length > 0 || canPrependFreePeriod(selectedEntry.membership))"
            class="sm:hidden flex flex-col items-center gap-2 px-4 pt-2"
          >
            <button
              v-if="selectedBookableAddons.length > 0"
              @click="memberAddons?.openBookingDialog()"
              type="button"
              class="w-full h-11 inline-flex items-center justify-center gap-1.5 rounded-lg border border-dashed border-indigo-300 bg-indigo-50 text-indigo-700 text-sm font-medium"
            >
              <Plus class="w-4 h-4" /> Add-on zu {{ selectedEntry.membership.membership_plan?.name }} hinzufügen
            </button>
            <button
              v-if="canPrependFreePeriod(selectedEntry.membership)"
              @click="openFreePeriodModal(selectedEntry.membership.id)"
              type="button"
              class="min-h-10 inline-flex items-center gap-1.5 text-green-700 text-sm font-medium"
            >
              <Gift class="w-4 h-4" /> Gratis-Zeitraum vorschalten
            </button>
          </div>
        </div>

        <!-- Monthly total, pinned to the bottom of the screen (mobile) -->
        <div
          ref="mobileTotalBar"
          v-if="showMobileTotal"
          class="sm:hidden fixed inset-x-0 bottom-0 z-30 flex items-center justify-between gap-3 px-4 pt-3 pb-[max(1.25rem,env(safe-area-inset-bottom))] bg-white border-t border-gray-200 shadow-[0_-4px_12px_rgba(0,0,0,0.04)]"
        >
          <div class="min-w-0 text-xs text-gray-500">
            <div>{{ selectedEntry.membership.membership_plan?.name }} gesamt</div>
            <div>{{ totalBreakdown(selectedEntry.membership) }}</div>
          </div>
          <div class="flex-none text-xl font-bold text-gray-900">
            {{ formatCurrency(selectedTotal) }}<span class="text-xs font-normal text-gray-500"> / Monat</span>
          </div>
        </div>

      </div>

      <!-- No Memberships -->
      <div v-else class="text-center py-8">
        <UserX class="w-12 h-12 text-gray-400 mx-auto mb-4" />
        <p class="text-gray-500">Keine aktiven Mitgliedschaften vorhanden</p>
      </div>
    </div>

    <!-- Free Period Modal -->
    <teleport to="body">
      <div v-if="showFreePeriodModal" class="fixed inset-0 bg-gray-500/75 overflow-y-auto h-full w-full z-50" @click="closeFreePeriodModal">
        <div class="relative top-10 mx-auto p-5 border border-gray-50 w-11/12 md:w-1/2 lg:w-1/3 shadow-lg rounded-md bg-white" @click.stop>
          <form @submit.prevent="addFreePeriod">
            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
              <div class="mb-4">
                <h3 class="text-lg font-medium text-gray-900">
                  Gratis-Zeitraum hinzufügen
                </h3>
                <p class="text-sm text-gray-500 mt-1">
                  Erstelle einen kostenlosen Zeitraum, z.B. für Probetraining oder Überbrückung.
                </p>
              </div>

              <!-- Error message -->
              <div v-if="freePeriodForm.errors.error" class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg">
                <div class="flex">
                  <AlertCircle class="h-5 w-5 text-red-400 flex-shrink-0" />
                  <p class="ml-3 text-sm text-red-800">{{ freePeriodForm.errors.error }}</p>
                </div>
              </div>

              <div class="space-y-4">
                <!-- Start Date -->
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">
                    Startdatum
                  </label>
                  <input
                    type="date"
                    v-model="freePeriodForm.start_date"
                    class="w-full border border-gray-300 rounded-md shadow-sm px-3 py-2 focus:ring-green-500 focus:border-green-500"
                    :class="{ 'border-red-500': freePeriodForm.errors.start_date }"
                  />
                  <p v-if="freePeriodForm.errors.start_date" class="mt-1 text-sm text-red-600">
                    {{ freePeriodForm.errors.start_date }}
                  </p>
                </div>

                <!-- End Date -->
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">
                    Enddatum
                  </label>
                  <input
                    type="date"
                    v-model="freePeriodForm.end_date"
                    class="w-full border border-gray-300 rounded-md shadow-sm px-3 py-2 focus:ring-green-500 focus:border-green-500"
                    :class="{ 'border-red-500': freePeriodForm.errors.end_date }"
                  />
                  <p v-if="freePeriodForm.errors.end_date" class="mt-1 text-sm text-red-600">
                    {{ freePeriodForm.errors.end_date }}
                  </p>
                </div>

                <!-- Link to Membership (optional) -->
                <div v-if="linkableMemberships.length > 0">
                  <label class="block text-sm font-medium text-gray-700 mb-1">
                    Mit Mitgliedschaft verknüpfen (optional)
                  </label>
                  <select
                    v-model="freePeriodForm.linked_membership_id"
                    class="w-full border border-gray-300 rounded-md shadow-sm px-3 py-2 focus:ring-green-500 focus:border-green-500"
                  >
                    <option :value="null">Keine Verknüpfung</option>
                    <option v-for="m in linkableMemberships" :key="m.id" :value="m.id">
                      {{ m.membership_plan?.name || 'Unbekannt' }} (ab {{ formatDate(m.start_date) }})
                    </option>
                  </select>
                  <p class="mt-1 text-xs text-gray-500">
                    Verknüpfe diesen Gratis-Zeitraum mit einer bestehenden oder neuen Mitgliedschaft.
                  </p>
                </div>

                <!-- Preview -->
                <div v-if="freePeriodForm.start_date && freePeriodForm.end_date" class="bg-green-50 border border-green-200 rounded-lg p-3">
                  <div class="flex items-start gap-2">
                    <Gift class="w-5 h-5 text-green-600 flex-shrink-0 mt-0.5" />
                    <div class="text-sm">
                      <p class="font-medium text-green-800">Vorschau</p>
                      <p class="text-green-700">
                        Gratis-Zeitraum vom {{ formatDate(freePeriodForm.start_date) }}
                        bis {{ formatDate(freePeriodForm.end_date) }}
                        ({{ freePeriodDays }} Tage)
                      </p>
                    </div>
                  </div>
                </div>

                <!-- Guest access warning -->
                <GuestAccessRevokeWarning v-if="showGuestAccessWarning" />
              </div>
            </div>

            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
              <button
                type="submit"
                :disabled="freePeriodForm.processing || !freePeriodForm.start_date || !freePeriodForm.end_date"
                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-green-600 text-base font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {{ freePeriodForm.processing ? 'Wird erstellt...' : 'Gratis-Zeitraum erstellen' }}
              </button>
              <button
                type="button"
                @click="closeFreePeriodModal"
                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
              >
                Abbrechen
              </button>
            </div>
          </form>
        </div>
      </div>
    </teleport>

    <!-- Add Membership Modal -->
    <teleport to="body">
      <div v-if="showAddMembershipModal" class="fixed inset-0 bg-gray-500/75 overflow-y-auto h-full w-full z-50" @click="closeAddMembershipModal">
        <div class="relative top-10 mx-auto p-5 border border-gray-50 w-11/12 md:w-3/4 lg:w-2/3 xl:w-1/2 shadow-lg rounded-md bg-white" @click.stop>
          <form @submit.prevent="addMembership">
            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
              <div class="mb-4">
                <h3 class="text-lg font-medium text-gray-900">
                  Neue Mitgliedschaft hinzufügen
                </h3>
              </div>

              <!-- Error message -->
              <div v-if="addMembershipForm.errors.membership" class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg">
                <div class="flex">
                  <AlertCircle class="h-5 w-5 text-red-400 flex-shrink-0" />
                  <p class="ml-3 text-sm text-red-800">{{ addMembershipForm.errors.membership }}</p>
                </div>
              </div>

              <MembershipFormSection
                v-model="membershipFormData"
                :membership-plans="membershipPlans"
                :errors="addMembershipForm.errors"
              />

              <!-- Guest access warning -->
              <GuestAccessRevokeWarning v-if="showGuestAccessWarning" class="mt-4" />
            </div>

            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
              <button
                type="submit"
                :disabled="addMembershipForm.processing || !membershipFormData.membership_plan_id"
                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {{ addMembershipForm.processing ? 'Wird erstellt...' : 'Mitgliedschaft erstellen' }}
              </button>
              <button
                type="button"
                @click="closeAddMembershipModal"
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
import { ref, computed, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import {
  Plus, AlertCircle, UserX, ChevronDown, Gift, EyeOff, Puzzle, FileText, CheckCircle, StopCircle
} from '@lucide/vue'
import { formatBillingCycle, formatCurrency, formatDate, formatDateForInput, todayInDisplayTimezone } from '@/utils/formatters'
import { cappedBillingDate, monthlyMembershipTotal, nextMonthlyBillingDate } from '@/utils/addons'
import { membershipStatusOption } from '@/utils/membershipStatus'
import { useBottomBarOffset } from '@/composables/useBottomBarOffset'
import MemberAddons from '@/Components/Members/MemberAddons.vue'
import MembershipFormSection from '@/Components/Members/MembershipFormSection.vue'
import MembershipCard from '@/Components/Members/MembershipCard.vue'
import GuestAccessRevokeWarning from '@/Components/Members/GuestAccessRevokeWarning.vue'

const props = defineProps({
  member: {
    type: Object,
    required: true
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
  resumingMembership: {
    type: [Number, null],
    default: null
  },
  revokingCancellation: {
    type: [Number, null],
    default: null
  },
  activatingMembership: {
    type: [Number, null],
    default: null
  },
  abortingMembership: {
    type: [Number, null],
    default: null
  },
  forcingMembershipStatus: {
    type: [Number, null],
    default: null
  },
  withdrawingMembership: {
    type: [Number, null],
    default: null
  },
  ignoringMembership: {
    type: [Number, null],
    default: null
  }
})

const emit = defineEmits(['activate', 'pause', 'resume', 'cancel', 'revoke-cancellation', 'abort', 'withdraw', 'force-status', 'ignore'])

// Local state
const showPastMemberships = ref(false)
const showAddMembershipModal = ref(false)
const showFreePeriodModal = ref(false)

// Free period form
const freePeriodForm = useForm({
  start_date: todayInDisplayTimezone(),
  end_date: formatDateForInput(new Date(Date.now() + 30 * 24 * 60 * 60 * 1000)), // +30 days
  linked_membership_id: null
})

// Add membership form
const addMembershipForm = useForm({
  member_id: props.member.id,
  membership_plan_id: null,
  start_date: todayInDisplayTimezone(),
  allow_past_start_date: false,
  billing_anchor_date: ''
})

const membershipFormData = ref({
  membership_plan_id: null,
  start_date: todayInDisplayTimezone(),
  allow_past_start_date: false,
  billing_anchor_date: ''
})

// Computed properties
const activeMemberships = computed(() => {
  if (!props.member.memberships) return []
  return props.member.memberships.filter(m =>
    m.status === 'active' || m.status === 'pending' || m.status === 'paused'
  )
})

const pastMemberships = computed(() => {
  if (!props.member.memberships) return []
  return props.member.memberships.filter(m =>
    m.status === 'cancelled' || m.status === 'expired' || m.status === 'withdrawn'
  )
})

// A standing guest access grants unlimited entry, so adding any membership limits
// the access to that period and revokes the guest access on the server.
const showGuestAccessWarning = computed(() =>
  Boolean(props.member.guest_access) && activeMemberships.value.length === 0
)

// Memberships that can be linked to a free period: only real contracts, never
// another free period
const linkableMemberships = computed(() => {
  if (!props.member.memberships) return []
  return props.member.memberships.filter(m =>
    (m.status === 'active' || m.status === 'pending') && !m.linked_free_membership_id && !m.is_free_trial
  )
})

// Displayable memberships - groups linked free periods with their memberships
const displayableMemberships = computed(() => {
  if (!activeMemberships.value.length) return []

  const result = []
  const processedIds = new Set()

  // First pass: find all linked pairs (paid membership with linked_free_membership_id)
  for (const membership of activeMemberships.value) {
    if (membership.linked_free_membership_id) {
      const freeTrial = activeMemberships.value.find(m => m.id === membership.linked_free_membership_id)
      if (freeTrial) {
        result.push({
          ...freeTrial,
          linkedMembership: membership
        })
        processedIds.add(membership.id)
        processedIds.add(freeTrial.id)
      }
    }
  }

  // Second pass: add standalone memberships that weren't part of a linked pair
  for (const membership of activeMemberships.value) {
    if (!processedIds.has(membership.id)) {
      result.push(membership)
      processedIds.add(membership.id)
    }
  }

  return result
})

// Displayable past memberships - groups linked free periods with their memberships (same as active)
const displayablePastMemberships = computed(() => {
  if (!pastMemberships.value.length) return []

  const result = []
  const processedIds = new Set()

  // First pass: find all linked pairs (paid membership with linked_free_membership_id)
  for (const membership of pastMemberships.value) {
    if (membership.linked_free_membership_id) {
      const freeTrial = pastMemberships.value.find(m => m.id === membership.linked_free_membership_id)
      if (freeTrial) {
        result.push({
          ...freeTrial,
          linkedMembership: membership
        })
        processedIds.add(membership.id)
        processedIds.add(freeTrial.id)
      }
    }
  }

  // Second pass: add standalone memberships that weren't part of a linked pair
  for (const membership of pastMemberships.value) {
    if (!processedIds.has(membership.id)) {
      result.push(membership)
      processedIds.add(membership.id)
    }
  }

  return result
})

// Contracts shown as tiles: each paid membership together with the free period
// linked in front of it, if any.
const membershipEntries = computed(() =>
  displayableMemberships.value
    .map(entry => entry.linkedMembership
      ? { membership: entry.linkedMembership, freeTrial: entry }
      : { membership: entry, freeTrial: null })
    .filter(entry => !entry.membership.is_free_trial)
)

// Past contracts, grouped the same way as the current ones
const pastEntries = computed(() =>
  displayablePastMemberships.value.map(entry => entry.linkedMembership
    ? { membership: entry.linkedMembership, freeTrial: entry }
    : { membership: entry, freeTrial: null })
)

// Free periods without a membership (e.g. a trial workout)
const standaloneFreeTrials = computed(() =>
  displayableMemberships.value.filter(entry => !entry.linkedMembership && entry.is_free_trial)
)

const standaloneFreeTrialEntries = computed(() =>
  standaloneFreeTrials.value.map(freeTrial => ({ membership: freeTrial, freeTrial: null }))
)

const selectedMembershipId = ref(null)
const memberAddons = ref(null)

// Falls back to the first current contract when nothing (or a contract that is gone) is selected.
const selectedEntry = computed(() =>
  [...membershipEntries.value, ...standaloneFreeTrialEntries.value, ...pastEntries.value]
    .find(entry => entry.membership.id === selectedMembershipId.value)
    || membershipEntries.value[0]
    || standaloneFreeTrialEntries.value[0]
    || null
)

const selectedIsPast = computed(() => pastEntries.value.includes(selectedEntry.value))

// The monthly total is pinned to the bottom of the screen on mobile for current contracts.
const showMobileTotal = computed(() =>
  Boolean(selectedEntry.value)
    && !selectedEntry.value.membership.is_free_trial
    && !selectedIsPast.value
    && selectedTotal.value !== null
)

// Keeps floating launchers (Chatwoot) above the pinned total
const mobileTotalBar = ref(null)
useBottomBarOffset(mobileTotalBar)

const selectedBookableAddons = computed(() =>
  (selectedEntry.value && props.bookableAddons?.[selectedEntry.value.membership.id]) || []
)

const selectedTotal = computed(() =>
  selectedEntry.value ? monthlyMembershipTotal(selectedEntry.value.membership) : null
)

// The membership fee shares its due dates with recurring add-ons, so the same
// anchor applies; only monthly contracts that are running have one.
const selectedNextBillingAt = computed(() => {
  const membership = selectedEntry.value?.membership
  if (!membership || membership.status !== 'active' || selectedTotal.value === null) return null
  return cappedBillingDate(nextMonthlyBillingDate(membership.start_date), membership.end_date)
})

const isLinkable = (membership) => linkableMemberships.value.some(m => m.id === membership.id)

// A free period can only be prepended to a membership that has not started yet
const startsInFuture = (membership) => {
  if (!membership?.start_date) return false
  return formatDateForInput(membership.start_date) > todayInDisplayTimezone()
}

const canPrependFreePeriod = (membership) => isLinkable(membership) && startsInFuture(membership)

const formatTerm = (membership) =>
  `${formatDate(membership.start_date)} – ${membership.end_date ? formatDate(membership.end_date) : (membership.cancellation_date ? formatDate(membership.cancellation_date) : 'unbefristet')}`

const addonCount = (membership) => (membership.addons || []).length

const addonCountLabel = (membership) => {
  const count = addonCount(membership)
  if (count === 0) return 'Keine Add-ons'
  return count === 1 ? '1 Add-on' : `${count} Add-ons`
}

// Monthly total where it can be summed up, otherwise the plan fee alone.
const entryTotal = (membership) =>
  monthlyMembershipTotal(membership) ?? (membership.membership_plan?.price || 0)

// e.g. "Tarif 34,95 € + 2 Add-ons 12,12 €" — only add-ons that are still billed count.
const totalBreakdown = (membership) => {
  const running = (membership.addons || [])
    .filter(addon => addon.billing_type === 'recurring' && !addon.pivot?.cancelled_at)
  const tariff = `Tarif ${formatCurrency(membership.membership_plan?.price || 0)}`
  if (running.length === 0) return tariff
  const addonsTotal = running.reduce((sum, addon) => sum + (Number(addon.pivot?.price) || 0), 0)
  return `${tariff} + ${running.length === 1 ? '1 Add-on' : `${running.length} Add-ons`} ${formatCurrency(addonsTotal)}`
}

// Check if a membership is currently active (based on date)
const isCurrentlyActive = (membership) => {
  if (!membership) return false
  // YYYY-MM-DD strings compare lexicographically
  const today = todayInDisplayTimezone()
  const isAfterStart = today >= formatDateForInput(membership.start_date)
  const isBeforeEnd = !membership.end_date || today <= formatDateForInput(membership.end_date)

  return isAfterStart && isBeforeEnd && membership.status === 'active'
}

// Calculate free period duration in days
const freePeriodDays = computed(() => {
  if (!freePeriodForm.start_date || !freePeriodForm.end_date) return 0
  const start = new Date(freePeriodForm.start_date)
  const end = new Date(freePeriodForm.end_date)
  const diffTime = Math.abs(end - start)
  return Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1
})

// Watch membershipFormData and sync to addMembershipForm
watch(membershipFormData, (newVal) => {
  addMembershipForm.membership_plan_id = newVal.membership_plan_id
  addMembershipForm.start_date = newVal.start_date
  addMembershipForm.allow_past_start_date = newVal.allow_past_start_date
  addMembershipForm.billing_anchor_date = newVal.billing_anchor_date
}, { deep: true })

// Modal functions
const openAddMembershipModal = () => {
  membershipFormData.value = {
    membership_plan_id: null,
    start_date: todayInDisplayTimezone(),
    allow_past_start_date: false,
    billing_anchor_date: ''
  }
  addMembershipForm.reset()
  addMembershipForm.clearErrors()
  showAddMembershipModal.value = true
}

const closeAddMembershipModal = () => {
  showAddMembershipModal.value = false
  addMembershipForm.reset()
  addMembershipForm.clearErrors()
}

const addMembership = () => {
  addMembershipForm.post(route('members.memberships.store', props.member.id), {
    preserveScroll: true,
    onSuccess: () => {
      closeAddMembershipModal()
    }
  })
}

// Free period modal functions
const openFreePeriodModal = (linkedMembershipId = null) => {
  freePeriodForm.reset()
  freePeriodForm.clearErrors()
  const today = todayInDisplayTimezone()
  freePeriodForm.start_date = today
  // Default: last day of the current month (day 0 of the next month)
  const [year, month] = today.split('-').map(Number)
  freePeriodForm.end_date = formatDateForInput(new Date(Date.UTC(year, month, 0)))
  freePeriodForm.linked_membership_id = linkedMembershipId
  showFreePeriodModal.value = true
}

const closeFreePeriodModal = () => {
  showFreePeriodModal.value = false
  freePeriodForm.reset()
  freePeriodForm.clearErrors()
}

const addFreePeriod = () => {
  freePeriodForm.post(route('members.memberships.store-free-period', props.member.id), {
    preserveScroll: true,
    onSuccess: () => {
      closeFreePeriodModal()
    }
  })
}
</script>
