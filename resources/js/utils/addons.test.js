import { describe, it, expect } from 'vitest'
import { resolveSelectedAddonIds } from '@/utils/addons'

describe('resolveSelectedAddonIds', () => {
  it('always includes included add-ons regardless of checked state', () => {
    const result = resolveSelectedAddonIds([
      { id: 1, mode: 'included', checked: true },
      { id: 2, mode: 'included', checked: false },
    ])
    expect(result).toEqual([1, 2])
  })

  it('includes optional add-ons only when checked', () => {
    const result = resolveSelectedAddonIds([
      { id: 1, mode: 'optional', checked: true },
      { id: 2, mode: 'optional', checked: false },
    ])
    expect(result).toEqual([1])
  })

  it('combines included and selected optional add-ons', () => {
    const result = resolveSelectedAddonIds([
      { id: 10, mode: 'included', checked: true },
      { id: 20, mode: 'optional', checked: true },
      { id: 30, mode: 'optional', checked: false },
    ])
    expect(result).toEqual([10, 20])
  })

  it('returns an empty array for no add-ons or invalid input', () => {
    expect(resolveSelectedAddonIds([])).toEqual([])
    expect(resolveSelectedAddonIds(null)).toEqual([])
    expect(resolveSelectedAddonIds(undefined)).toEqual([])
  })
})
