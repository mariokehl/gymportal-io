<template>
  <div>
    <!-- Actions Bar -->
    <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200 mb-4">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
          <div class="flex items-center">
            <input
              id="select-all"
              type="checkbox"
              :checked="isAllSelected"
              @change="toggleSelectAll"
              :disabled="batchExecutingPayments"
              class="h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500 disabled:opacity-50"
            />
            <label for="select-all" class="ml-2 text-sm font-medium text-gray-700">
              Alle auswählen
            </label>
          </div>

          <span v-if="selectedPayments.length > 0" class="text-sm text-gray-600">
            {{ selectedPayments.length }} von {{ payments.data.length }} ausgewählt
          </span>

          <!-- Batch Progress Indicator -->
          <div v-if="batchExecutingPayments" class="flex items-center text-sm text-indigo-600">
            <div class="w-4 h-4 border-2 border-indigo-600 border-t-transparent rounded-full animate-spin mr-2"></div>
            Zahlungen werden ausgeführt...
          </div>
        </div>

        <div class="flex items-center gap-2">
          <button
            v-if="selectedPayments.length > 0 && showCsvExport"
            @click="handleExport('csv')"
            :disabled="batchExecutingPayments"
            class="flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <Download class="w-4 h-4 mr-2" />
            CSV Export
          </button>

          <button
            v-if="selectedSepaPayments.length > 0 && showSepaExport"
            @click="handleExport('pain008')"
            :disabled="batchExecutingPayments"
            class="flex items-center px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-lg hover:bg-indigo-700 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <Download class="w-4 h-4 mr-2" />
            PAIN.008 Export ({{ selectedSepaPayments.length }})
          </button>
        </div>
      </div>
    </div>

    <!-- Payments Table -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left" v-if="showCheckboxes">
                <input
                  type="checkbox"
                  :checked="isAllSelected"
                  @change="toggleSelectAll"
                  class="h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500"
                />
              </th>
              <th
                v-for="column in visibleColumns"
                :key="column.key"
                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                :class="{
                  'cursor-pointer hover:bg-gray-100': column.sortable,
                  'text-right': column.align === 'right'
                }"
                @click="column.sortable && handleSort(column.key)"
              >
                <div class="flex items-center" :class="{ 'justify-end': column.align === 'right' }">
                  {{ column.label }}
                  <ArrowUpDown v-if="column.sortable" class="w-4 h-4 ml-1" />
                </div>
              </th>
              <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider" v-if="showActions">
                Aktionen
              </th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <template v-for="payment in payments.data" :key="payment.id">
            <tr
              class="hover:bg-gray-50"
              :class="{ 'opacity-60': executingPaymentId === payment.id }"
            >
              <td class="px-6 py-4 whitespace-nowrap" v-if="showCheckboxes">
                <input
                  type="checkbox"
                  :value="payment.id"
                  v-model="selectedPayments"
                  :disabled="executingPaymentId === payment.id || batchExecutingPayments"
                  class="h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500 disabled:opacity-50"
                />
              </td>
              <td
                v-for="column in visibleColumns"
                :key="column.key"
                class="px-6 py-4"
                :class="{
                  'whitespace-nowrap': column.nowrap,
                  'text-right': column.align === 'right'
                }"
              >
                <!-- ID Column -->
                <template v-if="column.key === 'id'">
                  <span class="text-sm font-medium text-gray-900">#{{ payment.id }}</span>
                </template>

                <!-- Date Column -->
                <template v-else-if="column.key === 'created_at'">
                  <span class="text-sm text-gray-900">{{ formatDate(payment.created_at) }}</span>
                </template>

                <!-- Member Column -->
                <template v-else-if="column.key === 'member'">
                  <div class="flex items-center">
                    <div class="w-8 h-8 shrink-0 bg-indigo-500 rounded-full text-white flex items-center justify-center text-xs font-semibold mr-3">
                      {{ getMemberInitials(payment.member) }}
                    </div>
                    <div class="min-w-0 max-w-[250px]">
                      <div class="text-sm font-medium text-gray-900 truncate">
                        {{ payment.member?.first_name }} {{ payment.member?.last_name }}
                      </div>
                      <div class="text-sm text-gray-500 truncate">
                        {{ payment.member?.email }}
                      </div>
                    </div>
                  </div>
                </template>

                <!-- Description Column -->
                <template v-else-if="column.key === 'description'">
                  <div class="text-sm text-gray-900 text-nowrap">{{ payment.description }}</div>
                  <div v-if="payment.transaction_id" class="text-sm text-gray-500 text-nowrap">
                    Tx: {{ payment.transaction_id }}
                  </div>
                  <div v-else-if="payment.mollie_payment_id" class="text-sm text-gray-500 text-nowrap">
                    Tx: {{ payment.mollie_payment_id }}
                  </div>
                </template>

                <!-- Amount Column -->
                <template v-else-if="column.key === 'amount'">
                  <span class="text-sm font-semibold">{{ formatCurrency(payment.amount) }}</span>
                </template>

                <!-- Status Column -->
                <template v-else-if="column.key === 'status'">
                  <div class="flex items-center">
                    <span
                      :class="getStatusClasses(payment.status_color)"
                      class="inline-flex px-2 py-1 text-xs font-semibold rounded-full"
                    >
                      {{ payment.status_text }}
                    </span>
                    <!-- Chargeback/Refund Badge -->
                    <button
                      v-if="getChargebackRefundCount(payment) > 0"
                      @click.stop="toggleRowExpansion(payment.id)"
                      class="ml-2 inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full bg-orange-100 text-orange-800 hover:bg-orange-200 transition-colors cursor-pointer"
                      :title="isRowExpanded(payment.id) ? 'Details ausblenden' : 'Details anzeigen'"
                    >
                      <component
                        :is="isRowExpanded(payment.id) ? ChevronDown : ChevronRight"
                        class="w-3 h-3 mr-1"
                      />
                      {{ getChargebackRefundCount(payment) }}
                    </button>
                    <div
                      v-if="executingPaymentId === payment.id"
                      class="ml-2 w-4 h-4 border-2 border-indigo-600 border-t-transparent rounded-full animate-spin"
                      title="Zahlung wird ausgeführt..."
                    ></div>
                  </div>
                </template>

                <!-- Payment Method Column -->
                <template v-else-if="column.key === 'payment_method'">
                  <span class="text-sm text-gray-900">{{ payment.payment_method_text }}</span>
                </template>

                <!-- Due Date Column -->
                <template v-else-if="column.key === 'due_date'">
                  <div v-if="payment.due_date" class="text-sm text-gray-900">{{ formatDate(payment.due_date) }}</div>
                  <span v-else class="text-sm text-gray-400">-</span>
                  <div v-if="payment.execution_date" class="text-sm text-gray-500">{{ formatDate(payment.execution_date) }}</div>
                </template>

                <!-- Custom Column Slot -->
                <template v-else>
                  <slot :name="`column-${column.key}`" :payment="payment" :value="payment[column.key]">
                    {{ payment[column.key] }}
                  </slot>
                </template>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium" v-if="showActions">
                <div class="flex items-center justify-end space-x-2">
                  <button
                    v-if="showViewDetails"
                    @click="viewPayment(payment)"
                    class="text-gray-700 hover:text-gray-900"
                    title="Details anzeigen"
                  >
                    <Eye class="w-4 h-4" />
                  </button>
                  <button
                    v-if="payment.status === 'pending' && showMarkAsPaid"
                    @click="markAsPaid(payment)"
                    class="text-green-600 hover:text-green-900"
                    title="Als bezahlt markieren"
                  >
                    <CheckCircle class="w-4 h-4" />
                  </button>
                  <button
                    v-if="payment.status === 'pending' && showCancelPayment"
                    @click="cancelPayment(payment)"
                    class="text-red-600 hover:text-red-900"
                    title="Zahlung abbrechen"
                  >
                    <XCircle class="w-4 h-4" />
                  </button>
                  <slot name="actions" :payment="payment" :is-executing="executingPaymentId === payment.id"></slot>
                </div>
              </td>
            </tr>

            <!-- Expanded Row for Chargebacks/Refunds -->
            <tr v-if="isRowExpanded(payment.id) && getChargebackRefundCount(payment) > 0">
              <td :colspan="visibleColumns.length + (showCheckboxes ? 1 : 0) + (showActions ? 1 : 0)" class="px-6 py-4 bg-gray-50">
                <div class="space-y-4">
                  <!-- Chargebacks -->
                  <div v-if="payment.chargebacks?.length > 0">
                    <h4 class="text-sm font-semibold text-gray-900 flex items-center mb-2">
                      <AlertTriangle class="w-4 h-4 mr-2 text-red-500" />
                      Rückbuchungen ({{ payment.chargebacks.length }})
                    </h4>
                    <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
                      <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-100">
                          <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Datum</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Betrag</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Grund</th>
                          </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                          <tr v-for="chargeback in payment.chargebacks" :key="chargeback.id" class="hover:bg-gray-50">
                            <td class="px-4 py-2 text-sm text-gray-900 font-mono">{{ chargeback.mollie_chargeback_id }}</td>
                            <td class="px-4 py-2 text-sm text-gray-900">{{ formatDate(chargeback.chargeback_date) }}</td>
                            <td class="px-4 py-2 text-sm font-semibold text-red-600">-{{ formatCurrency(chargeback.amount) }}</td>
                            <td class="px-4 py-2">
                              <span
                                :class="getChargebackStatusClasses(chargeback.status)"
                                class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full"
                              >
                                {{ chargeback.status_text }}
                              </span>
                            </td>
                            <td class="px-4 py-2 text-sm text-gray-600">{{ chargeback.reason || '-' }}</td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                  </div>

                  <!-- Refunds -->
                  <div v-if="payment.refunds?.length > 0">
                    <h4 class="text-sm font-semibold text-gray-900 flex items-center mb-2">
                      <RotateCcw class="w-4 h-4 mr-2 text-blue-500" />
                      Erstattungen ({{ payment.refunds.length }})
                    </h4>
                    <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
                      <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-100">
                          <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Datum</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Betrag</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Beschreibung</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Grund</th>
                          </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                          <tr v-for="refund in payment.refunds" :key="refund.id" class="hover:bg-gray-50">
                            <td class="px-4 py-2 text-sm text-gray-900 font-mono">{{ refund.mollie_refund_id }}</td>
                            <td class="px-4 py-2 text-sm text-gray-900">{{ formatDate(refund.created_at) }}</td>
                            <td class="px-4 py-2 text-sm font-semibold text-blue-600">-{{ formatCurrency(refund.amount) }}</td>
                            <td class="px-4 py-2">
                              <span
                                :class="getRefundStatusClasses(refund.status)"
                                class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full"
                              >
                                {{ refund.status_text }}
                              </span>
                            </td>
                            <td class="px-4 py-2 text-sm text-gray-600">{{ refund.description || '-' }}</td>
                            <td class="px-4 py-2 text-sm text-gray-600">{{ refund.reason || '-' }}</td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>
              </td>
            </tr>
            </template>
          </tbody>
        </table>
      </div>

      <!-- Pagination with Pagination Component -->
      <Pagination
        v-if="showPagination"
        :data="payments"
        item-label="Zahlungen"
        :is-loading="isProcessing"
        @navigate="handlePaginationEvent"
      />
    </div>

    <!-- Payment Detail Modal -->
    <div
      v-if="showPaymentModal"
      class="fixed inset-0 bg-gray-500/75 overflow-y-auto h-full w-full z-50"
      @click="closePaymentModal"
    >
      <div
        class="relative top-20 mx-auto p-5 border border-gray-50 w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white"
        @click.stop
      >
        <!-- Header -->
        <div class="flex items-start justify-between mb-5">
          <h3 class="text-lg font-medium text-gray-900">
            Zahlungsdetails #{{ selectedPayment?.id }}
          </h3>
          <div class="flex items-center gap-2">
            <button
              v-if="selectedPayment?.status === 'pending' && showMarkAsPaid"
              @click="markAsPaidFromModal"
              class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-green-700 bg-green-50 border border-green-200 rounded-lg hover:bg-green-100 transition-colors"
              title="Als bezahlt markieren"
            >
              <CheckCircle class="w-4 h-4" />
              Bezahlt
            </button>
            <button
              v-if="selectedPayment?.status === 'pending' && showCancelPayment"
              @click="cancelPaymentFromModal"
              class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-red-700 bg-red-50 border border-red-200 rounded-lg hover:bg-red-100 transition-colors"
              title="Zahlung abbrechen"
            >
              <Ban class="w-4 h-4" />
              Abbrechen
            </button>
            <button
              @click="closePaymentModal"
              class="ml-1 p-1.5 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100 transition-colors"
            >
              <X class="w-5 h-5" />
            </button>
          </div>
        </div>

        <div v-if="selectedPayment" class="space-y-4">
          <!-- Kerndaten -->
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-500">Betrag</label>
              <div class="mt-1 flex items-center gap-2">
                <span class="text-sm text-gray-900 font-semibold">{{ formatCurrency(selectedPayment.amount) }}</span>
                <span
                  :class="getStatusClasses(selectedPayment.status_color)"
                  class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full"
                >
                  {{ selectedPayment.status_text }}
                </span>
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-500">Zahlungsart</label>
              <p class="mt-1 text-sm text-gray-900">{{ selectedPayment.payment_method_text }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-500">Erstellt am</label>
              <p class="mt-1 text-sm text-gray-900">{{ formatDateTime(selectedPayment.created_at) }}</p>
            </div>
            <div v-if="selectedPayment.execution_date">
              <label class="block text-sm font-medium text-gray-500">Ausführungsdatum</label>
              <p class="mt-1 text-sm text-gray-900">{{ formatDate(selectedPayment.execution_date) }}</p>
            </div>
            <div v-if="selectedPayment.due_date">
              <label class="block text-sm font-medium text-gray-500">Fälligkeitsdatum</label>
              <p class="mt-1 text-sm text-gray-900">{{ formatDate(selectedPayment.due_date) }}</p>
            </div>
            <div v-if="selectedPayment.paid_date">
              <label class="block text-sm font-medium text-gray-500">Bezahlt am</label>
              <p class="mt-1 text-sm text-gray-900">{{ formatDateTime(selectedPayment.paid_date) }}</p>
            </div>
            <div v-if="selectedPayment.canceled_at">
              <label class="block text-sm font-medium text-gray-500">Abgebrochen am</label>
              <p class="mt-1 text-sm text-gray-900">{{ formatDateTime(selectedPayment.canceled_at) }}</p>
            </div>
          </div>

          <div v-if="selectedPayment.member">
            <label class="block text-sm font-medium text-gray-700">Mitglied</label>
            <div class="mt-1 flex items-center">
              <div class="w-8 h-8 bg-indigo-500 rounded-full text-white flex items-center justify-center text-xs font-semibold mr-3">
                {{ getMemberInitials(selectedPayment.member) }}
              </div>
              <div>
                <p class="text-sm font-medium text-gray-900">
                  {{ selectedPayment.member.first_name }} {{ selectedPayment.member.last_name }}
                </p>
                <p class="text-sm text-gray-500">{{ selectedPayment.member.email }}</p>
              </div>
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-500">Beschreibung</label>
            <p class="mt-1 text-sm text-gray-900">{{ selectedPayment.description }}</p>
          </div>

          <div v-if="selectedPayment.transaction_id">
            <label class="block text-sm font-medium text-gray-500">Transaktions-ID</label>
            <p class="mt-1 text-sm text-gray-900 font-mono">{{ selectedPayment.transaction_id }}</p>
          </div>

          <div v-if="selectedPayment.mollie_payment_id">
            <label class="block text-sm font-medium text-gray-500">Zahlungs-ID (Mollie)</label>
            <p class="mt-1 text-sm text-gray-900 font-mono">{{ selectedPayment.mollie_payment_id }}</p>
          </div>

          <div>
            <div class="flex items-center justify-between">
              <label class="block text-sm font-medium text-gray-500">Notizen</label>
              <button
                v-if="!editingNotes"
                @click="startEditingNotes"
                class="text-gray-400 hover:text-indigo-600 transition-colors"
                title="Notizen bearbeiten"
              >
                <Pencil class="w-3.5 h-3.5" />
              </button>
            </div>
            <div v-if="editingNotes" class="mt-1">
              <textarea
                ref="notesTextarea"
                v-model="notesForm"
                rows="3"
                class="w-full text-sm border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                placeholder="Notizen hinzufügen..."
                @keydown.ctrl.enter="saveNotes"
                @keydown.meta.enter="saveNotes"
                @keydown.escape="cancelEditingNotes"
              ></textarea>
              <div class="flex items-center justify-end gap-2 mt-1.5">
                <button
                  @click="cancelEditingNotes"
                  :disabled="savingNotes"
                  class="inline-flex items-center px-2.5 py-1 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50"
                >
                  Abbrechen
                </button>
                <button
                  @click="saveNotes"
                  :disabled="savingNotes"
                  class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium text-white bg-indigo-600 border border-transparent rounded-md hover:bg-indigo-700 disabled:opacity-50"
                >
                  <Loader2 v-if="savingNotes" class="w-3 h-3 animate-spin" />
                  <Check v-else class="w-3 h-3" />
                  Speichern
                </button>
              </div>
            </div>
            <p v-else-if="selectedPayment.notes" class="mt-1 text-sm text-gray-900 whitespace-pre-line">{{ selectedPayment.notes }}</p>
            <p v-else class="mt-1 text-sm text-gray-400 italic cursor-pointer hover:text-indigo-600" @click="startEditingNotes">Notiz hinzufügen...</p>
          </div>

          <!-- Mollie Zahlungslink -->
          <div
            v-if="selectedPayment.checkout_url || (selectedPayment.status === 'pending' && !selectedPayment.mollie_payment_id && page.props.mollie_configured)"
            class="border-t border-gray-200 pt-4"
          >
            <div v-if="selectedPayment.checkout_url" class="flex items-center justify-between gap-3 bg-indigo-50 border border-indigo-200 rounded-lg px-4 py-3">
              <div class="flex items-center gap-2 min-w-0">
                <Link2 class="w-4 h-4 text-indigo-500 shrink-0" />
                <span class="text-sm font-medium text-indigo-900 truncate">{{ selectedPayment.checkout_url }}</span>
              </div>
              <div class="flex items-center gap-1.5 shrink-0">
                <button
                  @click="copyPaymentLink"
                  class="inline-flex items-center gap-1.5 px-2.5 py-1.5 text-xs font-medium text-indigo-700 bg-white border border-indigo-300 rounded-md hover:bg-indigo-50 transition-colors"
                >
                  <component :is="paymentLinkCopied ? ClipboardCheck : Clipboard" class="w-3.5 h-3.5" />
                  {{ paymentLinkCopied ? 'Kopiert!' : 'Kopieren' }}
                </button>
                <a
                  :href="selectedPayment.checkout_url"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="inline-flex items-center px-2.5 py-1.5 text-xs font-medium text-indigo-700 bg-white border border-indigo-300 rounded-md hover:bg-indigo-50 transition-colors"
                  title="Link extern öffnen"
                >
                  <ExternalLink class="w-3.5 h-3.5" />
                </a>
              </div>
            </div>
            <button
              v-else
              @click="createPaymentLink"
              :disabled="creatingPaymentLink"
              class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-indigo-700 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <template v-if="creatingPaymentLink">
                <div class="w-4 h-4 border-2 border-indigo-600 border-t-transparent rounded-full animate-spin"></div>
                Wird erstellt...
              </template>
              <template v-else>
                <Link2 class="w-4 h-4" />
                Mollie Zahlungslink erstellen
              </template>
            </button>
          </div>

          <!-- Custom detail fields slot -->
          <slot name="payment-details" :payment="selectedPayment"></slot>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, nextTick } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import axios from 'axios'
