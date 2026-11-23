<template>
  <div class="space-y-6">
    <!-- Primary Access Methods -->
    <div class="flex flex-col gap-3">
      <h3 class="text-lg font-semibold text-ink">Primäre Zugangsmethoden</h3>

      <!-- QR Code Section -->
      <div class="rounded-lg border border-line bg-surface shadow-sm overflow-hidden">
        <div class="flex items-center gap-3 p-4">
          <div class="w-11 h-11 rounded-xl bg-brand-subtle text-brand-ink flex items-center justify-center flex-none">
            <QrCode class="w-[22px] h-[22px]" />
          </div>
          <div class="flex-1 min-w-0">
            <h4 class="font-semibold text-ink">QR-Code Zugang</h4>
            <p class="text-sm text-ink-muted mt-0.5">Digitaler Zugang über Mitglieder-App</p>
          </div>
          <label class="relative inline-flex items-center cursor-pointer flex-none">
            <input
              v-model="accessForm.qr_code_enabled"
              type="checkbox"
              role="switch"
              aria-label="QR-Code Zugang"
              class="sr-only peer"
              @change="updateAccessSettings"
            >
            <div class="w-11 h-6 bg-fill-strong peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-brand-line rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-surface after:border-line-strong after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-brand"></div>
          </label>
        </div>

        <!-- Status footer strip -->
        <div
          class="px-4 py-2.5 border-t text-sm flex items-center gap-2"
          :class="accessForm.qr_code_enabled ? 'bg-success-subtle border-success-line text-success' : 'bg-surface-muted border-line-subtle text-ink-muted'"
        >
          <CheckCircle v-if="accessForm.qr_code_enabled" class="w-4 h-4 flex-none" />
          <XCircle v-else class="w-4 h-4 flex-none" />
          <span v-if="accessForm.qr_code_enabled">QR-Code ist aktiviert — sichtbar in der Mitglieder-App (PWA)</span>
          <span v-else>QR-Code-Zugang ist deaktiviert</span>
        </div>

        <!-- QR actions -->
        <div v-if="accessForm.qr_code_enabled" class="flex flex-wrap gap-x-4 gap-y-2 px-4 py-3 border-t border-line-subtle">
          <button
            @click="invalidateQrCode"
            type="button"
            class="text-sm font-semibold text-danger hover:text-danger-strong flex items-center gap-1.5"
          >
            <XCircle class="w-4 h-4" />
            QR-Code invalidieren
          </button>
          <button
            @click="sendQrCodeToMember"
            type="button"
            class="text-sm font-semibold text-brand-ink hover:text-brand-ink-strong flex items-center gap-1.5"
          >
            <Mail class="w-4 h-4" />
            App-Link per E-Mail senden
          </button>
        </div>
      </div>

      <!-- NFC Tag Section -->
      <div class="rounded-lg border border-line bg-surface shadow-sm overflow-hidden">
        <div class="flex items-center gap-3 p-4">
          <div class="w-11 h-11 rounded-xl bg-purple-100 text-purple-600 flex items-center justify-center flex-none">
            <Nfc class="w-[22px] h-[22px]" />
          </div>
          <div class="flex-1 min-w-0">
            <h4 class="font-semibold text-ink">NFC-Tag Zugang</h4>
            <p class="text-sm text-ink-muted mt-0.5">Kontaktloser Zugang via NFC-Chip oder Karte</p>
          </div>
          <label class="relative inline-flex items-center cursor-pointer flex-none">
            <input
              v-model="accessForm.nfc_enabled"
              type="checkbox"
              role="switch"
              aria-label="NFC-Tag Zugang"
              class="sr-only peer"
              @change="updateAccessSettings"
            >
            <div class="w-11 h-6 bg-fill-strong peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-purple-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-surface after:border-line-strong after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-purple-600"></div>
          </label>
        </div>

        <!-- Status footer strip (shown only when NFC is off; when on the editor takes over) -->
        <div
          v-if="!accessForm.nfc_enabled"
          class="px-4 py-2.5 border-t border-line-subtle bg-surface-muted text-sm text-ink-muted flex items-center gap-2"
        >
          <XCircle class="w-4 h-4 flex-none" />
          NFC-Zugang ist deaktiviert
        </div>

        <!-- Registered tag summary (full-width status strip, analogous to the QR strip) -->
        <div
          v-else-if="accessForm.nfc_uid && !editingNfc && !isNfcScanning"
          class="px-4 py-2.5 border-t border-line-subtle bg-purple-50 flex flex-wrap items-center justify-between gap-x-4 gap-y-2"
        >
          <div class="flex items-center gap-2 min-w-0">
            <CheckCircle class="w-4 h-4 text-purple-600 flex-none" />
            <div class="min-w-0 text-sm">
              <span class="font-medium text-purple-900">NFC-Tag registriert</span>
              <span class="text-purple-700 font-mono ml-2">{{ formatNfcIdForDisplay(accessForm.nfc_uid) }}</span>
            </div>
          </div>
          <div class="flex items-center gap-4 flex-none">
            <button
              @click="startNfcEdit"
              type="button"
              class="text-sm font-semibold text-brand-ink hover:text-brand-ink-strong"
            >
              Bearbeiten
            </button>
            <button
              @click="startNfcScanning"
              type="button"
              class="text-sm font-semibold text-purple-600 hover:text-purple-800 flex items-center gap-1.5"
            >
              <Radio class="w-4 h-4" />
              Einlesen
            </button>
            <button
              @click="removeNfcTag"
              type="button"
              class="text-sm font-semibold text-danger hover:text-danger-strong"
            >
              Entfernen
            </button>
          </div>
        </div>

        <!-- NFC editor (no tag yet, or actively editing/scanning) -->
        <div v-else class="px-4 py-4 border-t border-line-subtle space-y-3">
          <!-- Input + actions (stacks on mobile, inline on larger screens) -->
          <div class="flex flex-col sm:flex-row sm:items-center gap-2">
            <input
              v-model="nfcInputValue"
              type="text"
              placeholder="NFC ID eingeben..."
              class="field flex-1 min-w-0 px-3 py-2 disabled:bg-surface-muted"
              :disabled="!editingNfc"
              @input="validateNfcInput"
            />
            <div class="flex gap-2">
              <template v-if="!editingNfc && !isNfcScanning">
                <button
                  @click="startNfcEdit"
                  type="button"
                  class="flex-1 sm:flex-none px-4 py-2 text-brand-ink border border-brand rounded-md hover:bg-brand-subtle text-sm font-medium"
                >
                  Bearbeiten
                </button>
                <button
                  @click="startNfcScanning"
                  type="button"
                  class="flex-1 sm:flex-none px-4 py-2 text-purple-600 border border-purple-600 rounded-md hover:bg-purple-50 flex items-center justify-center gap-2 text-sm font-medium"
                >
                  <Radio class="w-4 h-4" />
                  Einlesen
                </button>
              </template>
              <template v-else-if="isNfcScanning">
                <button
                  @click="stopNfcScanning"
                  type="button"
                  class="flex-1 sm:flex-none px-4 py-2 text-danger border border-danger rounded-md hover:bg-danger-subtle flex items-center justify-center gap-2 text-sm font-medium"
                >
                  <Loader2 class="w-4 h-4 animate-spin" />
                  Abbrechen
                </button>
              </template>
              <template v-else>
                <button
                  @click="saveNfcUid"
                  type="button"
                  :disabled="!isNfcValid"
                  class="flex-1 sm:flex-none px-4 py-2 bg-brand text-white rounded-md hover:bg-brand-strong disabled:bg-line-strong disabled:cursor-not-allowed text-sm font-medium"
                >
                  Speichern
                </button>
                <button
                  @click="cancelNfcEdit"
                  type="button"
                  class="flex-1 sm:flex-none px-4 py-2 text-ink-secondary border border-line-strong rounded-md hover:bg-surface-muted text-sm font-medium"
                >
                  Abbrechen
                </button>
              </template>
            </div>
          </div>

          <!-- Format hints -->
          <div v-if="editingNfc" class="text-xs text-ink-muted space-y-1">
            <p>Akzeptierte Formate:</p>
            <ul class="ml-4 space-y-0.5">
              <li>• Hex mit Trennzeichen: <code class="bg-fill px-1 rounded">04:A1:B2:C3</code> oder <code class="bg-fill px-1 rounded">04-A1-B2-C3</code></li>
              <li>• Hex mit Prefix: <code class="bg-fill px-1 rounded">0x04A1B2C3</code></li>
              <li>• Reines Hex: <code class="bg-fill px-1 rounded">04A1B2C3</code></li>
              <li>• Dezimal: <code class="bg-fill px-1 rounded">77856451</code></li>
            </ul>
          </div>

          <!-- Validation feedback -->
          <div v-if="editingNfc && nfcInputValue && !isNfcValid" class="flex items-center gap-2 text-sm text-danger">
            <XCircle class="w-4 h-4" />
            Ungültiges Format
          </div>
          <div v-if="editingNfc && normalizedNfcId && isNfcValid" class="flex items-center gap-2 text-sm text-success">
            <CheckCircle class="w-4 h-4" />
            Normalisiert: {{ formatNfcIdForDisplay(normalizedNfcId) }}
          </div>
        </div>

        <!-- Additional tags (only next to a registered primary tag) -->
        <div
          v-if="accessForm.nfc_enabled && accessForm.nfc_uid && !editingNfc && !isNfcScanning"
          class="px-4 py-3 border-t border-line-subtle space-y-3"
        >
          <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
            <span class="text-sm font-medium text-ink-secondary">Weitere NFC-Tags</span>
            <button
              v-if="!addingNfcTag"
              @click="startNfcTagAdd"
              type="button"
              class="text-sm font-semibold text-brand-ink hover:text-brand-ink-strong flex items-center gap-1.5"
            >
              <Plus class="w-4 h-4" />
              Hinzufügen
            </button>
          </div>

          <div v-if="additionalNfcTags.length > 0" class="flex flex-wrap gap-2">
            <span
              v-for="tag in additionalNfcTags"
              :key="tag.id"
              class="inline-flex items-center gap-1 pl-2.5 pr-1 py-1 rounded-full bg-purple-100 text-purple-800 text-xs font-mono"
            >
              {{ formatNfcIdForDisplay(tag.uid) }}
              <button
                @click="removeAdditionalNfcTag(tag)"
                type="button"
                :disabled="removingNfcTagId === tag.id"
                :aria-label="`NFC-Tag ${formatNfcIdForDisplay(tag.uid)} entfernen`"
                title="NFC-Tag entfernen"
                class="p-0.5 rounded-full text-purple-500 hover:text-purple-900 hover:bg-purple-200 disabled:opacity-50"
              >
                <Loader2 v-if="removingNfcTagId === tag.id" class="w-3.5 h-3.5 animate-spin" />
                <X v-else class="w-3.5 h-3.5" />
              </button>
            </span>
          </div>
          <p v-else-if="!addingNfcTag" class="text-xs text-ink-muted">Keine weiteren NFC-Tags hinterlegt.</p>

          <div v-if="addingNfcTag" class="space-y-2">
            <div class="flex flex-col sm:flex-row sm:items-center gap-2">
              <input
                ref="nfcTagInput"
                v-model="nfcTagInputValue"
                type="text"
                placeholder="Weitere NFC ID eingeben..."
                class="field flex-1 min-w-0 px-3 py-2"
                @keyup.enter="saveAdditionalNfcTag"
              />
              <div class="flex gap-2">
                <button
                  @click="saveAdditionalNfcTag"
                  type="button"
                  :disabled="!normalizedNfcTagId || nfcTagForm.processing"
                  class="flex-1 sm:flex-none px-4 py-2 bg-brand text-white rounded-md hover:bg-brand-strong disabled:bg-line-strong disabled:cursor-not-allowed text-sm font-medium"
                >
                  Speichern
                </button>
                <button
                  @click="cancelNfcTagAdd"
                  type="button"
                  class="flex-1 sm:flex-none px-4 py-2 text-ink-secondary border border-line-strong rounded-md hover:bg-surface-muted text-sm font-medium"
                >
                  Abbrechen
                </button>
              </div>
            </div>
            <div v-if="nfcTagInputValue && !normalizedNfcTagId" class="flex items-center gap-2 text-sm text-danger">
              <XCircle class="w-4 h-4" />
              Ungültiges Format
            </div>
            <div v-else-if="normalizedNfcTagId && !nfcTagForm.errors.uid" class="flex items-center gap-2 text-sm text-success">
              <CheckCircle class="w-4 h-4" />
              Normalisiert: {{ formatNfcIdForDisplay(normalizedNfcTagId) }}
            </div>
          </div>
          <p v-if="nfcTagForm.errors.uid" class="text-sm text-danger">{{ nfcTagForm.errors.uid }}</p>
        </div>
      </div>
    </div>

    <!-- Corporate fitness aggregator -->
    <MemberAggregatorCard :member="member" />

    <!-- Additional Services -->
    <div class="flex flex-col gap-3">
      <h3 class="text-lg font-semibold text-ink">Zusätzliche Services</h3>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <!-- Solarium -->
        <div class="rounded-lg border border-line bg-surface shadow-sm p-3.5">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-[10px] bg-warning-soft text-warning flex items-center justify-center flex-none">
              <Sun class="w-5 h-5" />
            </div>
            <div class="flex-1 min-w-0">
              <h5 class="font-semibold text-ink">Solarium</h5>
              <p class="text-sm text-ink-muted mt-0.5">Zugang zur Sonnenbank</p>
              <p v-if="accessForm.solarium_enabled" class="text-xs text-ink-muted mt-1">
                {{ accessForm.solarium_minutes || 0 }} Minuten verfügbar
              </p>
            </div>
            <label class="relative inline-flex items-center cursor-pointer flex-none">
              <input
                v-model="accessForm.solarium_enabled"
                type="checkbox"
                role="switch"
                aria-label="Solarium"
                class="sr-only peer"
                @change="updateAccessSettings"
              >
              <div class="w-11 h-6 bg-fill-strong peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-warning rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-surface after:border-line-strong after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-warning-solid"></div>
            </label>
          </div>
        </div>

        <!-- Vending Machine -->
        <div class="rounded-lg border border-line bg-surface shadow-sm p-3.5">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-[10px] bg-success-soft text-success flex items-center justify-center flex-none">
              <Package class="w-5 h-5" />
            </div>
            <div class="flex-1 min-w-0">
              <h5 class="font-semibold text-ink">Vending Machine</h5>
              <p class="text-sm text-ink-muted mt-0.5">Proteinriegel & Snacks</p>
              <p v-if="accessForm.vending_enabled" class="text-xs text-ink-muted mt-1">
                Guthaben: {{ formatCurrency(accessForm.vending_credit || 0) }}
              </p>
            </div>
            <label class="relative inline-flex items-center cursor-pointer flex-none">
              <input
                v-model="accessForm.vending_enabled"
                type="checkbox"
                role="switch"
                aria-label="Vending Machine"
                class="sr-only peer"
                @change="updateAccessSettings"
              >
              <div class="w-11 h-6 bg-fill-strong peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-success rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-surface after:border-line-strong after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-success-solid"></div>
            </label>
          </div>
        </div>

        <!-- Massage Chair -->
        <div class="rounded-lg border border-line bg-surface shadow-sm p-3.5">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-[10px] bg-info-soft text-info flex items-center justify-center flex-none">
              <Armchair class="w-5 h-5" />
            </div>
            <div class="flex-1 min-w-0">
              <h5 class="font-semibold text-ink">Massagestuhl</h5>
              <p class="text-sm text-ink-muted mt-0.5">Wellness & Entspannung</p>
              <p v-if="accessForm.massage_enabled" class="text-xs text-ink-muted mt-1">
                {{ accessForm.massage_sessions || 0 }} Sitzungen verfügbar
              </p>
            </div>
            <label class="relative inline-flex items-center cursor-pointer flex-none">
              <input
                v-model="accessForm.massage_enabled"
                type="checkbox"
                role="switch"
                aria-label="Massagestuhl"
                class="sr-only peer"
                @change="updateAccessSettings"
              >
              <div class="w-11 h-6 bg-fill-strong peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-info rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-surface after:border-line-strong after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-info-solid"></div>
            </label>
          </div>
        </div>

        <!-- Coffee Flat -->
        <div class="rounded-lg border border-line bg-surface shadow-sm p-3.5">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-[10px] bg-orange-100 text-orange-600 flex items-center justify-center flex-none">
              <Coffee class="w-5 h-5" />
            </div>
            <div class="flex-1 min-w-0">
              <h5 class="font-semibold text-ink">Kaffee-Flatrate</h5>
              <p class="text-sm text-ink-muted mt-0.5">Unbegrenzt Kaffee</p>
              <p v-if="accessForm.coffee_flat_enabled" class="text-xs text-ink-muted mt-1">
                Gültig bis: {{ accessForm.coffee_flat_expiry ? formatDate(accessForm.coffee_flat_expiry) : 'Unbegrenzt' }}
              </p>
            </div>
            <label class="relative inline-flex items-center cursor-pointer flex-none">
              <input
                v-model="accessForm.coffee_flat_enabled"
                type="checkbox"
                role="switch"
                aria-label="Kaffee-Flatrate"
                class="sr-only peer"
                @change="updateAccessSettings"
              >
              <div class="w-11 h-6 bg-fill-strong peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-orange-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-surface after:border-line-strong after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-orange-600"></div>
            </label>
          </div>
        </div>

        <!-- Tageskarte -->
        <div class="rounded-lg border border-line bg-surface shadow-sm p-3.5">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-[10px] bg-rose-100 text-rose-600 flex items-center justify-center flex-none">
              <Ticket class="w-5 h-5" />
            </div>
            <div class="flex-1 min-w-0">
              <h5 class="font-semibold text-ink">Tageskarte</h5>
              <p class="text-sm text-ink-muted mt-0.5">Eintritt für einen Tag</p>
              <div v-if="accessForm.day_pass_enabled" class="text-xs text-ink-muted mt-1">
                <div v-if="editingDayPassDate" class="flex items-center gap-1.5">
                  <span>Gültig am</span>
                  <input
                    ref="dayPassDateInput"
                    v-model="dayPassDateValue"
                    type="date"
                    :min="todayIso"
                    class="field px-1.5 py-0.5"
                    @keydown.enter.prevent="saveDayPassDate"
                    @keydown.esc.prevent="cancelDayPassDateEdit"
                  >
                  <button
                    type="button"
                    class="p-0.5 text-ink-muted hover:text-success-strong disabled:opacity-50"
                    title="Datum speichern"
                    :disabled="!dayPassDateValue || accessForm.processing"
                    @click="saveDayPassDate"
                  >
                    <CheckCircle class="w-3.5 h-3.5" />
                  </button>
                  <button
                    type="button"
                    class="p-0.5 text-ink-muted hover:text-ink"
                    title="Abbrechen"
                    @click="cancelDayPassDateEdit"
                  >
                    <X class="w-3.5 h-3.5" />
                  </button>
                </div>
                <div v-else-if="accessForm.day_pass_valid_until" class="flex items-center gap-1">
                  <button
                    type="button"
                    class="hover:text-ink hover:underline"
                    title="Datum ändern"
                    @click="startDayPassDateEdit"
                  >
                    Gültig am {{ formatDate(accessForm.day_pass_valid_until) }}
                  </button>
                  <button
                    type="button"
                    class="p-0.5 text-ink-muted hover:text-danger-strong disabled:opacity-50"
                    title="Datum entfernen"
                    :disabled="accessForm.processing"
                    @click="removeDayPassDate"
                  >
                    <X class="w-3.5 h-3.5" />
                  </button>
                </div>
                <div v-else>
                  Wird beim ersten Check-in eingelöst ·
                  <button
                    type="button"
                    class="text-rose-600 hover:underline"
                    @click="startDayPassDateEdit"
                  >
                    Datum festlegen
                  </button>
                </div>
                <p v-if="accessForm.errors.day_pass_valid_until" class="text-danger mt-1">
                  {{ accessForm.errors.day_pass_valid_until }}
                </p>
              </div>
            </div>
            <label class="relative inline-flex items-center cursor-pointer flex-none">
              <input
                v-model="accessForm.day_pass_enabled"
                type="checkbox"
                role="switch"
                aria-label="Tageskarte"
                class="sr-only peer"
                @change="updateAccessSettings"
              >
              <div class="w-11 h-6 bg-fill-strong peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-rose-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-surface after:border-line-strong after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-rose-600"></div>
            </label>
          </div>
        </div>

        <!-- Visit card -->
        <div class="rounded-lg border border-line bg-surface shadow-sm p-3.5">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-[10px] bg-teal-100 text-teal-600 flex items-center justify-center flex-none">
              <Hash class="w-5 h-5" />
            </div>
            <div class="flex-1 min-w-0">
              <h5 class="font-semibold text-ink">Mehrfachkarte</h5>
              <p class="text-sm text-ink-muted mt-0.5">Mehrere Eintritte</p>
              <p v-if="accessForm.visit_card_enabled" class="text-xs text-ink-muted mt-1">
                {{ accessForm.visit_card_entries || 0 }} Eintritte übrig
              </p>
            </div>
            <label class="relative inline-flex items-center cursor-pointer flex-none">
              <input
                v-model="accessForm.visit_card_enabled"
                type="checkbox"
                role="switch"
                aria-label="Mehrfachkarte"
                class="sr-only peer"
                @change="updateAccessSettings"
              >
              <div class="w-11 h-6 bg-fill-strong peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-teal-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-surface after:border-line-strong after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-teal-600"></div>
            </label>
          </div>
        </div>

        <!-- Guest wallet pass -->
        <div v-if="member.access_config?.has_guest_pass" class="rounded-lg border border-line bg-surface shadow-sm p-3.5">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-[10px] bg-fill text-ink-secondary flex items-center justify-center flex-none">
              <WalletCards class="w-5 h-5" />
            </div>
            <div class="flex-1 min-w-0">
              <h5 class="font-semibold text-ink">Wallet-Pass</h5>
              <p class="text-sm text-ink-muted mt-0.5">Gast-QR-Code im Apple oder Google Wallet</p>
            </div>
            <button
              type="button"
              class="flex-none px-3 py-1.5 text-sm font-medium text-danger bg-danger-subtle border border-danger-line rounded-lg hover:bg-danger-soft"
              @click="revokeGuestPass"
            >
              Sperren
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Advanced: static login code override (not used for external members) -->
    <div v-if="!isExternMember || hasStaticLoginCode" class="flex flex-col gap-3">
      <div class="flex justify-between items-center gap-2.5">
        <div class="flex items-center gap-2 min-w-0">
          <h3 class="text-lg font-semibold text-ink">Erweiterte Optionen</h3>
          <span
            v-if="hasStaticLoginCode"
            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-warning-soft text-warning-strong text-xs font-semibold"
          >
            <ShieldAlert class="w-3.5 h-3.5 flex-none" />
            Abweichende Konfiguration
          </span>
        </div>
        <button
          @click="advancedOpen = !advancedOpen"
          type="button"
          class="flex-none text-ink-muted hover:text-ink flex items-center gap-1.5 text-sm font-medium transition-colors whitespace-nowrap"
        >
          <span>{{ advancedOpen ? 'Ausblenden' : 'Einblenden' }}</span>
          <ChevronDown v-if="!advancedOpen" class="w-4 h-4" />
          <ChevronUp v-else class="w-4 h-4" />
        </button>
      </div>

      <div v-if="advancedOpen" class="rounded-lg border border-line bg-surface shadow-sm overflow-hidden">
        <div class="flex items-center gap-3 p-4">
          <div
            class="w-11 h-11 rounded-xl flex items-center justify-center flex-none"
            :class="hasStaticLoginCode ? 'bg-warning-subtle text-warning' : 'bg-fill text-ink-muted'"
          >
            <KeyRound class="w-[22px] h-[22px]" />
          </div>
          <div class="flex-1 min-w-0">
            <h4 class="font-semibold text-ink">Statischer Login-Code</h4>
            <p class="text-sm text-ink-muted mt-0.5">
              Ersetzt den per E-Mail versendeten Login-Code der Mitglieder-App
            </p>
          </div>
        </div>

        <!-- Status footer strip -->
        <div
          class="px-4 py-2.5 border-t text-sm flex items-start gap-2"
          :class="hasStaticLoginCode ? 'bg-warning-subtle border-warning-line text-warning-strong' : 'bg-surface-muted border-line-subtle text-ink-muted'"
        >
          <ShieldAlert v-if="hasStaticLoginCode" class="w-4 h-4 flex-none mt-0.5" />
          <CheckCircle v-else class="w-4 h-4 flex-none mt-0.5" />
          <span v-if="hasStaticLoginCode">
            Ein statischer Login-Code ist hinterlegt. Das Mitglied meldet sich damit ohne E-Mail-Versand an.
            Aus Sicherheitsgründen kann der Code nicht mehr angezeigt, sondern nur neu gesetzt oder entfernt werden.
          </span>
          <span v-else>Standardkonfiguration — der Login-Code wird bei jeder Anmeldung per E-Mail versendet.</span>
        </div>

        <div class="p-4 border-t border-line-subtle flex flex-col gap-3">
          <div v-if="!isExternMember" class="bg-info-subtle p-3 rounded-lg">
            <div class="flex items-start gap-2">
              <Info class="w-5 h-5 text-info mt-0.5 flex-none" />
              <p class="text-sm text-info-strong">
                Nutzen Sie diese Option nur in Einzelfällen — etwa wenn die Login-E-Mails beim Mitglied nicht
                ankommen oder Sie sich für einen Supportauftrag vorübergehend in die Mitglieder-App einloggen
                müssen. Der Code gilt unbegrenzt, bis Sie ihn wieder entfernen.
              </p>
            </div>
          </div>

          <div v-if="isExternMember" class="bg-warning-subtle p-3 rounded-lg">
            <div class="flex items-start gap-2">
              <ShieldAlert class="w-5 h-5 text-warning mt-0.5 flex-none" />
              <p class="text-sm text-warning-strong">
                Für externe Mitglieder wird kein statischer Login-Code berücksichtigt. Sie können den hinterlegten
                Code nur noch entfernen.
              </p>
            </div>
          </div>

          <div v-if="!editingStaticCode" class="flex flex-wrap gap-x-4 gap-y-2">
            <button
              v-if="!isExternMember"
              @click="startStaticCodeEdit"
              type="button"
              class="text-sm font-semibold text-brand-ink hover:text-brand-ink-strong flex items-center gap-1.5"
            >
              <KeyRound class="w-4 h-4" />
              {{ hasStaticLoginCode ? 'Code neu setzen' : 'Code festlegen' }}
            </button>
            <button
              v-if="hasStaticLoginCode"
              @click="removeStaticCode"
              type="button"
              class="text-sm font-semibold text-danger hover:text-danger-strong flex items-center gap-1.5"
            >
              <XCircle class="w-4 h-4" />
              Code entfernen
            </button>
          </div>

          <div v-else class="flex flex-col gap-3">
            <div class="flex flex-col sm:flex-row sm:items-center gap-3">
              <!-- Six single-digit boxes, mirroring the code entry in the member app -->
              <div class="flex gap-2">
                <input
                  v-for="(digit, index) in codeDigits"
                  :key="index"
                  :ref="el => setCodeInputRef(el, index)"
                  :value="digit"
                  type="text"
                  inputmode="numeric"
                  autocomplete="off"
                  maxlength="6"
                  :aria-label="`Stelle ${index + 1} von 6`"
                  class="field w-11 h-12 text-center text-lg font-mono font-semibold"
                  :class="digit ? 'border-brand' : ''"
                  @input="onCodeInput($event, index)"
                  @keydown.backspace="onCodeBackspace($event, index)"
                  @keydown.left.prevent="focusCodeInput(index - 1)"
                  @keydown.right.prevent="focusCodeInput(index + 1)"
                  @keyup.enter="saveStaticCode"
                  @focus="$event.target.select()"
                >
              </div>
              <div class="flex gap-2">
                <button
                  @click="saveStaticCode"
                  type="button"
                  :disabled="!isStaticCodeValid || staticCodeForm.processing"
                  class="px-4 py-2 rounded-lg bg-brand text-white text-sm font-semibold hover:bg-brand-strong disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-1.5"
                >
                  <Loader2 v-if="staticCodeForm.processing" class="w-4 h-4 animate-spin" />
                  Speichern
                </button>
                <button
                  @click="cancelStaticCodeEdit"
                  type="button"
                  class="px-4 py-2 rounded-lg border border-line-strong text-ink-secondary text-sm font-semibold hover:bg-surface-muted"
                >
                  Abbrechen
                </button>
              </div>
            </div>
            <p v-if="staticCodeForm.errors.static_login_code" class="text-sm text-danger">
              {{ staticCodeForm.errors.static_login_code }}
            </p>
            <p v-else class="text-xs text-ink-muted">
              Der Code besteht aus genau 6 Ziffern. Notieren Sie ihn — er lässt sich später nicht mehr auslesen.
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- Linked Devices (only visible when PWA login is disabled, i.e. branded app only) -->
    <div v-if="member.gym?.pwa_settings?.pwa_login_disabled" class="flex flex-col gap-3">
      <h3 class="text-lg font-semibold text-ink">Verknüpfte Geräte</h3>

      <div v-if="member.devices && member.devices.length > 0" class="flex flex-col gap-3">
        <div class="bg-warning-subtle p-3 rounded-lg">
          <div class="flex items-start gap-2">
            <Info class="w-5 h-5 text-warning mt-0.5 flex-none" />
            <div class="text-sm text-warning-strong">
              <p>Maximal {{ maxDevicesPerMember }} {{ maxDevicesPerMember === 1 ? 'Gerät kann' : 'Geräte können' }} mit diesem Mitglied verknüpft sein. Neue Geräte werden beim Login über die Branded App automatisch registriert.</p>
            </div>
          </div>
        </div>

        <div
          v-for="device in member.devices"
          :key="device.id"
          class="flex items-center justify-between gap-3 p-4 rounded-lg border border-line bg-surface shadow-sm"
        >
          <div class="flex items-center gap-3 min-w-0">
            <div class="p-2 bg-fill rounded-lg flex-none">
              <Smartphone class="w-5 h-5 text-ink-secondary" />
            </div>
            <div class="min-w-0">
              <p class="text-sm font-medium text-ink truncate">
                {{ device.device_name || 'Unbekanntes Gerät' }}
              </p>
              <p class="text-xs text-ink-muted font-mono truncate">
                {{ device.device_token.substring(0, 8) }}...
              </p>
              <p v-if="device.last_used_at" class="text-xs text-ink-muted">
                Zuletzt aktiv: {{ formatDateTime(device.last_used_at) }}
              </p>
            </div>
          </div>
          <button
            @click="removeDevice(device)"
            type="button"
            :disabled="removingDeviceId === device.id"
            class="p-2 text-danger hover:text-danger-strong hover:bg-danger-subtle rounded-lg transition-colors flex-none"
            title="Gerät entfernen"
          >
            <Loader2 v-if="removingDeviceId === device.id" class="w-5 h-5 animate-spin" />
            <X v-else class="w-5 h-5" />
          </button>
        </div>
      </div>

      <div v-else class="text-center py-8 bg-surface-muted rounded-lg">
        <Smartphone class="w-12 h-12 text-ink-muted mx-auto mb-4" />
        <p class="text-ink-muted">Keine Geräte verknüpft</p>
        <p class="text-xs text-ink-muted mt-1">Geräte werden beim Login über die Branded App automatisch registriert</p>
      </div>
    </div>

    <!-- Access Log -->
    <div class="flex flex-col gap-3">
      <h3 class="text-lg font-semibold text-ink">Zugangshistorie</h3>

      <div v-if="visibleAccessLogs.length > 0" class="rounded-lg border border-line bg-surface shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-[820px] w-full border-collapse">
            <thead>
              <tr class="bg-surface-muted">
                <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase tracking-wide whitespace-nowrap">Zeitpunkt</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase tracking-wide whitespace-nowrap">Aktion</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase tracking-wide whitespace-nowrap">Leistung</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase tracking-wide whitespace-nowrap">Gerät</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase tracking-wide whitespace-nowrap">Methode</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase tracking-wide whitespace-nowrap">Status</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-line-subtle">
              <tr v-for="log in visibleAccessLogs" :key="log.id" class="hover:bg-surface-muted">
                <td class="px-4 py-3 text-sm whitespace-nowrap">{{ formatDateTime(log.accessed_at) }}</td>
                <td class="px-4 py-3 text-sm whitespace-nowrap">
                  {{ log.action_name }}
                  <span v-if="log.performed_by_name" class="block text-xs text-ink-muted">
                    durch {{ log.performed_by_name }}
                  </span>
                </td>
                <td class="px-4 py-3 text-sm whitespace-nowrap">{{ log.service_name || '—' }}</td>
                <td class="px-4 py-3 text-sm whitespace-nowrap text-ink-muted">{{ log.device_name || '—' }}</td>
                <td class="px-4 py-3 text-sm whitespace-nowrap">
                  <span v-if="log.method" class="inline-flex items-center gap-1">
                    <component :is="getAccessMethodIcon(log.method)" class="w-4 h-4" />
                    {{ log.method }}
                  </span>
                  <span v-else class="text-ink-muted">—</span>
                </td>
                <td class="px-4 py-3 text-sm whitespace-nowrap">
                  <template v-if="log.is_access_attempt">
                    <span :class="log.success ? 'text-success' : 'text-danger'">
                      {{ log.success ? 'Erfolgreich' : 'Verweigert' }}
                    </span>
                    <span v-if="!log.success && log.reason" class="block text-xs text-ink-muted">
                      {{ log.reason }}
                    </span>
                  </template>
                  <span v-else class="text-ink-muted">—</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="flex items-center gap-1.5 px-4 py-2.5 text-xs text-ink-muted border-t border-line-subtle sm:hidden">
          <MoveHorizontal class="w-3.5 h-3.5" />
          Zum Scrollen wischen
        </div>

        <div v-if="hasMoreAccessLogs" class="px-4 py-4 text-center border-t border-line-subtle">
          <button
            type="button"
            :disabled="loadingMoreAccessLogs"
            class="inline-flex items-center px-4 py-2 border border-line-strong rounded-md text-sm font-medium text-ink-secondary bg-surface hover:bg-surface-muted disabled:opacity-50"
            @click="loadMoreAccessLogs"
          >
            <Loader2 v-if="loadingMoreAccessLogs" class="w-4 h-4 mr-2 animate-spin" />
            {{ loadingMoreAccessLogs ? 'Laden...' : 'Weitere laden' }}
          </button>
        </div>
      </div>
      <div v-else class="text-center py-8 bg-surface-muted rounded-lg">
        <Key class="w-12 h-12 text-ink-muted mx-auto mb-4" />
        <p class="text-ink-muted">Noch keine Zugänge protokolliert</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, nextTick, onMounted, onUnmounted, watch } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import {
  QrCode, Nfc, Sun, Package, Armchair, Coffee, Ticket, Hash, WalletCards, Info, Mail, Loader2, Radio,
  Smartphone, X, XCircle, Plus, CheckCircle, Key, MoveHorizontal, KeyRound, ShieldAlert, ChevronDown, ChevronUp,
} from '@lucide/vue'
import { formatCurrency, formatDate, formatDateForInput, formatDateTime, todayInDisplayTimezone } from '@/utils/formatters'
import MemberAggregatorCard from '@/Components/Members/MemberAggregatorCard.vue'

