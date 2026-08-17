import { describe, expect, it } from 'vitest'
import {
    DEFAULT_TOKEN_LIFETIME_MONTHS,
    EXPIRY_WARNING_DAYS,
    daysUntilExpiry,
    defaultTokenExpiry,
    tokenExpiryState,
    tokenExpiryWarning,
} from './scannerToken'

describe('defaultTokenExpiry', () => {
    it('suggests a date twelve months out', () => {
        expect(DEFAULT_TOKEN_LIFETIME_MONTHS).toBe(12)
        expect(defaultTokenExpiry(new Date(2026, 7, 14))).toBe('2027-08-14')
    })

    it('crosses the year boundary', () => {
        expect(defaultTokenExpiry(new Date(2026, 11, 31))).toBe('2027-12-31')
    })

    it('clamps to the last day when the target month is shorter', () => {
        // A naive +12 months from 29.02.2028 would roll into March.
        expect(defaultTokenExpiry(new Date(2028, 1, 29))).toBe('2029-02-28')
    })

    it('keeps the local day rather than shifting to UTC', () => {
        // Late evening is already the next day in UTC — the suggestion must
        // still be based on the day the operator sees.
        expect(defaultTokenExpiry(new Date(2026, 7, 14, 23, 30))).toBe('2027-08-14')
    })
})

describe('daysUntilExpiry', () => {
    const today = '2026-08-14'

    it('returns null without an expiry', () => {
        expect(daysUntilExpiry(null, today)).toBeNull()
        expect(daysUntilExpiry('', today)).toBeNull()
    })

    it('returns null for an unparsable value', () => {
        expect(daysUntilExpiry('not-a-date', today)).toBeNull()
    })

    it('counts whole days ahead', () => {
        expect(daysUntilExpiry('2026-08-24', today)).toBe(10)
    })

    it('returns zero on the day itself', () => {
        // Expiring later today must not read as already expired.
        expect(daysUntilExpiry('2026-08-14', today)).toBe(0)
    })

    it('handles a full ISO timestamp', () => {
        expect(daysUntilExpiry('2026-08-24T00:00:00.000000Z', today)).toBe(10)
    })

    it('goes negative once passed', () => {
        expect(daysUntilExpiry('2026-08-11', today)).toBe(-3)
    })
})

describe('tokenExpiryState', () => {
    const today = '2026-08-14'

    it('treats a device without an expiry as valid', () => {
        expect(tokenExpiryState(null, today)).toBe('valid')
    })

    it('stays quiet well before the deadline', () => {
        expect(tokenExpiryState('2026-12-01', today)).toBe('valid')
    })

    it('warns inside the notice period', () => {
        expect(EXPIRY_WARNING_DAYS).toBe(30)
        expect(tokenExpiryState('2026-09-01', today)).toBe('expiring')
    })

    it('warns exactly on the boundary', () => {
        expect(tokenExpiryState('2026-09-13', today)).toBe('expiring')
    })

    it('is still quiet one day outside the boundary', () => {
        expect(tokenExpiryState('2026-09-14', today)).toBe('valid')
    })

    it('reports an expired token', () => {
        expect(tokenExpiryState('2026-08-13', today)).toBe('expired')
    })
})

describe('tokenExpiryWarning', () => {
    const today = '2026-08-14'

    it('says nothing when there is nothing to warn about', () => {
        expect(tokenExpiryWarning(null, today)).toBe('')
        expect(tokenExpiryWarning('2026-12-01', today)).toBe('')
    })

    it('names the remaining days and the date', () => {
        expect(tokenExpiryWarning('2026-09-01', today)).toBe(
            'Token läuft in 18 Tagen ab (01.09.2026)'
        )
    })

    it('uses the singular for a single day', () => {
        expect(tokenExpiryWarning('2026-08-15', today)).toBe(
            'Token läuft in 1 Tag ab (15.08.2026)'
        )
    })

    it('has its own wording for the last day', () => {
        expect(tokenExpiryWarning('2026-08-14', today)).toBe(
            'Token läuft heute ab (14.08.2026)'
        )
    })

    it('reports an expired token with its date', () => {
        expect(tokenExpiryWarning('2026-08-01', today)).toBe(
            'Token abgelaufen am 01.08.2026'
        )
    })

    it('stays on one line, since the tooltip does not wrap', () => {
        const warnings = ['2026-09-01', '2026-08-14', '2026-08-01']
            .map((date) => tokenExpiryWarning(date, today))

        warnings.forEach((warning) => {
            expect(warning).not.toContain('\n')
            expect(warning.length).toBeLessThan(45)
        })
    })
})
