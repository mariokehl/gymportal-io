// Filter builder: groups of conditions combined with AND/OR, e.g. (A AND B) OR (C).
// The field registry comes from the backend (App\Services\Filtering\FilterField),
// which sanitizes every definition again before building the query. The
// operators and the completeness rules here mirror App\Services\Filtering.

export const LOGIC_LABELS = { AND: 'UND', OR: 'ODER' }

export const TYPE_LABELS = {
  text: 'Text',
  enum: 'Auswahl',
  number: 'Zahl',
  count: 'Anzahl',
  bool: 'Ja/Nein',
  date: 'Datum',
}

export const OPERATORS = {
  text: [['contains', 'enthält'], ['eq', 'ist genau'], ['starts', 'beginnt mit'], ['empty', 'ist leer'], ['notempty', 'ist nicht leer']],
  enum: [['any', 'ist eine von'], ['none', 'ist keine von']],
  number: [['eq', '='], ['neq', '≠'], ['gt', '>'], ['gte', '≥'], ['lt', '<'], ['lte', '≤'], ['between', 'zwischen']],
  count: [['has', 'mindestens 1'], ['none', 'keine (0)'], ['eq', 'genau'], ['gte', 'mindestens'], ['lte', 'höchstens'], ['between', 'zwischen']],
  bool: [['yes', 'ja'], ['no', 'nein']],
  date: [
    ['lastDays', 'in den letzten'], ['olderDays', 'vor mehr als'], ['nextDays', 'in den nächsten'],
    ['before', 'vor dem'], ['after', 'nach dem'], ['between', 'zwischen'], ['empty', 'nie / leer'], ['notempty', 'vorhanden'],
  ],
}

const DEFAULT_OPERATORS = { text: 'contains', enum: 'any', number: 'gte', count: 'has', bool: 'yes', date: 'lastDays' }

// Date operators that take a number of days instead of a calendar date.
export const DAY_OPERATORS = ['lastDays', 'olderDays', 'nextDays']

// Operators that need no value input at all.
const VALUELESS_OPERATORS = ['empty', 'notempty', 'has', 'none']

export const operatorLabel = (type, operator) => (OPERATORS[type]?.find(([key]) => key === operator) ?? [operator, operator])[1]

export const operatorOptions = (type) => (OPERATORS[type] ?? []).map(([value, label]) => ({
  value,
  label: DAY_OPERATORS.includes(value) ? `${label} … Tagen` : label,
}))

export const needsValue = (type, operator) => type !== 'bool' && type !== 'enum' && !VALUELESS_OPERATORS.includes(operator)

let sequence = 0
export const uid = () => `c${Date.now().toString(36)}${(sequence++).toString(36)}`

export const createCondition = () => ({ id: uid(), field: null, op: null, value: '', value2: '', values: [] })

export const createGroup = (logic = 'AND', conditions = []) => ({ id: uid(), logic, conditions })

export const emptyDefinition = () => ({ rootLogic: 'AND', groups: [createGroup()] })

export const toggleLogic = (logic) => (logic === 'AND' ? 'OR' : 'AND')

// Normalizes a definition from the server or a saved filter: every group and
// condition gets an id, and at least one (empty) group exists.
export function normalizeDefinition(definition) {
  const groups = (definition?.groups ?? []).map(group => ({
    id: uid(),
    logic: group.logic === 'OR' ? 'OR' : 'AND',
    conditions: (group.conditions ?? []).map(condition => ({
      ...createCondition(),
      ...condition,
      id: condition.id || uid(),
      values: [...(condition.values ?? [])],
    })),
  }))

  return { rootLogic: definition?.rootLogic === 'OR' ? 'OR' : 'AND', groups: groups.length ? groups : [createGroup()] }
}

// A fresh condition state after picking a field, with the type's default operator.
export function applyField(condition, field) {
  return {
    ...condition,
    field: field.key,
    op: DEFAULT_OPERATORS[field.type],
    value: field.type === 'date' ? '30' : '',
    value2: '',
    values: [],
  }
}

const isDigits = value => /^\d+$/.test(String(value ?? '').trim())
const isNumber = value => String(value ?? '').trim() !== '' && Number.isFinite(Number(value))
export const isIsoDate = value => /^\d{4}-\d{2}-\d{2}$/.test(String(value ?? '')) && !Number.isNaN(Date.parse(value))

export function isComplete(field, condition) {
  if (!field || !condition?.op) return false

  const { op, value, value2 } = condition

  switch (field.type) {
    case 'text': return op === 'empty' || op === 'notempty' || String(value ?? '').trim() !== ''
    case 'enum': return (condition.values ?? []).length > 0
    case 'number': return op === 'between' ? isNumber(value) && isNumber(value2) : isNumber(value)
    case 'count':
      if (op === 'has' || op === 'none') return true
      return op === 'between' ? isDigits(value) && isDigits(value2) : isDigits(value)
    case 'bool': return true
    case 'date':
      if (op === 'empty' || op === 'notempty') return true
      if (DAY_OPERATORS.includes(op)) return isDigits(value)
      return op === 'between' ? isIsoDate(value) && isIsoDate(value2) : isIsoDate(value)
    default: return false
  }
}