const props = defineProps({
  member: {
    type: Object,
    required: true,
  },
  maxDevicesPerMember: {
    type: Number,
    default: 2,
  },
  // Total number of access log entries. Only the first page is delivered with
  // the page; the rest is fetched on demand.
  accessLogsTotal: {
    type: Number,
    default: 0,
  },
})

// Access Control state
const editingNfc = ref(false)
const nfcInputValue = ref('')
const normalizedNfcId = ref('')
const isNfcValid = ref(false)
// Computed rather than copied on mount, so the list follows Inertia reloads.
const accessLogs = computed(() => props.member?.access_logs ?? [])

// Pages loaded on top of the initial one. Reset whenever Inertia delivers a
// fresh first page, so a reload never shows stale rows below the new ones.
const additionalAccessLogs = ref([])
const accessLogsPage = ref(1)
const accessLogsHasMore = ref(null)
const loadingMoreAccessLogs = ref(false)

watch(accessLogs, () => {
  additionalAccessLogs.value = []
  accessLogsPage.value = 1
  accessLogsHasMore.value = null
})

const visibleAccessLogs = computed(() => [...accessLogs.value, ...additionalAccessLogs.value])

// Before the first fetch the server-provided total decides; afterwards the
// endpoint's own has_more flag is authoritative.
const hasMoreAccessLogs = computed(() => accessLogsHasMore.value !== null
  ? accessLogsHasMore.value
  : visibleAccessLogs.value.length < props.accessLogsTotal)

