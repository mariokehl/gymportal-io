import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'

import { registerFlashToasts, showFlashToasts } from './useFlashToasts'
import { useToast } from './useToast'

const { toasts } = useToast()

const messages = () => toasts.value.map(toast => `${toast.type}:${toast.title}`)

beforeEach(() => {
  vi.useFakeTimers()
  toasts.value = []
})

afterEach(() => {
  vi.useRealTimers()
})

describe('showFlashToasts', () => {
  it('shows every toast of the flash data with its type', () => {
    showFlashToasts({
      toasts: [
        { type: 'success', message: 'Gespeichert' },
        { type: 'error', message: 'Fehler' },
        { type: 'info', message: 'Hinweis' },
        { type: 'warning', message: 'Achtung' },
      ],
    })

    expect(messages()).toEqual(['success:Gespeichert', 'error:Fehler', 'info:Hinweis', 'warning:Achtung'])
  })

  it('ignores empty flash data and falls back to success for unknown types', () => {
    showFlashToasts({})
    showFlashToasts(undefined)
    showFlashToasts({ toasts: [{ type: 'other', message: 'Erledigt' }, { type: 'error', message: '' }] })

    expect(messages()).toEqual(['success:Erledigt'])
  })
})

describe('registerFlashToasts', () => {
  it('toasts once per flash event and can be removed again', () => {
    const listeners = []
    const router = {
      on: vi.fn((type, callback) => {
        listeners.push({ type, callback })
        return () => listeners.splice(listeners.findIndex(listener => listener.callback === callback), 1)
      }),
    }
    const fire = flash => listeners.filter(listener => listener.type === 'flash')
      .forEach(listener => listener.callback({ detail: { flash } }))

    const stop = registerFlashToasts(router)
    fire({ toasts: [{ type: 'success', message: 'Filter „Aggregatoren“ gespeichert' }] })

    expect(router.on).toHaveBeenCalledWith('flash', expect.any(Function))
    expect(messages()).toEqual(['success:Filter „Aggregatoren“ gespeichert'])

    stop()
    fire({ toasts: [{ type: 'success', message: 'Nicht mehr' }] })

    expect(messages()).toHaveLength(1)
  })
})
