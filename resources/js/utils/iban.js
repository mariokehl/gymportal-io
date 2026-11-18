/**
 * IBAN formats of the SEPA countries supported by Mollie for direct debit
 * (lengths according to the SWIFT IBAN registry). The list intentionally
 * follows Mollie, not the full SEPA scheme, e.g. Hungary is not included.
 * See https://www.mollie.com/de/growth/sepa-lastschrift-leitfaden
 *
 * Territories without an IBAN prefix of their own are covered by their
 * parent country: Saint-Pierre-et-Miquelon uses French IBANs (FR),
 * Guernsey, Jersey and the Isle of Man use British IBANs (GB).
 */
export const SEPA_IBAN_COUNTRIES = Object.freeze({
  // EU/EEA
  AT: { length: 20, name: 'Österreich' },
  BE: { length: 16, name: 'Belgien' },
  BG: { length: 22, name: 'Bulgarien' },
  HR: { length: 21, name: 'Kroatien' },
  CY: { length: 28, name: 'Zypern' },
  CZ: { length: 24, name: 'Tschechische Republik' },
  DK: { length: 18, name: 'Dänemark' },
  EE: { length: 20, name: 'Estland' },
  FI: { length: 18, name: 'Finnland' },
  FR: { length: 27, name: 'Frankreich' },
  DE: { length: 22, name: 'Deutschland' },
  GR: { length: 27, name: 'Griechenland' },
  IS: { length: 26, name: 'Island' },
  IE: { length: 22, name: 'Irland' },
  IT: { length: 27, name: 'Italien' },
  LV: { length: 21, name: 'Lettland' },
  LI: { length: 21, name: 'Liechtenstein' },
  LT: { length: 20, name: 'Litauen' },
  LU: { length: 20, name: 'Luxemburg' },
  MT: { length: 31, name: 'Malta' },
  NL: { length: 18, name: 'Niederlande' },
  NO: { length: 15, name: 'Norwegen' },
  PL: { length: 28, name: 'Polen' },
  PT: { length: 25, name: 'Portugal' },
  RO: { length: 24, name: 'Rumänien' },
  SK: { length: 24, name: 'Slowakei' },
  SI: { length: 19, name: 'Slowenien' },
  ES: { length: 24, name: 'Spanien' },
  SE: { length: 24, name: 'Schweden' },
  // Non-EEA
  AD: { length: 24, name: 'Andorra' },
  MC: { length: 27, name: 'Monaco' },
  SM: { length: 27, name: 'San Marino' },
  CH: { length: 21, name: 'Schweiz' },
  GB: { length: 22, name: 'Vereinigtes Königreich' },
  VA: { length: 22, name: 'Vatikanstadt' },
})

const MIN_IBAN_LENGTH = Math.min(...Object.values(SEPA_IBAN_COUNTRIES).map(c => c.length))
const MAX_IBAN_LENGTH = Math.max(...Object.values(SEPA_IBAN_COUNTRIES).map(c => c.length))

/**
 * Maximum length of an IBAN formatted in groups of four (including spaces).
 */
export const MAX_FORMATTED_IBAN_LENGTH = MAX_IBAN_LENGTH + Math.ceil(MAX_IBAN_LENGTH / 4) - 1

/**
 * Removes all whitespace and upper-cases the IBAN.
 */
export function cleanIban(iban) {
  if (!iban) return ''
  return iban.replace(/\s/g, '').toUpperCase()
}

/**
 * MOD-97 remainder of the IBAN (a valid IBAN yields 1).
 */
export function ibanChecksum(iban) {
  const cleaned = cleanIban(iban)
  const rearranged = cleaned.slice(4) + cleaned.slice(0, 4)

  let remainder = 0
  for (const char of rearranged) {
    // Letters map to two-digit numbers (A=10 … Z=35)
    const digits = char >= 'A' && char <= 'Z'
      ? (char.charCodeAt(0) - 55).toString()
      : char
    for (const digit of digits) {
      remainder = (remainder * 10 + Number(digit)) % 97
    }
  }

  return remainder
}

/**
 * Validates an IBAN against the SEPA country formats and the MOD-97 check digits.
 *
 * @param {string} iban
 * @param {{ required?: boolean }} options
 * @returns {{ isValid: boolean, errors: string[], country: string, countryName: string }}
 */
export function validateIban(iban, { required = false } = {}) {
  const result = { isValid: false, errors: [], country: '', countryName: '' }

  if (!iban) {
    if (required) {
      result.errors.push('IBAN ist erforderlich')
    }
    return result
  }

  const cleaned = cleanIban(iban)

  if (cleaned.length < MIN_IBAN_LENGTH) {
    result.errors.push('IBAN ist zu kurz')
    return result
  }

  if (!/^[A-Z]{2}[0-9]{2}[A-Z0-9]+$/.test(cleaned)) {
    result.errors.push('Ungültiges IBAN-Format')
    return result
  }

  const countryCode = cleaned.slice(0, 2)
  result.country = countryCode

  const countryFormat = SEPA_IBAN_COUNTRIES[countryCode]
  if (!countryFormat) {
    result.errors.push('Es werden nur IBANs aus SEPA-Ländern unterstützt')
    return result
  }

  result.countryName = countryFormat.name

  if (cleaned.length !== countryFormat.length) {
    result.errors.push(
      `IBAN für ${countryFormat.name} muss ${countryFormat.length} Zeichen haben (aktuell: ${cleaned.length})`
    )
    return result
  }

  if (ibanChecksum(cleaned) !== 1) {
    result.errors.push('Ungültige Prüfziffer')
    return result
  }

  result.isValid = true
  return result
}