const loadMoreAccessLogs = async () => {
  if (loadingMoreAccessLogs.value || !hasMoreAccessLogs.value) return

  loadingMoreAccessLogs.value = true
  try {
    const response = await axios.get(route('members.access.logs', props.member.id), {
      params: { page: accessLogsPage.value + 1 },
    })

    additionalAccessLogs.value.push(...(response.data.data ?? []))
    accessLogsPage.value = response.data.current_page ?? accessLogsPage.value + 1
    accessLogsHasMore.value = response.data.has_more ?? false
  } catch (error) {
    console.error('Failed to load more access logs:', error)
  } finally {
    loadingMoreAccessLogs.value = false
  }
}

// Device management
const removingDeviceId = ref(null)

// Advanced options: static login code (overrides the emailed TOTP code).
// The code itself is never sent to the frontend — only a boolean flag.
const advancedOpen = ref(false)
const editingStaticCode = ref(false)
const staticCodeForm = useForm({ static_login_code: '' })
const hasStaticLoginCode = computed(() => props.member.access_config?.has_static_login_code ?? false)
const isExternMember = computed(() => props.member.status === 'extern')
const isStaticCodeValid = computed(() => /^\d{6}$/.test(staticCodeForm.static_login_code))

// Six single-digit boxes; the joined value is what gets submitted.
const codeDigits = ref(['', '', '', '', '', ''])
const codeInputRefs = ref([])

