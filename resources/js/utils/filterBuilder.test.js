import { describe, it, expect } from 'vitest'
import {
  activeConditionCount, applyField, describeCondition, describeDefinition, effectiveKey, isComplete,
  needsValue, normalizeDefinition, operatorOptions, sameDefinition, serializeDefinition,
} from '@/utils/filterBuilder'

const fields = {
  name: { key: 'name', label: 'Name', type: 'text', options: [] },
  status: { key: 'status', label: 'Status', type: 'enum', options: [{ value: 'active', label: 'Aktiv' }, { value: 'paused', label: 'Pausiert' }] },
  age: { key: 'age', label: 'Alter', type: 'number', unit: 'Jahre', options: [] },
  check_in_count: { key: 'check_in_count', label: 'Anzahl Check-Ins', type: 'count', options: [] },
  has_nfc_tag: { key: 'has_nfc_tag', label: 'Hat NFC-Tag', type: 'bool', options: [] },
  last_check_in: { key: 'last_check_in', label: 'Letzter Check-In', type: 'date', options: [] },
}

const condition = (field, op, extra = {}) => ({ id: `${field}-${op}`, field, op, value: '', value2: '', values: [], ...extra })

describe('isComplete', () => {
  it('requires a value only where the operator needs one', () => {
    expect(isComplete(fields.name, condition('name', 'contains'))).toBe(false)
    expect(isComplete(fields.name, condition('name', 'contains', { value: 'lea' }))).toBe(true)
    expect(isComplete(fields.name, condition('name', 'empty'))).toBe(true)
    expect(isComplete(fields.has_nfc_tag, condition('has_nfc_tag', 'no'))).toBe(true)
    expect(isComplete(fields.check_in_count, condition('check_in_count', 'none'))).toBe(true)
  })

  it('validates numbers, counts, days and dates', () => {
    expect(isComplete(fields.age, condition('age', 'between', { value: '25' }))).toBe(false)
    expect(isComplete(fields.age, condition('age', 'between', { value: '25', value2: '40' }))).toBe(true)
    expect(isComplete(fields.check_in_count, condition('check_in_count', 'gte', { value: '2.5' }))).toBe(false)
    expect(isComplete(fields.last_check_in, condition('last_check_in', 'olderDays', { value: '60' }))).toBe(true)
    expect(isComplete(fields.last_check_in, condition('last_check_in', 'before', { value: '60' }))).toBe(false)
    expect(isComplete(fields.last_check_in, condition('last_check_in', 'before', { value: '2026-10-01' }))).toBe(true)
  })

  it('needs at least one option for enums and a known field', () => {
    expect(isComplete(fields.status, condition('status', 'any'))).toBe(false)
    expect(isComplete(fields.status, condition('status', 'any', { values: ['active'] }))).toBe(true)
    expect(isComplete(undefined, condition('unknown', 'any'))).toBe(false)
  })
})

describe('applyField', () => {
  it('resets the condition to the default operator of the field type', () => {
    const picked = applyField(condition('name', 'eq', { value: 'x', values: ['a'] }), fields.last_check_in)

    expect(picked).toMatchObject({ field: 'last_check_in', op: 'lastDays', value: '30', value2: '', values: [] })
  })
})

describe('operator helpers', () => {
  it('labels day operators with their unit and knows valueless operators', () => {
    expect(operatorOptions('date').find(option => option.value === 'lastDays').label).toBe('in den letzten … Tagen')
    expect(needsValue('count', 'has')).toBe(false)
    expect(needsValue('enum', 'any')).toBe(false)
    expect(needsValue('number', 'gte')).toBe(true)
  })
})

describe('describing a filter', () => {
  const definition = {
    rootLogic: 'AND',
    groups: [
      { id: 'g1', logic: 'AND', conditions: [condition('status', 'any', { values: ['active', 'paused'] })] },
      { id: 'g2', logic: 'OR', conditions: [condition('check_in_count', 'none'), condition('last_check_in', 'olderDays', { value: '60' }), condition('name', 'contains')] },
    ],
  }

  it('counts only complete conditions', () => {
    expect(activeConditionCount(definition, fields)).toBe(3)
  })

  it('builds tokens with logic and parentheses for multi-condition groups', () => {
    const tokens = describeDefinition(definition, fields)

    expect(tokens.map(token => token.kind)).toEqual(['condition', 'logic', 'paren', 'condition', 'logic', 'condition', 'paren'])
    expect(tokens[0]).toMatchObject({ field: 'Status', operator: 'ist eine von', value: 'Aktiv, Pausiert', groupId: 'g1' })
    expect(tokens[1].text).toBe('UND')
    expect(tokens[4].text).toBe('ODER')
  })

  it('formats dates and booleans', () => {
    expect(describeCondition(fields.last_check_in, condition('last_check_in', 'between', { value: '2026-01-01', value2: '2026-02-01' })))
      .toEqual(['Letzter Check-In', 'zwischen', '01.01.2026 und 01.02.2026'])
    expect(describeCondition(fields.has_nfc_tag, condition('has_nfc_tag', 'no'))).toEqual(['Hat NFC-Tag:', '', 'nein'])
  })
})

describe('comparing and serializing', () => {
  it('ignores ids, incomplete placeholders and value order when comparing', () => {
    const a = { rootLogic: 'AND', groups: [{ id: 'x', logic: 'AND', conditions: [condition('status', 'any', { values: ['paused', 'active'] })] }] }
    const b = { rootLogic: 'AND', groups: [{ id: 'y', logic: 'AND', conditions: [{ ...condition('status', 'any', { values: ['active', 'paused'] }), id: 'other' }, { id: 'new', field: null }] }] }

    expect(sameDefinition(a, b)).toBe(true)
    expect(sameDefinition(a, { ...a, rootLogic: 'OR' })).toBe(false)
  })

  it('derives the same effective key while only incomplete conditions change', () => {
    const base = { rootLogic: 'AND', groups: [{ id: 'g', logic: 'AND', conditions: [condition('name', 'contains', { value: 'lea' })] }] }
    const withDraft = { ...base, groups: [{ ...base.groups[0], conditions: [...base.groups[0].conditions, condition('age', 'gte')] }] }

    expect(effectiveKey(withDraft, fields)).toBe(effectiveKey(base, fields))
  })

  it('omits the parameter when no condition has a field', () => {
    expect(serializeDefinition({ rootLogic: 'AND', groups: [{ id: 'g', logic: 'AND', conditions: [{ id: 'c', field: null }] }] })).toBeNull()
    expect(JSON.parse(serializeDefinition({ rootLogic: 'OR', groups: [{ id: 'g', logic: 'AND', conditions: [condition('name', 'empty')] }] })))
      .toEqual({ rootLogic: 'OR', groups: [{ logic: 'AND', conditions: [{ id: 'name-empty', field: 'name', op: 'empty', value: '', value2: '', values: [] }] }] })
  })

  it('normalizes server definitions and always keeps one group', () => {
    expect(normalizeDefinition({ rootLogic: 'AND', groups: [] }).groups).toHaveLength(1)

    const normalized = normalizeDefinition({ rootLogic: 'OR', groups: [{ logic: 'OR', conditions: [{ field: 'name', op: 'eq', value: 'x' }] }] })
    expect(normalized.rootLogic).toBe('OR')
    expect(normalized.groups[0].conditions[0]).toMatchObject({ field: 'name', op: 'eq', value: 'x', values: [] })
    expect(normalized.groups[0].conditions[0].id).toBeTruthy()
  })
})
