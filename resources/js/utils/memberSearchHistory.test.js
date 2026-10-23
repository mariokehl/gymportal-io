import { describe, it, expect } from 'vitest'
import {
  MAX_RECENT,
  addToHistory,
  formatViewedAt,
  historyKey,
  readHistory,
  writeHistory,
} from './memberSearchHistory'

function memoryStorage(initial = {}) {
  const data = { ...initial }
  return {
    getItem: key => (key in data ? data[key] : null),
    setItem: (key, value) => { data[key] = String(value) },
    data,
  }
}

describe('historyKey', () => {
  it('separates histories per user and gym', () => {
    expect(historyKey(1, 2)).not.toBe(historyKey(1, 3))
    expect(historyKey(1, 2)).not.toBe(historyKey(4, 2))
  })
})

describe('readHistory', () => {
  it('returns an empty list for missing, invalid or non-array data', () => {
    expect(readHistory(memoryStorage(), 'k')).toEqual([])
    expect(readHistory(memoryStorage({ k: '{broken' }), 'k')).toEqual([])
    expect(readHistory(memoryStorage({ k: '{"id":1}' }), 'k')).toEqual([])
    expect(readHistory(null, 'k')).toEqual([])
  })

  it('drops malformed entries', () => {
    const storage = memoryStorage({
      k: JSON.stringify([{ id: 1, viewedAt: 10 }, { id: 'x', viewedAt: 10 }, { id: 2 }, null]),
    })

    expect(readHistory(storage, 'k')).toEqual([{ id: 1, viewedAt: 10 }])
  })

  it('survives a storage that throws', () => {
    const storage = { getItem: () => { throw new Error('denied') } }

    expect(readHistory(storage, 'k')).toEqual([])
  })
})

describe('addToHistory', () => {
  it('moves an existing member to the top without duplicating it', () => {
    const history = [{ id: 1, viewedAt: 1 }, { id: 2, viewedAt: 2 }]

    expect(addToHistory(history, 2, 5)).toEqual([{ id: 2, viewedAt: 5 }, { id: 1, viewedAt: 1 }])
  })

  it('caps the history length', () => {
    let history = []
    for (let id = 1; id <= MAX_RECENT + 3; id++) {
      history = addToHistory(history, id, id)
    }

    expect(history).toHaveLength(MAX_RECENT)
    expect(history[0].id).toBe(MAX_RECENT + 3)
  })
})

describe('writeHistory', () => {
  it('round-trips through readHistory', () => {
    const storage = memoryStorage()
    writeHistory(storage, 'k', [{ id: 7, viewedAt: 42 }])

    expect(readHistory(storage, 'k')).toEqual([{ id: 7, viewedAt: 42 }])
  })

  it('ignores storage errors', () => {
    const storage = { setItem: () => { throw new Error('quota') } }

    expect(() => writeHistory(storage, 'k', [])).not.toThrow()
  })
})

describe('formatViewedAt', () => {
  const now = new Date(2026, 9, 23, 14, 30).getTime()

  it('formats recent views relatively', () => {
    expect(formatViewedAt(now - 20 * 1000, now)).toBe('gerade eben')
    expect(formatViewedAt(now - 5 * 60000, now)).toBe('vor 5 Min.')
  })

  it('shows the time for earlier views today', () => {
    expect(formatViewedAt(new Date(2026, 9, 23, 9, 14).getTime(), now)).toBe('heute, 09:14')
  })

  it('labels yesterday and falls back to the date', () => {
    expect(formatViewedAt(new Date(2026, 9, 22, 18, 0).getTime(), now)).toBe('gestern')
    expect(formatViewedAt(new Date(2026, 9, 14, 8, 0).getTime(), now)).toBe('14.10.')
  })
})