const setCodeInputRef = (el, index) => {
  codeInputRefs.value[index] = el
}

const focusCodeInput = (index) => {
  if (index < 0 || index > 5) return
  const input = codeInputRefs.value[index]
  input?.focus()
  input?.select()
}

const syncStaticCodeFromDigits = () => {
  staticCodeForm.static_login_code = codeDigits.value.join('')
}

const resetCodeDigits = () => {
  codeDigits.value = ['', '', '', '', '', '']
  syncStaticCodeFromDigits()
}

const onCodeInput = (event, index) => {
  const typed = event.target.value.replace(/\D/g, '')

  if (typed.length > 1) {
    // A full code was pasted (or typed fast) — spread it across the boxes.
    const chars = typed.split('').slice(0, 6 - index)
    chars.forEach((char, offset) => {
      codeDigits.value[index + offset] = char
    })
    syncStaticCodeFromDigits()
    focusCodeInput(Math.min(index + chars.length, 5))
  } else {
    codeDigits.value[index] = typed
    syncStaticCodeFromDigits()
    if (typed && index < 5) {
      focusCodeInput(index + 1)
    }
  }

  // Keep the DOM in sync when the input was rejected (e.g. a letter).
  event.target.value = codeDigits.value[index]
}

const onCodeBackspace = (event, index) => {
  if (codeDigits.value[index]) {
    return
  }

  // Empty box: step back and clear the previous digit.
  event.preventDefault()
  if (index > 0) {
    codeDigits.value[index - 1] = ''
    syncStaticCodeFromDigits()
    focusCodeInput(index - 1)
  }
}

