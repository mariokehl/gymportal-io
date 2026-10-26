import { describe, it, expect } from 'vitest'

import { resolveTab, urlWithTab } from './tabQuery'

const tabs = ['gym', 'team', 'emails']

describe('resolveTab', () => {
  it('picks the tab named in the query string', () => {
    expect(resolveTab('?tab=emails', tabs, 'gym')).toBe('emails')
  })

  it('falls back when no tab is given', () => {
    expect(resolveTab('', tabs, 'gym')).toBe('gym')
  })

  it('falls back for a tab the page does not offer', () => {
    expect(resolveTab('?tab=statistics', tabs, 'gym')).toBe('gym')
  })
})

describe('urlWithTab', () => {
  it('sets the tab and keeps other parameters', () => {
    expect(urlWithTab('https://app.test/settings?foo=1', 'emails', 'gym'))
      .toBe('/settings?foo=1&tab=emails')
  })

  it('drops the parameter for the default tab', () => {
    expect(urlWithTab('https://app.test/settings?tab=emails', 'gym', 'gym'))
      .toBe('/settings')
  })

  it('keeps the hash', () => {
    expect(urlWithTab('https://app.test/settings#top', 'team', 'gym'))
      .toBe('/settings?tab=team#top')
  })
})
