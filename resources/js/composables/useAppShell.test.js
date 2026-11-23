import { describe, it, expect } from 'vitest'
import { createAppShellState } from './useAppShell'

describe('createAppShellState', () => {
  it('starts with the default page options', () => {
    const shell = createAppShellState()

    expect(shell.pageOptions.hideHeaderUserOnMobile).toBe(false)
  })

  it('applies the options of the claiming page', () => {
    const shell = createAppShellState()

    shell.claim(Symbol('page'), { hideHeaderUserOnMobile: true })

    expect(shell.pageOptions.hideHeaderUserOnMobile).toBe(true)
  })

  it('resets options the next page does not set', () => {
    const shell = createAppShellState()
    shell.claim(Symbol('member'), { hideHeaderUserOnMobile: true })

    shell.claim(Symbol('dashboard'))

    expect(shell.pageOptions.hideHeaderUserOnMobile).toBe(false)
  })

  it('restores the defaults when the owner releases', () => {
    const shell = createAppShellState()
    const page = Symbol('page')
    shell.claim(page, { hideHeaderUserOnMobile: true })

    shell.release(page)

    expect(shell.pageOptions.hideHeaderUserOnMobile).toBe(false)
  })

  it('ignores a release from a page that no longer owns the options', () => {
    const shell = createAppShellState()
    const oldPage = Symbol('old')
    const newPage = Symbol('new')
    shell.claim(oldPage)
    shell.claim(newPage, { hideHeaderUserOnMobile: true })

    shell.release(oldPage)

    expect(shell.pageOptions.hideHeaderUserOnMobile).toBe(true)
  })
})