// NFC Scanning state
const isNfcScanning = ref(false)
const nfcScanChannel = ref(null)
const nfcScanConnected = ref(false)

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

  day_pass_enabled: props.member.access_config?.day_pass_enabled || false,
  day_pass_valid_until: props.member.access_config?.day_pass_valid_until || null,

  visit_card_enabled: props.member.access_config?.visit_card_enabled || false,
  visit_card_entries: props.member.access_config?.visit_card_entries || 0,
})

// Number of currently active access methods/services — surfaced to the parent
// so it can render the tab badge without owning the access form.
const activeAccessCount = computed(() => {
  let count = 0
  if (accessForm.qr_code_enabled) count++
  if (accessForm.nfc_enabled) count++
  if (accessForm.solarium_enabled) count++
  if (accessForm.vending_enabled) count++
  if (accessForm.massage_enabled) count++
  if (accessForm.coffee_flat_enabled) count++
  if (accessForm.day_pass_enabled) count++
  if (accessForm.visit_card_enabled) count++
  return count
})

// Additional NFC tags next to the primary one
const additionalNfcTags = computed(() => props.member.access_config?.additional_nfc_tags ?? [])
const addingNfcTag = ref(false)
const nfcTagInput = ref(null)
const nfcTagInputValue = ref('')
const normalizedNfcTagId = computed(() => normalizeCardId(nfcTagInputValue.value) || '')
const nfcTagForm = useForm({ uid: '' })
const removingNfcTagId = ref(null)

