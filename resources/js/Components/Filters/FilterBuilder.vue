<template>
  <div class="flex flex-col gap-3 px-3 py-4">
    <template v-for="(group, groupIndex) in modelValue.groups" :key="group.id">
      <div v-if="groupIndex > 0" class="flex items-center gap-2.5">
        <div class="h-px flex-1 bg-gray-200" />
        <button
          type="button"
          title="Verknüpfung der Gruppen umschalten"
          class="inline-flex items-center gap-1.5 rounded-full border border-indigo-200 bg-white px-3 py-1 text-xs font-semibold tracking-wider text-indigo-700 hover:bg-indigo-50"
          @click="emitDefinition({ rootLogic: toggleLogic(modelValue.rootLogic) })"
        >
          {{ LOGIC_LABELS[modelValue.rootLogic] }}
          <ArrowUpDown class="h-3 w-3" />
        </button>
        <div class="h-px flex-1 bg-gray-200" />
      </div>

      <div class="flex flex-col gap-2.5 rounded-lg border border-gray-200 bg-gray-50 px-4 pb-3.5 pt-3">
        <div class="flex flex-wrap items-center gap-2.5">
          <span class="text-xs font-medium uppercase tracking-wider text-gray-500">Gruppe {{ groupIndex + 1 }}</span>
          <span class="text-[13px] text-gray-500">Bedingungen verknüpfen mit</span>
          <div class="inline-flex overflow-hidden rounded-md border border-gray-300 bg-white">
            <button
              v-for="logic in ['AND', 'OR']"
              :key="logic"
              type="button"
              :class="[
                'px-3 py-1 text-xs font-semibold tracking-wide',
                group.logic === logic ? 'cursor-default bg-indigo-600 text-white' : 'text-gray-600 hover:bg-gray-100'
              ]"
              @click="updateGroup(group.id, { logic })"
            >
              {{ LOGIC_LABELS[logic] }}
            </button>
          </div>
          <span class="text-xs text-gray-400">{{ group.logic === 'AND' ? 'alle müssen zutreffen' : 'mindestens eine muss zutreffen' }}</span>
          <button
            v-if="modelValue.groups.length > 1"
            type="button"
            class="ml-auto inline-flex items-center gap-1 rounded-md px-1.5 py-1 text-[13px] text-gray-500 hover:bg-red-100 hover:text-red-700"
            @click="removeGroup(group.id)"
          >
            <Trash2 class="h-3.5 w-3.5" />
            Gruppe entfernen
          </button>
        </div>

        <FilterConditionRow
          v-for="(condition, conditionIndex) in group.conditions"
          :key="condition.id"
          :condition="condition"
          :fields="fields"
          :fields-by-key="fieldsByKey"
          :is-first="conditionIndex === 0"
          :logic="group.logic"
          :auto-open="condition.id === autoOpenId"
          @update="updateCondition(group.id, $event)"
          @toggle-logic="updateGroup(group.id, { logic: toggleLogic(group.logic) })"
          @duplicate="duplicateCondition(group.id, condition.id)"
          @remove="removeCondition(group.id, condition.id)"
        />

        <p v-if="group.conditions.length === 0" class="m-0 text-[13px] text-gray-500">
          Keine Bedingungen – diese Gruppe trifft auf alle {{ subject }} zu.
        </p>

        <div>
          <button
            type="button"
            class="-ml-1.5 inline-flex items-center gap-1.5 rounded-md px-1.5 py-1 text-sm font-medium text-indigo-600 hover:bg-indigo-50"
            @click="addCondition(group.id)"
          >
            <Plus class="h-4 w-4" />
            Bedingung hinzufügen
          </button>
        </div>
      </div>
    </template>

    <div class="flex flex-wrap items-center gap-3">
      <button
        v-if="modelValue.groups.length < MAX_GROUPS"
        type="button"
        class="inline-flex h-[34px] items-center gap-1.5 rounded-md border border-dashed border-indigo-300 bg-white px-3 text-sm font-medium text-indigo-700 hover:bg-indigo-50"
        @click="addGroup"
      >
        <Layers class="h-4 w-4" />
        Gruppe hinzufügen
      </button>
      <span class="text-[13px] text-gray-500">Gruppen bündeln Bedingungen, z. B. (A UND B) ODER (C).</span>
    </div>

    <div class="flex flex-wrap items-baseline gap-2.5 border-t border-gray-100 pt-3.5 text-sm leading-[1.9]">
      <span class="text-xs font-medium uppercase tracking-wider text-gray-500">Zeige {{ subject }}, bei denen</span>
      <span>
        <span v-if="summary.length === 0" class="text-gray-500">keine Einschränkung gilt (alle {{ subject }})</span>
        <template v-for="(token, index) in summary" :key="index">
          <span v-if="token.kind === 'logic'" class="mr-1 rounded-full bg-indigo-50 px-[7px] py-0.5 text-[11px] font-semibold tracking-wider text-indigo-700">{{ token.text }}</span>
          <span v-else-if="token.kind === 'paren'" class="font-semibold text-gray-400">{{ token.text }} </span>
          <template v-else>
            <span class="font-semibold text-gray-900">{{ token.field }} </span>
            <span v-if="token.operator" class="text-gray-500">{{ token.operator }} </span>
            <span v-if="token.value" class="font-medium text-indigo-700">{{ token.value }} </span>
          </template>
        </template>
      </span>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { ArrowUpDown, Layers, Plus, Trash2 } from 'lucide-vue-next'
