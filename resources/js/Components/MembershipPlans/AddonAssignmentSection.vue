<template>
  <div class="bg-white rounded-lg shadow-sm border border-gray-200">
    <!-- Header -->
    <div class="p-5 border-b border-gray-100 flex items-start gap-3">
      <div class="w-10 h-10 flex-none rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center">
        <Package class="w-5 h-5" />
      </div>
      <div class="flex-1 min-w-0">
        <h3 class="text-base font-semibold text-gray-900">Add-ons</h3>
        <p class="text-xs text-gray-500 leading-relaxed mt-0.5">
          Zusatzleistungen für diesen Vertrag: inklusive enthalten oder optional zubuchbar.
        </p>
      </div>
      <span
        v-if="addons.length > 0"
        class="flex-none text-xs font-semibold text-indigo-600 bg-indigo-50 px-2.5 py-1 rounded-full mt-0.5 whitespace-nowrap"
      >
        {{ assignedCount }} zugeordnet
      </span>
    </div>

    <div class="p-5">
      <!-- No add-ons exist yet -->
      <div v-if="addons.length === 0" class="text-center py-6 px-2">
        <div class="w-10 h-10 mx-auto mb-2.5 rounded-full bg-gray-100 text-gray-400 flex items-center justify-center">
          <Package class="w-5 h-5" />
        </div>
        <p class="text-sm font-semibold text-gray-900">Noch keine Add-ons angelegt</p>
        <p class="text-xs text-gray-500 mt-0.5 leading-relaxed">
          Legen Sie Zusatzleistungen an, um sie diesem Vertrag zuzuordnen.
        </p>
      </div>

      <!-- Assignment rows -->
      <div v-else class="flex flex-col gap-2.5">
        <div
          v-for="addon in addons"
          :key="addon.id"
          class="border rounded-lg px-3.5 py-3 flex items-center gap-3 transition-colors"
          :class="modeOf(addon.id) ? 'border-indigo-200 bg-indigo-50/40' : 'border-gray-200 bg-white'"
        >
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2">
              <span class="text-sm font-semibold text-gray-900 truncate">{{ addon.name }}</span>
              <span
                v-if="!addon.is_active"
                class="flex-none text-xs font-medium text-gray-600 bg-gray-100 px-2 py-0.5 rounded-full"
              >
                Inaktiv
              </span>
            </div>
            <p class="text-xs text-gray-500 mt-0.5">{{ priceLabel(addon) }}</p>
          </div>

          <select
            :id="`addon_mode_${addon.id}`"
            :value="modeOf(addon.id) ?? ''"
            :aria-label="`Zuordnung für ${addon.name}`"
            class="w-40 flex-none text-sm border border-gray-300 rounded-md px-2.5 py-2 bg-white focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600"
            @change="setMode(addon.id, $event.target.value)"
          >
            <option value="">Nicht zugeordnet</option>
            <option value="optional">Optional</option>
            <option value="included">Inklusive</option>
          </select>
        </div>
      </div>

      <!-- Create a new add-on on its own page: an add-on needs more fields
           than this card can sensibly ask for. -->
      <Link
        :href="route('contracts.addons.create')"
        class="w-full mt-3 text-sm font-medium py-2.5 border border-dashed border-indigo-300 rounded-lg bg-indigo-50 text-indigo-600 hover:bg-indigo-100 transition-colors flex items-center justify-center gap-1.5"
      >
        <Plus class="w-4 h-4" />
        Neues Add-on anlegen
      </Link>

      <!-- Summary -->
      <dl v-if="assignedCount > 0" class="mt-4 bg-gray-50 rounded-lg px-4 py-3.5">
        <div v-if="includedAddons.length > 0" class="flex justify-between text-sm mb-2">
          <dt class="text-gray-600">Inklusive (immer berechnet)</dt>
          <dd class="text-gray-900 font-semibold">{{ formatCents(includedTotalCents) }}</dd>
        </div>
        <div v-if="optionalCount > 0" class="flex justify-between text-sm mb-2">
          <dt class="text-gray-600">Optional zubuchbar</dt>
          <dd class="text-gray-900 font-semibold">
            {{ optionalCount }} {{ optionalCount === 1 ? 'Add-on' : 'Add-ons' }}
          </dd>
        </div>
        <p class="text-xs text-gray-500 leading-relaxed mt-1">
          Inklusive Add-ons sind für Mitglieder vorausgewählt und nicht abwählbar.
          Optionale Add-ons wählt das Mitglied beim Vertragsabschluss.
        </p>
      </dl>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { Package, Plus } from 'lucide-vue-next'
import { formatCents, toCents } from '@/utils/discountProjection'

const props = defineProps({
  /** Map of { addon_id: 'included' | 'optional' } for the assigned add-ons. */
  modelValue: { type: Object, default: () => ({}) },
  addons: { type: Array, default: () => [] },
})

const emit = defineEmits(['update:modelValue'])

const modeOf = (addonId) => props.modelValue[addonId] ?? null

const assignedAddons = computed(() => props.addons.filter((addon) => modeOf(addon.id)))

const assignedCount = computed(() => assignedAddons.value.length)

const includedAddons = computed(() =>
  props.addons.filter((addon) => modeOf(addon.id) === 'included')
)

const optionalCount = computed(
  () => props.addons.filter((addon) => modeOf(addon.id) === 'optional').length
)

const includedTotalCents = computed(() =>
  includedAddons.value.reduce((total, addon) => total + toCents(addon.price), 0)
)

/**
 * An assignment of '' means "not assigned" and drops the add-on from the map.
 */
const setMode = (addonId, mode) => {
  const next = { ...props.modelValue }

  if (mode === '') {
    delete next[addonId]
  } else {
    next[addonId] = mode
  }

  emit('update:modelValue', next)
}

/**
 * Price plus how it is billed — one-off at contract start or monthly.
 */
const priceLabel = (addon) => {
  const price = formatCents(toCents(addon.price))

  return addon.billing_type === 'recurring' ? `${price} / Monat` : `${price} einmalig`
}
</script>