// The server may change the NFC state on its own (a removed primary tag is
// replaced by an additional one, or NFC is switched off without any tag left),
// so the form follows the props after every reload.
watch(
  () => [props.member.access_config?.nfc_enabled, props.member.access_config?.nfc_uid],
  ([enabled, uid]) => {
    accessForm.nfc_enabled = enabled ?? false
    accessForm.nfc_uid = uid || ''
    if (!editingNfc.value) {
      nfcInputValue.value = formatNfcIdForDisplay(uid)
    }
  },
)

const startNfcTagAdd = () => {
  nfcTagForm.clearErrors()
  nfcTagInputValue.value = ''
  addingNfcTag.value = true
  nextTick(() => nfcTagInput.value?.focus())
}

const cancelNfcTagAdd = () => {
  nfcTagForm.clearErrors()
  nfcTagInputValue.value = ''
  addingNfcTag.value = false
}

const saveAdditionalNfcTag = () => {
  if (!normalizedNfcTagId.value || nfcTagForm.processing) return

  nfcTagForm.uid = normalizedNfcTagId.value
  nfcTagForm.post(route('members.access.nfc-tags.store', props.member.id), {
    preserveScroll: true,
    onSuccess: () => cancelNfcTagAdd(),
  })
}

const removeAdditionalNfcTag = (tag) => {
  if (!confirm(`Möchten Sie den NFC-Tag ${formatNfcIdForDisplay(tag.uid)} wirklich entfernen?`)) {
    return
  }

  removingNfcTagId.value = tag.id
  router.delete(route('members.access.nfc-tags.destroy', { member: props.member.id, nfcTag: tag.id }), {
    preserveScroll: true,
    onFinish: () => {
      removingNfcTagId.value = null
    },
  })
}