import Pagination from '@/Components/Pagination.vue'
import {
  Download,
  ArrowUpDown,
  Eye,
  CheckCircle,
  XCircle,
  X,
  ChevronDown,
  ChevronRight,
  RotateCcw,
  AlertTriangle,
  Clipboard,
  ClipboardCheck,
  ExternalLink,
  Ban,
  Link2,
  Pencil,
  Check,
  Loader2
} from 'lucide-vue-next'
import { formatCurrency, formatDate, formatDateTime } from '@/utils/formatters'

// Props
const props = defineProps({
  payments: {
    type: Object,
    required: true
  },
  executingPaymentId: {
    type: [Number, String, null],
    default: null
  },
  batchExecutingPayments: {
    type: Boolean,
    default: false
  },
  columns: {
    type: Array,
    default: () => [
      { key: 'id', label: 'ID', sortable: true, nowrap: true },
      { key: 'created_at', label: 'Datum', sortable: true, nowrap: true },
      { key: 'member', label: 'Mitglied', sortable: false },
      { key: 'description', label: 'Beschreibung', sortable: false },
      { key: 'amount', label: 'Betrag', sortable: true, nowrap: true },
      { key: 'status', label: 'Status', sortable: false, nowrap: true },
      { key: 'payment_method', label: 'Zahlungsart', sortable: false, nowrap: true },
      { key: 'due_date', label: 'Fälligkeitsdatum', sortable: false, nowrap: true }
    ]
  },
  showCheckboxes: {
    type: Boolean,
    default: true
  },
  showActions: {
    type: Boolean,
    default: true
  },
  showViewDetails: {
    type: Boolean,
    default: true
  },
  showMarkAsPaid: {
    type: Boolean,
    default: true
  },
  showCancelPayment: {
    type: Boolean,
    default: true
  },
  showPagination: {
    type: Boolean,
    default: true
  },
  showCsvExport: {
    type: Boolean,
    default: true
  },
  showSepaExport: {
    type: Boolean,
    default: true
  },
  selectedIds: {
    type: Array,
    default: () => []
  },
  markPaidRoute: {
    type: String,
    default: 'payments.mark-paid'
  },
  cancelRoute: {
    type: String,
    default: 'payments.cancel'
  },
  exportRoute: {
    type: String,
    default: 'finances.export'
  },
  confirmMarkPaidMessage: {
    type: String,
    default: 'Möchten Sie diese Zahlung als bezahlt markieren?'
  },
  confirmCancelMessage: {
    type: String,
    default: 'Möchten Sie diese Zahlung wirklich abbrechen? Diese Aktion kann nicht rückgängig gemacht werden.'
  }
})

