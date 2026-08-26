import { describe, it, expect } from 'vitest'
import {
    DEFAULT_SYMBOL_COLOR,
    SYMBOL_COLORS,
    SYMBOL_EMOJIS,
    resolveSymbol,
    symbolTileStyle,
} from './organizationSymbol'

describe('resolveSymbol', () => {
    it('renders the emoji when the organisation uses one', () => {
        const symbol = resolveSymbol({
            name: 'FitZone München',
            symbol: { type: 'emoji', emoji: '🥊', color: '#0891b2', initial: 'F' },
        })

        expect(symbol.type).toBe('emoji')
        expect(symbol.content).toBe('🥊')
        expect(symbol.color).toBe('#0891b2')
    })

    it('renders the initial when the organisation uses one', () => {
        const symbol = resolveSymbol({
            name: 'FitZone Berlin',
            symbol: { type: 'initial', emoji: null, color: '#4f46e5', initial: 'F' },
        })

        expect(symbol.type).toBe('initial')
        expect(symbol.content).toBe('F')
    })

    it('falls back to the initial when the emoji is missing', () => {
        const symbol = resolveSymbol({
            name: 'Studio Nord',
            symbol: { type: 'emoji', emoji: null, color: '#4f46e5', initial: 'S' },
        })

        expect(symbol.type).toBe('initial')
        expect(symbol.content).toBe('S')
    })

    it('derives the initial from the name when the backend shared no symbol', () => {
        const symbol = resolveSymbol({ name: 'urban gym' })

        expect(symbol.content).toBe('U')
        expect(symbol.color).toBe(DEFAULT_SYMBOL_COLOR)
    })

    it('stays renderable for an unknown organisation', () => {
        expect(resolveSymbol(undefined).content).toBe('?')
        expect(resolveSymbol({ name: '' }).content).toBe('?')
    })

    it('uses the default colour when none is set', () => {
        expect(resolveSymbol({ name: 'A', symbol: { color: '' } }).color).toBe(DEFAULT_SYMBOL_COLOR)
    })
})

describe('symbolTileStyle', () => {
    it('binds the organisation colour as the tile background', () => {
        expect(symbolTileStyle({ name: 'A', symbol: { color: '#dc2626' } })).toEqual({
            backgroundColor: '#dc2626',
            color: '#ffffff',
        })
    })
})

describe('palettes', () => {
    it('offers the default colour as a choice', () => {
        expect(SYMBOL_COLORS.map(color => color.value)).toContain(DEFAULT_SYMBOL_COLOR)
    })

    it('offers a unique set of emojis', () => {
        expect(new Set(SYMBOL_EMOJIS).size).toBe(SYMBOL_EMOJIS.length)
    })
})