// Device management
const removeDevice = (device) => {
  if (!confirm('Möchten Sie dieses Gerät wirklich entfernen? Das Mitglied wird auf allen Geräten abgemeldet und kann sich anschließend mit einem neuen Gerät anmelden.')) {
    return
  }
  removingDeviceId.value = device.id
  router.delete(route('members.access.remove-device', { member: props.member.id, device: device.id }), {
    preserveScroll: true,
    onFinish: () => {
      removingDeviceId.value = null
    },
  })
}

const startStaticCodeEdit = () => {
  staticCodeForm.reset()
  staticCodeForm.clearErrors()
  resetCodeDigits()
  editingStaticCode.value = true
  nextTick(() => focusCodeInput(0))
}

const cancelStaticCodeEdit = () => {
  staticCodeForm.reset()
  staticCodeForm.clearErrors()
  resetCodeDigits()
  editingStaticCode.value = false
}

const saveStaticCode = () => {
  if (!isStaticCodeValid.value) return

  staticCodeForm.post(route('members.access.static-login-code.store', props.member.id), {
    preserveScroll: true,
    onSuccess: () => {
      staticCodeForm.reset()
      resetCodeDigits()
      editingStaticCode.value = false
    },
  })
}

const removeStaticCode = () => {
  if (!confirm('Möchten Sie den statischen Login-Code wirklich entfernen? Das Mitglied erhält seinen Login-Code danach wieder per E-Mail.')) {
    return
  }

  router.delete(route('members.access.static-login-code.destroy', props.member.id), {
    preserveScroll: true,
  })
}

const revokeGuestPass = () => {
  if (!confirm('Möchten Sie den Wallet-Pass wirklich sperren? Der Gast kann danach in der Gäste-App einen neuen Pass hinzufügen.')) {
    return
  }

  router.delete(route('members.access.guest-pass.destroy', props.member.id), {
    preserveScroll: true,
  })
}

// NFC ID normalisation
const normalizeCardId = (cardId) => {
  if (!cardId) return null

  // Trim whitespace and uppercase
  cardId = cardId.trim().toUpperCase()

  // 1. UID format with separators (04:A1:B2:C3 or 04-A1-B2-C3)
  if (cardId.includes(':') || cardId.includes('-')) {
    const normalized = cardId.replace(/[:-]/g, '')
    if (/^[0-9A-F]+$/.test(normalized)) {
      return normalized
    }
  }

  // 2. Hex with 0x prefix
  else if (cardId.startsWith('0X')) {
    const hexPart = cardId.substring(2)
    if (/^[0-9A-F]+$/.test(hexPart)) {
      return hexPart
    }
  }

  // 3. Plain hex (A-F, 0-9)
  else if (/^[0-9A-F]+$/.test(cardId)) {
    return cardId
  }

  // 4. Plain decimal
  else if (/^[0-9]+$/.test(cardId)) {
    // Convert decimal to hex for uniform storage
    return parseInt(cardId, 10).toString(16).toUpperCase()
  }

  return null
}

