/**
 * Client-side mirror of App\Services\DiscountProjectionService.
 *
 * Kept in sync so the "Preisverlauf" panel can update live while the user
 * types, without a round-trip. All arithmetic runs in integer cents for the
 * same reason it does server-side: phased pricing compounds across months and
 * binary floats accumulate drift in member-facing totals.
 *
 * If you change the rules here, change DiscountProjectionService too — the
 * parity is covered by tests on both sides.
 */

export const DEFAULT_TERM_MONTHS = 12

/**
 * Convert a decimal amount to integer cents without a binary float round-trip.
 * Accepts German ("19,95") and English ("19.95") notation.
 */
export function toCents(amount) {
  if (amount === null || amount === undefined) {
    return 0
  }

  let normalized = String(amount).trim().replace(/[^0-9,.-]/g, '')

  if (normalized === '' || normalized === '-') {
    return 0
  }

  const hasComma = normalized.includes(',')
  const hasDot = normalized.includes('.')

  if (hasComma && hasDot) {
    // The last-occurring separator is the decimal separator.
    const decimalSeparator = normalized.lastIndexOf(',') > normalized.lastIndexOf('.') ? ',' : '.'
    const thousandSeparator = decimalSeparator === ',' ? '.' : ','
    normalized = normalized.split(thousandSeparator).join('')
    normalized = normalized.replace(decimalSeparator, '.')
  } else if (hasComma) {
    // Single comma: German decimal input.
    normalized = normalized.replace(',', '.')
  }

  const [rawWhole = '', rawFraction = ''] = normalized.split('.')
  const sign = rawWhole.startsWith('-') ? -1 : 1
  const whole = rawWhole.replace(/[+-]/g, '')
  const fraction = rawFraction.slice(0, 2).padEnd(2, '0')

  return sign * ((parseInt(whole || '0', 10) || 0) * 100 + (parseInt(fraction, 10) || 0))
}

/**
 * Format integer cents as a German currency string, e.g. "1.234,50 €".
 */
export function formatCents(cents) {
  return `${(cents / 100).toLocaleString('de-DE', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })} €`
}

/**
 * Discard blank/zero-length rows and coerce form input to a stable shape.
 */
function normalizePhases(phases) {
  return (phases ?? [])
    .map((phase) => ({
      durationMonths: Math.max(0, parseInt(phase.duration_months, 10) || 0),
      priceCents: Math.max(0, toCents(phase.price)),
    }))
    .filter((phase) => phase.durationMonths > 0)
}

/**
 * Term the projection is totalled over: the plan's minimum commitment when
 * set, otherwise enough months to cover every phase (falling back to a default
 * horizon) so the discounted period is never cut off.
 */
function resolveTermMonths(commitmentMonths, discountedMonths) {
  const commitment = parseInt(commitmentMonths, 10) || 0

  if (commitment > 0) {
    return Math.max(commitment, discountedMonths)
  }

  return Math.max(DEFAULT_TERM_MONTHS, discountedMonths)
}

/**
 * Build the timeline segments, totals and member saving for a set of phases.
 *
 * @param {object} options
 * @param {string|number} options.price            Regular plan price.
 * @param {string|number} options.commitmentMonths Minimum commitment, may be blank.
 * @param {Array} options.phases                   Raw form rows.
 */
export function projectDiscounts({ price, commitmentMonths, phases }) {
  const regularPriceCents = toCents(price)
  const normalized = normalizePhases(phases)

  const discountedMonths = normalized.reduce((total, phase) => total + phase.durationMonths, 0)
  const termMonths = resolveTermMonths(commitmentMonths, discountedMonths)

  const segments = []
  let monthsUsed = 0

  for (const phase of normalized) {
    if (monthsUsed >= termMonths) {
      break
    }

    const duration = Math.min(phase.durationMonths, termMonths - monthsUsed)

    segments.push({
      durationMonths: duration,
      priceCents: phase.priceCents,
      isDiscounted: true,
      startMonth: monthsUsed + 1,
      endMonth: monthsUsed + duration,
    })

    monthsUsed += duration
  }

  if (monthsUsed < termMonths) {
    segments.push({
      durationMonths: termMonths - monthsUsed,
      priceCents: regularPriceCents,
      isDiscounted: false,
      startMonth: monthsUsed + 1,
      endMonth: termMonths,
    })
  }

  const discountedTotalCents = segments.reduce(
    (total, segment) => total + segment.priceCents * segment.durationMonths,
    0
  )
  const regularTotalCents = regularPriceCents * termMonths
  const commitment = parseInt(commitmentMonths, 10) || 0

  return {
    termMonths,
    segments,
    regularTotalCents,
    discountedTotalCents,
    savingsCents: Math.max(0, regularTotalCents - discountedTotalCents),
    discountedMonths,
    exceedsTerm: commitment > 0 && discountedMonths > commitment,
  }
}
