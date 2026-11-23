<template>
  <div class="flex gap-6 items-start flex-col xl:flex-row">
    <!-- Einstellung -->
    <div class="flex-1 min-w-0 w-full bg-surface rounded-lg shadow-sm border border-line p-6">
      <h3 class="text-lg leading-6 font-semibold text-ink mb-1">Standorteinschränkungen</h3>
      <p class="text-sm text-ink-secondary mb-5">
        Bestimmt, an welchen Standorten Mitglieder mit dem Vertrag „{{ planName }}“ einchecken dürfen.
      </p>

      <!-- Rückwirkende Wirkung -->
      <div v-if="activeMembersCount > 0" class="bg-warning-subtle border border-warning-line rounded-lg p-4 mb-6">
        <div class="flex items-start">
          <AlertTriangle class="w-5 h-5 text-warning mt-0.5 mr-2 flex-shrink-0" />
          <div>
            <p class="text-sm font-medium text-warning-strong">Achtung: Änderung wirkt rückwirkend</p>
            <p class="text-sm text-warning mt-1">
              Die Einstellung gilt für alle {{ activeMembersCount }}
              {{ activeMembersCount === 1 ? 'Mitglied' : 'Mitglieder' }} mit diesem Vertrag,
              unabhängig davon, wann die Mitgliedschaft abgeschlossen wurde.
            </p>
          </div>
        </div>
      </div>

      <!-- Standort lässt keine Fremd-Check-ins zu -->
      <div v-if="isLocked" class="bg-surface-muted border border-line rounded-lg p-4 mb-6">
        <div class="flex items-start">
          <Lock class="w-5 h-5 text-ink-muted mt-0.5 mr-2 flex-shrink-0" />
          <div>
            <p class="text-sm font-medium text-ink-secondary">Nicht bearbeitbar</p>
            <p class="text-sm text-ink-secondary mt-1">
              Der Standort {{ state.gym_name }} lässt unter Zugangskontrolle / Konfiguration nur
              Mitglieder dieses Standorts zu. Solange diese Einstellung gilt, ist keine abweichende
              Standortauswahl im Vertrag möglich.
            </p>
            <Link
              :href="route('access-control.index')"
              class="inline-block mt-2 text-sm font-medium text-brand-ink hover:text-brand-ink-strong"
            >
              Zur Zugangskontrolle
            </Link>
          </div>
        </div>
      </div>

      <div :class="isLocked ? 'opacity-50 pointer-events-none' : ''">
        <div class="space-y-4">
          <label
            v-for="option in SCOPE_OPTIONS"
            :key="option.value"
            class="flex gap-3 items-start cursor-pointer"
          >
            <input
              v-model="form.location_scope"
              type="radio"
              name="contract-location-scope"
              :value="option.value"
              :disabled="isLocked"
              class="mt-0.5 h-4 w-4 text-brand-ink border-line-strong"
            />
            <span>
              <span class="block text-sm font-medium text-ink-secondary">{{ option.title }}</span>
              <span class="block text-xs text-ink-muted mt-0.5">{{ option.desc }}</span>
            </span>
          </label>
        </div>

        <!-- Standortauswahl -->
        <div
          v-if="form.location_scope === 'selected'"
          class="mt-5 ml-7 border border-line rounded-lg overflow-hidden"
        >
          <div class="bg-surface-muted border-b border-line px-4 py-2.5 text-xs font-medium uppercase tracking-wide text-ink-muted">
            Erlaubte Standorte
          </div>
          <label
            v-for="location in state.locations"
            :key="location.id"
            class="flex items-center gap-3 px-4 py-3 border-b border-line-subtle last:border-b-0"
            :class="location.is_current ? 'bg-surface-muted' : 'cursor-pointer hover:bg-surface-muted'"
          >
            <input
              type="checkbox"
              :value="location.id"
              :checked="location.is_current || form.allowed_gym_ids.includes(location.id)"
              :disabled="location.is_current || isLocked"
              @change="toggleLocation(location.id)"
              class="h-4 w-4 text-brand-ink border-line-strong rounded"
            />
            <span class="flex-1 min-w-0">
              <span class="block text-sm font-medium text-ink">{{ location.name }}</span>
              <span class="block text-xs text-ink-muted">{{ locationAddress(location) }}</span>
            </span>
            <span v-if="location.is_current" class="text-xs text-ink-muted">Mitgliedsstandort</span>
          </label>
        </div>

        <div class="flex justify-end gap-3 mt-6">
          <button
            type="button"
            @click="reset"
            :disabled="isLocked || isSaving"
            class="px-4 py-2 rounded-lg border border-line-strong bg-surface text-sm font-medium text-ink-secondary hover:bg-surface-muted disabled:opacity-50"
          >
            Zurücksetzen
          </button>
          <button
            type="button"
            @click="save"
            :disabled="isLocked || isSaving"
            class="bg-brand hover:bg-brand-strong text-white text-sm font-medium px-6 py-2 rounded-lg disabled:opacity-50"
          >
            {{ isSaving ? 'Speichern...' : 'Speichern' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Ergebnis -->
    <div class="w-full xl:w-80 xl:flex-shrink-0 bg-surface rounded-lg shadow-sm border border-line p-6">
      <h3 class="text-lg leading-6 font-semibold text-ink mb-3">Ergebnis</h3>
      <p class="text-sm text-ink-secondary mb-4">
        Mit den aktuellen Einstellungen kann ein Mitglied aus {{ state.gym_name }} hier einchecken:
      </p>

      <div
        v-for="row in effect"
        :key="row.id"
        class="flex items-center gap-2.5 py-2.5 border-b border-line-subtle last:border-b-0"
      >
        <span
          :class="[
            'flex-shrink-0 w-5 h-5 rounded-full flex items-center justify-center',
            row.allowed ? 'bg-success-solid' : 'bg-gray-400'
          ]"
        >
          <Check v-if="row.allowed" class="w-3 h-3 text-white" />
          <X v-else class="w-3 h-3 text-white" />
        </span>
        <span class="flex-1 min-w-0 text-sm text-ink-secondary truncate">{{ row.name }}</span>
        <span class="text-xs text-ink-muted whitespace-nowrap">{{ row.reason }}</span>
      </div>

      <p class="mt-4 text-xs text-ink-muted">
        Ein Check-in gelingt nur, wenn Vertrag <em>und</em> Zielstandort ihn erlauben.
        Die Standortregel pflegen Sie unter Zugangskontrolle / Konfiguration.
      </p>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import axios from 'axios'
import { AlertTriangle, Lock, Check, X } from '@lucide/vue'

const props = defineProps({
  planId: {
    type: Number,
    required: true
  },
  planName: {
    type: String,
    required: true
  },
  activeMembersCount: {
    type: Number,
    default: 0
  },
  locationScope: {
    type: Object,
    required: true
  }
})

const emit = defineEmits(['success', 'error'])

const SCOPE_OPTIONS = [
  {
    value: 'own',
    title: 'Nur Mitgliedsstandort',
    desc: 'Mitglieder mit diesem Vertrag trainieren ausschließlich im Studio, in dem sie angemeldet wurden.'
  },
  {
    value: 'selected',
    title: 'Ausgewählte Standorte',
    desc: 'Der Vertrag gilt zusätzlich in den unten gewählten Standorten.'
  },
  {
    value: 'all',
    title: 'Alle Standorte der Organisation',
    desc: 'Der Vertrag gilt an allen Standorten der Organisation.'
  }
]

const state = ref({ ...props.locationScope })
const effect = ref([...props.locationScope.effect])
const isSaving = ref(false)

const form = ref({
  location_scope: props.locationScope.scope,
  allowed_gym_ids: [...props.locationScope.allowed_gym_ids]
})

// While the location itself admits only its own members, no contract setting can
// produce a cross-location check-in — offering the choice would be misleading.
const isLocked = computed(() => state.value.location_rule === 'own')

const locationAddress = (location) =>
  [location.address, location.city].filter(Boolean).join(', ') || '—'

const toggleLocation = (id) => {
  const index = form.value.allowed_gym_ids.indexOf(id)

  if (index === -1) {
    form.value.allowed_gym_ids.push(id)
  } else {
    form.value.allowed_gym_ids.splice(index, 1)
  }
}

const reset = () => {
  form.value = {
    location_scope: state.value.scope,
    allowed_gym_ids: [...state.value.allowed_gym_ids]
  }
}

const save = async () => {
  isSaving.value = true

  try {
    const response = await axios.put(route('contracts.locations.update', props.planId), form.value)

    state.value = {
      ...state.value,
      scope: form.value.location_scope,
      allowed_gym_ids: [...form.value.allowed_gym_ids]
    }
    effect.value = response.data.effect
    emit('success', response.data.message)
  } catch (error) {
    emit('error', error.response?.data?.message ?? 'Die Standorteinschränkungen konnten nicht gespeichert werden.')
  } finally {
    isSaving.value = false
  }
}
</script>
