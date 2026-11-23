import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { effectScope, nextTick, ref } from 'vue'
import { useDelayedFlag } from './useDelayedFlag'

let scope

const setup = (initial = false) => {
  const source = ref(initial)
  scope = effectScope()
  const flag = scope.run(() => useDelayedFlag(source, 150))
  return { source, flag }
}

beforeEach(() => vi.useFakeTimers())

afterEach(() => {
  scope?.stop()
  vi.useRealTimers()
})

describe('useDelayedFlag', () => {
  it('turns on only after the source stayed true for the delay', async () => {
    const { source, flag } = setup()

    source.value = true
    await nextTick()
    vi.advanceTimersByTime(149)
    expect(flag.value).toBe(false)

    vi.advanceTimersByTime(1)
    expect(flag.value).toBe(true)
  })

  it('never turns on for a source that is shorter true than the delay', async () => {
    const { source, flag } = setup()

    source.value = true
    await nextTick()
    vi.advanceTimersByTime(100)
    source.value = false
    await nextTick()
    vi.advanceTimersByTime(500)

    expect(flag.value).toBe(false)
  })

  it('turns off immediately', async () => {
    const { source, flag } = setup(true)

    vi.advanceTimersByTime(150)
    expect(flag.value).toBe(true)

    source.value = false
    await nextTick()
    expect(flag.value).toBe(false)
  })

  it('clears a pending timer when the scope is disposed', async () => {
    const { source, flag } = setup()

    source.value = true
    await nextTick()
    scope.stop()
    vi.advanceTimersByTime(500)

    expect(flag.value).toBe(false)
  })
})