// Groups reduced to the conditions that take effect.
export function activeGroups(definition, fieldsByKey) {
  return (definition?.groups ?? [])
    .map(group => ({ ...group, conditions: group.conditions.filter(condition => isComplete(fieldsByKey[condition.field], condition)) }))
    .filter(group => group.conditions.length > 0)
}

export const activeConditionCount = (definition, fieldsByKey) =>
  activeGroups(definition, fieldsByKey).reduce((count, group) => count + group.conditions.length, 0)

// Key of what the filter actually does; equal keys yield equal results.
export function effectiveKey(definition, fieldsByKey) {
  const groups = activeGroups(definition, fieldsByKey).map(group => ({
    logic: group.logic,
    conditions: group.conditions.map(({ field, op, value, value2, values }) => ({ field, op, value, value2, values })),
  }))

  return JSON.stringify({ rootLogic: definition?.rootLogic ?? 'AND', groups })
}

// Structural comparison without ids, e.g. to detect changes of a saved filter.
export function sameDefinition(a, b) {
  const strip = definition => JSON.stringify({
    rootLogic: definition?.rootLogic ?? 'AND',
    groups: (definition?.groups ?? [])
      .filter(group => group.conditions.length > 0)
      .map(group => ({
        logic: group.logic,
        conditions: group.conditions
          .filter(condition => condition.field)
          .map(({ field, op, value, value2, values }) => ({
            field, op, value: String(value ?? ''), value2: String(value2 ?? ''), values: [...(values ?? [])].sort(),
          })),
      })),
  })

  return strip(a) === strip(b)
}

const formatIsoDate = value => (isIsoDate(value) ? value.split('-').reverse().join('.') : value)

// [field label, operator label, value label] of a complete condition.
export function describeCondition(field, condition) {
  const operator = operatorLabel(field.type, condition.op)
  const unit = field.unit ? ` ${field.unit}` : ''
  const { op, value, value2 } = condition

  switch (field.type) {
    case 'text':
      return [field.label, operator, ['empty', 'notempty'].includes(op) ? '' : `„${value}“`]
    case 'enum': {
      const labels = condition.values.map(selected => field.options.find(option => option.value === selected)?.label ?? selected)
      return [field.label, operator, labels.join(', ')]
    }
    case 'number':
      return [field.label, operator, op === 'between' ? `${value} und ${value2}${unit}` : `${value}${unit}`]
    case 'count':
      if (op === 'has' || op === 'none') return [field.label, operator, '']
      return [field.label, operator, op === 'between' ? `${value} und ${value2}` : `${value}`]
    case 'bool':
      return [`${field.label}:`, '', op === 'yes' ? 'ja' : 'nein']
    case 'date':
      if (op === 'empty' || op === 'notempty') return [field.label, operator, '']
      if (DAY_OPERATORS.includes(op)) return [field.label, operator, `${value} Tagen`]
      if (op === 'between') return [field.label, operator, `${formatIsoDate(value)} und ${formatIsoDate(value2)}`]
      return [field.label, operator, formatIsoDate(value)]
    default:
      return [field.label, operator, String(value ?? '')]
  }
}

// Readable tokens of the active filter for the summary sentence and the chips:
// { kind: 'field'|'op'|'value'|'logic'|'paren'|'condition', ... }.
// 'condition' tokens carry groupId/conditionId so a chip can remove its condition.
export function describeDefinition(definition, fieldsByKey) {
  const groups = activeGroups(definition, fieldsByKey)
  const tokens = []

  groups.forEach((group, groupIndex) => {
    if (groupIndex > 0) tokens.push({ kind: 'logic', text: LOGIC_LABELS[definition.rootLogic] })

    const wrap = groups.length > 1 && group.conditions.length > 1
    if (wrap) tokens.push({ kind: 'paren', text: '(' })

    group.conditions.forEach((condition, conditionIndex) => {
      if (conditionIndex > 0) tokens.push({ kind: 'logic', text: LOGIC_LABELS[group.logic] })

      const [field, operator, value] = describeCondition(fieldsByKey[condition.field], condition)
      tokens.push({ kind: 'condition', groupId: group.id, conditionId: condition.id, field, operator, value })
    })

    if (wrap) tokens.push({ kind: 'paren', text: ')' })
  })

  return tokens
}

// Request parameter of a definition; null when nothing would be filtered.
export function serializeDefinition(definition) {
  const groups = (definition?.groups ?? [])
    .map(group => ({
      logic: group.logic,
      conditions: group.conditions
        .filter(condition => condition.field)
        .map(({ id, field, op, value, value2, values }) => ({ id, field, op, value, value2, values })),
    }))
    .filter(group => group.conditions.length > 0)

  return groups.length ? JSON.stringify({ rootLogic: definition.rootLogic, groups }) : null
}