import FilterConditionRow from '@/Components/Filters/FilterConditionRow.vue'
import {
  LOGIC_LABELS, createCondition, createGroup, describeDefinition, toggleLogic, uid,
} from '@/utils/filterBuilder'

// Mirrors App\Services\Filtering\FilterDefinition.
const MAX_GROUPS = 10
const MAX_CONDITIONS_PER_GROUP = 20

const props = defineProps({
  modelValue: { type: Object, required: true },
  fields: { type: Array, required: true },
  fieldsByKey: { type: Object, required: true },
  // Plural noun of the filtered records, e.g. "Mitglieder".
  subject: { type: String, required: true },
  newGroupLogic: { type: String, default: 'AND' },
})

const emit = defineEmits(['update:modelValue'])

const autoOpenId = ref(null)

const summary = computed(() => describeDefinition(props.modelValue, props.fieldsByKey))

const emitDefinition = patch => emit('update:modelValue', { ...props.modelValue, ...patch })

const mapGroups = mapper => emitDefinition({ groups: props.modelValue.groups.map(mapper) })

const updateGroup = (groupId, patch) => mapGroups(group => (group.id === groupId ? { ...group, ...patch } : group))

const mapConditions = (groupId, mapper) => mapGroups(group => (group.id === groupId ? { ...group, conditions: mapper(group.conditions) } : group))

const updateCondition = (groupId, updated) => mapConditions(groupId, conditions => conditions.map(condition => (condition.id === updated.id ? updated : condition)))

const removeCondition = (groupId, conditionId) => mapConditions(groupId, conditions => conditions.filter(condition => condition.id !== conditionId))

const duplicateCondition = (groupId, conditionId) => mapConditions(groupId, conditions => {
  if (conditions.length >= MAX_CONDITIONS_PER_GROUP) return conditions

  const index = conditions.findIndex(condition => condition.id === conditionId)
  const copy = { ...conditions[index], id: uid(), values: [...conditions[index].values] }

  return [...conditions.slice(0, index + 1), copy, ...conditions.slice(index + 1)]
})

const addCondition = groupId => {
  const condition = createCondition()
  autoOpenId.value = condition.id
  mapConditions(groupId, conditions => (conditions.length >= MAX_CONDITIONS_PER_GROUP ? conditions : [...conditions, condition]))
}

const removeGroup = groupId => emitDefinition({ groups: props.modelValue.groups.filter(group => group.id !== groupId) })

const addGroup = () => {
  const condition = createCondition()
  autoOpenId.value = condition.id
  emitDefinition({ groups: [...props.modelValue.groups, createGroup(props.newGroupLogic, [condition])] })
}

// An empty builder starts with one condition and its field picker open.
onMounted(() => {
  const hasConditions = props.modelValue.groups.some(group => group.conditions.length > 0)

  if (!hasConditions && props.modelValue.groups.length > 0) {
    addCondition(props.modelValue.groups[0].id)
  }
})
</script>