// Format NFC ID for display (with colons for readability)
const formatNfcIdForDisplay = (nfcId) => {
  if (!nfcId) return ''
  return nfcId.match(/.{1,2}/g)?.join(':') || nfcId
}

const invalidateQrCode = () => {
  if (confirm('Möchten Sie wirklich den QR-Code invalidieren? Das Mitglied kann sich dann nicht mehr per QR-Code einloggen, bis ein neuer Code generiert wird.')) {
    router.post(route('members.access.invalidate-qr', props.member.id), {}, {
      preserveScroll: true,
    })
  }
}

const sendQrCodeToMember = () => {
  if (confirm(`Möchten Sie dem Mitglied einen Link zur Mitglieder-App per E-Mail an ${props.member.email} senden?`)) {
    router.post(route('members.access.send-app-link', props.member.id), {}, {
      preserveScroll: true,
      onSuccess: () => {
        alert('E-Mail wurde erfolgreich versendet.')
      },
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

  // Store the normalised version
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
    },
  })
}

const cancelNfcEdit = () => {
  nfcInputValue.value = formatNfcIdForDisplay(accessForm.nfc_uid) || ''
  normalizedNfcId.value = ''
  isNfcValid.value = false
  editingNfc.value = false
}

const removeNfcTag = () => {
  const message = additionalNfcTags.value.length > 0
    ? 'Möchten Sie den NFC-Tag wirklich entfernen? Der erste weitere NFC-Tag wird dann zum primären Tag.'
    : 'Möchten Sie den NFC-Tag wirklich entfernen? Ohne NFC-Tag wird der NFC-Zugang deaktiviert.'
  if (confirm(message)) {
    accessForm.nfc_uid = ''
    nfcInputValue.value = ''
    normalizedNfcId.value = ''

    accessForm.put(route('members.access.update', props.member.id), {
      preserveScroll: true,
    })
  }
}

const updateAccessSettings = () => {
  accessForm.put(route('members.access.update', props.member.id), {
    preserveScroll: true,
  })
}

// Day pass date editing
const editingDayPassDate = ref(false)
const dayPassDateInput = ref(null)
const dayPassDateValue = ref('')
const todayIso = computed(() => todayInDisplayTimezone())

const startDayPassDateEdit = () => {
  accessForm.clearErrors('day_pass_valid_until')
  dayPassDateValue.value = formatDateForInput(accessForm.day_pass_valid_until) || todayIso.value
  editingDayPassDate.value = true
  nextTick(() => dayPassDateInput.value?.focus())
}

const cancelDayPassDateEdit = () => {
  editingDayPassDate.value = false
  dayPassDateValue.value = ''
}

// Submits a new day pass date and restores the previous one if validation fails
const submitDayPassDate = (date, onSuccess = () => {}) => {
  const previous = accessForm.day_pass_valid_until
  accessForm.day_pass_valid_until = date
  accessForm.put(route('members.access.update', props.member.id), {
    preserveScroll: true,
    onSuccess,
    onError: () => {
      accessForm.day_pass_valid_until = previous
    },
  })
}

const saveDayPassDate = () => {
  if (!dayPassDateValue.value || accessForm.processing) return

  submitDayPassDate(dayPassDateValue.value, cancelDayPassDateEdit)
}

const removeDayPassDate = () => {
  if (!confirm('Möchten Sie das Datum der Tageskarte wirklich entfernen? Die Tageskarte wird dann beim nächsten Check-in eingelöst.')) {
    return
  }

  submitDayPassDate(null)
}

// NFC Scanning functions
const startNfcScanning = () => {
  if (!window.Echo || !props.member.gym?.id) {
    alert('WebSocket-Verbindung nicht verfügbar.')
    return
  }

  isNfcScanning.value = true
  const gymId = props.member.gym.id

  try {
    nfcScanChannel.value = window.Echo.private(`gym.${gymId}.access-logs`)

    nfcScanChannel.value.listen('.scanner.access', (event) => {
      const log = event.log
      // Only process NFC card scans
      if (log.scan_type === 'nfc_card' && log.nfc_card_id) {
        // Found an NFC card scan - use the nfc_card_id
        const nfcCardId = log.nfc_card_id

        // Set the value and save
        accessForm.nfc_uid = nfcCardId
        nfcInputValue.value = formatNfcIdForDisplay(nfcCardId)

        // Save immediately
        accessForm.put(route('members.access.update', props.member.id), {
          preserveScroll: true,
          onSuccess: () => {
            stopNfcScanning()
          },
          onError: (errors) => {
            console.error('Fehler beim Speichern der NFC-ID:', errors)
            alert('Die NFC-ID konnte nicht gespeichert werden. Möglicherweise ist diese ID bereits einem anderen Mitglied zugeordnet.')
            stopNfcScanning()
          },
        })
      }
    })

    nfcScanChannel.value.subscribed(() => {
      nfcScanConnected.value = true
      console.log(`NFC scanning started for gym.${gymId}.access-logs`)
    })

    nfcScanChannel.value.error((error) => {
      console.error('NFC scan WebSocket error:', error)
      nfcScanConnected.value = false
      stopNfcScanning()
    })
  } catch (error) {
    console.error('Failed to start NFC scanning:', error)
    isNfcScanning.value = false
  }
}

const stopNfcScanning = () => {
  if (nfcScanChannel.value && window.Echo && props.member.gym?.id) {
    window.Echo.leave(`gym.${props.member.gym.id}.access-logs`)
    nfcScanChannel.value = null
  }
  isNfcScanning.value = false
  nfcScanConnected.value = false
}

// Keys match MemberAccessLog::getMethodNameAttribute(), which is what the
// backend sends — not the raw slugs.
const getAccessMethodIcon = (method) => {
  const icons = {
    'QR-Code': QrCode,
    'NFC-Tag': Nfc,
    'Manuell': Key,
  }
  return icons[method] || Key
}

// Lifecycle
onMounted(() => {
  // Initialize NFC value with proper formatting
  if (props.member?.access_config?.nfc_uid) {
    accessForm.nfc_uid = props.member.access_config.nfc_uid
    nfcInputValue.value = formatNfcIdForDisplay(props.member.access_config.nfc_uid)
  }
})

onUnmounted(() => {
  stopNfcScanning()
})

defineExpose({ activeAccessCount })
</script>