// Emits
const emit = defineEmits([
  'sort',
  'export',
  'paginate',
  'update:selectedIds',
  'payment-viewed',
  'payment-marked-paid',
  'payment-canceled',
  'before-mark-paid',
  'before-cancel',
  'before-export'
])

// Local state
const selectedPayments = ref(props.selectedIds)
const showPaymentModal = ref(false)
const selectedPayment = ref(null)
const page = usePage()
const isProcessing = ref(false)
const expandedRows = ref(new Set())
const creatingPaymentLink = ref(false)
const paymentLinkCopied = ref(false)
const editingNotes = ref(false)
const notesForm = ref('')
const savingNotes = ref(false)
const notesTextarea = ref(null)

// Watch for external changes to selectedIds
watch(() => props.selectedIds, (newVal) => {
  selectedPayments.value = newVal
})

// Watch for internal changes and emit them
watch(selectedPayments, (newVal) => {
  emit('update:selectedIds', newVal)
})

// Computed
const visibleColumns = computed(() => {
  return props.columns.filter(col => col.visible !== false)
})

const isAllSelected = computed(() => {
  return props.payments.data.length > 0 && selectedPayments.value.length === props.payments.data.length
})

const selectedSepaPayments = computed(() => {
  return props.payments.data.filter(payment =>
    selectedPayments.value.includes(payment.id) && payment.payment_method === 'sepa_direct_debit'
  )
})

