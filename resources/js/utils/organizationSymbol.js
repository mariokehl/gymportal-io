// Rendering rules for the organisation symbol shown in the sidebar, the
// organization switcher and the settings preview. Kept in one place so every
// surface renders an organisation identically.

export const DEFAULT_SYMBOL_COLOR = '#4f46e5'

export const SYMBOL_TYPE_INITIAL = 'initial'
export const SYMBOL_TYPE_EMOJI = 'emoji'

// The palette offered in the settings form. Values mirror the Tailwind 600
// steps the rest of the backend uses.
export const SYMBOL_COLORS = [
    { name: 'Indigo', value: '#4f46e5' },
    { name: 'Blau', value: '#2563eb' },
    { name: 'Cyan', value: '#0891b2' },
    { name: 'Grün', value: '#059669' },
    { name: 'Limette', value: '#65a30d' },
    { name: 'Amber', value: '#d97706' },
    { name: 'Orange', value: '#ea580c' },
    { name: 'Rot', value: '#dc2626' },
    { name: 'Pink', value: '#db2777' },
    { name: 'Violett', value: '#7c3aed' },
    { name: 'Schiefer', value: '#475569' },
    { name: 'Anthrazit', value: '#111827' },
]

// Curated emoji set: fitness disciplines, achievements and location markers.
export const SYMBOL_EMOJIS = [
    '🏋️', '💪', '🤸', '🥊', '🧘', '🏃', '🚴', '🏊',
    '⚽', '🏆', '🥇', '🎯', '🔥', '⚡', '⭐', '🌊',
    '🌲', '🍀', '🏙️', '🏢', '📍', '🧭', '🛡️', '🎽',
]

/**
 * Normalize whatever the backend shared for an organisation into the values a
 * symbol tile needs. Tolerates organisations without a symbol payload so the
 * switcher keeps working while props are still being rolled out.
 */
export function resolveSymbol(organization) {
    const symbol = organization?.symbol ?? {}
    const name = organization?.name ?? ''

    const emoji = symbol.emoji || null
    const type = symbol.type === SYMBOL_TYPE_EMOJI && emoji
        ? SYMBOL_TYPE_EMOJI
        : SYMBOL_TYPE_INITIAL

    const initial = symbol.initial || name.trim().charAt(0).toUpperCase() || '?'

    return {
        type,
        color: symbol.color || DEFAULT_SYMBOL_COLOR,
        content: type === SYMBOL_TYPE_EMOJI ? emoji : initial,
    }
}

/**
 * Inline styles for a symbol tile. The colour comes from operator input, so it
 * cannot live in a Tailwind class and is bound as a style instead.
 */
export function symbolTileStyle(organization) {
    const { color } = resolveSymbol(organization)

    return {
        backgroundColor: color,
        color: '#ffffff',
    }
}