// Methods
const toggleSelectAll = () => {
  if (isAllSelected.value) {
    selectedPayments.value = []
  } else {
    selectedPayments.value = props.payments.data.map(payment => payment.id)
  }
}

const handleSort = (column) => {
  emit('sort', column)
}

const handleExport = async (type) => {
  if (selectedPayments.value.length === 0) {
    alert('Bitte wählen Sie mindestens eine Zahlung aus.')
    return
  }

  // Allow parent to handle or prevent export
  const beforeExportEvent = {
    type,
    paymentIds: selectedPayments.value,
    preventDefault: false
  }

  emit('before-export', beforeExportEvent)

  if (!beforeExportEvent.preventDefault) {
    // If parent doesn't handle it, use default behavior
    if (props.exportRoute && window.route) {
      try {
        // Use axios to download the file as a blob
        const response = await axios.post(route(props.exportRoute), {
          payment_ids: selectedPayments.value,
          export_type: type
        }, {
          responseType: 'blob'
        })

        // Extract filename from Content-Disposition header or create a default one
        const contentDisposition = response.headers['content-disposition']
        let filename = `export_${type}_${new Date().toISOString().slice(0, 10)}`

        if (contentDisposition) {
          const filenameMatch = contentDisposition.match(/filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/)
          if (filenameMatch && filenameMatch[1]) {
            filename = filenameMatch[1].replace(/['"]/g, '')
          }
        } else {
          // Add appropriate file extension based on type
          if (type === 'csv') filename += '.csv'
          else if (type === 'pain008') filename += '.xml'
          else if (type === 'pdf') filename += '.pdf'
        }

        // Create blob URL and trigger download
        const blob = new Blob([response.data], { type: response.headers['content-type'] })
        const url = window.URL.createObjectURL(blob)
        const link = document.createElement('a')
        link.href = url
        link.download = filename
        document.body.appendChild(link)
        link.click()

        // Cleanup
        document.body.removeChild(link)
        window.URL.revokeObjectURL(url)
      } catch (error) {
        console.error('Export failed:', error)
        alert('Export fehlgeschlagen. Bitte versuchen Sie es erneut.')
      }
    } else {
      // Emit for parent to handle
      emit('export', {
        type,
        paymentIds: selectedPayments.value
      })
    }
  }
}

const handlePaginationEvent = (event) => {
  // Handle loading states from Pagination component
  if (event.type === 'start') {
    isProcessing.value = true
  } else if (event.type === 'finish') {
    isProcessing.value = false
  }

  // Emit event for parent component if needed
  emit('paginate', event)
}

const viewPayment = (payment) => {
  selectedPayment.value = payment
  showPaymentModal.value = true
  emit('payment-viewed', payment)
}

const closePaymentModal = () => {
  showPaymentModal.value = false
  selectedPayment.value = null
  editingNotes.value = false
  notesForm.value = ''
}

const markAsPaid = async (payment) => {
  if (confirm(props.confirmMarkPaidMessage)) {
    await performMarkAsPaid(payment)
  }
}

const markAsPaidFromModal = async () => {
  if (selectedPayment.value && confirm(props.confirmMarkPaidMessage)) {
    await performMarkAsPaid(selectedPayment.value)
    closePaymentModal()
  }
}

const performMarkAsPaid = async (payment) => {
  // Allow parent to handle or prevent marking as paid
  const beforeMarkPaidEvent = {
    payment,
    preventDefault: false
  }

  emit('before-mark-paid', beforeMarkPaidEvent)

  if (!beforeMarkPaidEvent.preventDefault) {
    // If parent doesn't handle it, use default behavior
    if (props.markPaidRoute && window.route && router) {
      router.patch(route(props.markPaidRoute, payment.id), {}, {
        onSuccess: () => {
          emit('payment-marked-paid', payment)
        }
      })
    } else {
      // Emit for parent to handle
      emit('payment-marked-paid', payment)
    }
  }
}

const cancelPayment = async (payment) => {
  if (confirm(props.confirmCancelMessage)) {
    await performCancelPayment(payment)
  }
}

const cancelPaymentFromModal = async () => {
  if (selectedPayment.value && confirm(props.confirmCancelMessage)) {
    await performCancelPayment(selectedPayment.value)
    closePaymentModal()
  }
}

const performCancelPayment = async (payment) => {
  // Allow parent to handle or prevent cancellation
  const beforeCancelEvent = {
    payment,
    preventDefault: false
  }

  emit('before-cancel', beforeCancelEvent)

  if (!beforeCancelEvent.preventDefault) {
    // If parent doesn't handle it, use default behavior
    if (props.cancelRoute && window.route && router) {
      router.delete(route(props.cancelRoute, payment.id), {
        onSuccess: () => {
          emit('payment-canceled', payment)
        }
      })
    } else {
      // Emit for parent to handle
      emit('payment-canceled', payment)
    }
  }
}

const createPaymentLink = async () => {
  if (!selectedPayment.value || creatingPaymentLink.value) return

  creatingPaymentLink.value = true
  try {
    const response = await axios.post(route('payments.create-payment-link', selectedPayment.value.id))
    selectedPayment.value.checkout_url = response.data.checkout_url
    selectedPayment.value.mollie_payment_id = response.data.mollie_payment_id
    selectedPayment.value.payment_method = response.data.payment_method
    selectedPayment.value.payment_method_text = response.data.payment_method_text
  } catch (error) {
    const message = error.response?.data?.error || 'Zahlungslink konnte nicht erstellt werden.'
    alert(message)
  } finally {
    creatingPaymentLink.value = false
  }
}

const startEditingNotes = () => {
  notesForm.value = selectedPayment.value.notes || ''
  editingNotes.value = true
  nextTick(() => {
    notesTextarea.value?.focus()
  })
}

const cancelEditingNotes = () => {
  editingNotes.value = false
  notesForm.value = ''
}

const saveNotes = async () => {
  savingNotes.value = true
  try {
    const response = await axios.patch(route('payments.update-notes', selectedPayment.value.id), {
      notes: notesForm.value || null,
    })
    selectedPayment.value.notes = response.data.notes
    const payment = props.payments.data.find(p => p.id === selectedPayment.value.id)
    if (payment) {
      payment.notes = response.data.notes
    }
    editingNotes.value = false
  } catch (error) {
    const message = error.response?.data?.message || 'Notizen konnten nicht gespeichert werden.'
    alert(message)
  } finally {
    savingNotes.value = false
  }
}

const copyPaymentLink = async () => {
  if (!selectedPayment.value?.checkout_url) return

  try {
    await navigator.clipboard.writeText(selectedPayment.value.checkout_url)
    paymentLinkCopied.value = true
    setTimeout(() => {
      paymentLinkCopied.value = false
    }, 2000)
  } catch {
    // Fallback for older browsers
    const textArea = document.createElement('textarea')
    textArea.value = selectedPayment.value.checkout_url
    document.body.appendChild(textArea)
    textArea.select()
    document.execCommand('copy')
    document.body.removeChild(textArea)
    paymentLinkCopied.value = true
    setTimeout(() => {
      paymentLinkCopied.value = false
    }, 2000)
  }
}

// Utility functions
const getMemberInitials = (member) => {
  if (!member) return '??'
  const first = member.first_name?.charAt(0) || ''
  const last = member.last_name?.charAt(0) || ''
  return (first + last).toUpperCase()
}

const getStatusClasses = (color) => {
  const classes = {
    green: 'bg-green-100 text-green-800',
    yellow: 'bg-yellow-100 text-yellow-800',
    red: 'bg-red-100 text-red-800',
    blue: 'bg-indigo-100 text-indigo-800',
    gray: 'bg-gray-100 text-gray-800'
  }
  return classes[color] || classes.gray
}

const getChargebackRefundCount = (payment) => {
  const chargebackCount = payment.chargebacks?.length || 0
  const refundCount = payment.refunds?.length || 0
  return chargebackCount + refundCount
}

const toggleRowExpansion = (paymentId) => {
  if (expandedRows.value.has(paymentId)) {
    expandedRows.value.delete(paymentId)
  } else {
    expandedRows.value.add(paymentId)
  }
  // Trigger reactivity
  expandedRows.value = new Set(expandedRows.value)
}

const isRowExpanded = (paymentId) => {
  return expandedRows.value.has(paymentId)
}

const getChargebackStatusClasses = (status) => {
  const classes = {
    received: 'bg-red-100 text-red-800',
    accepted: 'bg-gray-100 text-gray-800',
    disputed: 'bg-yellow-100 text-yellow-800',
    reversed: 'bg-green-100 text-green-800'
  }
  return classes[status] || classes.received
}

const getRefundStatusClasses = (status) => {
  const classes = {
    pending: 'bg-yellow-100 text-yellow-800',
    processing: 'bg-blue-100 text-blue-800',
    refunded: 'bg-green-100 text-green-800',
    failed: 'bg-red-100 text-red-800'
  }
  return classes[status] || classes.pending
}

// Expose methods for parent component if needed
defineExpose({
  viewPayment,
  closePaymentModal,
  selectedPayments
})
</script>
