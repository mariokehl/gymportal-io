import { describe, it, expect } from 'vitest'
import { SEPA_IBAN_COUNTRIES, MAX_FORMATTED_IBAN_LENGTH, cleanIban, ibanChecksum, validateIban } from '@/utils/iban'

// Public example IBANs (SWIFT IBAN registry / ECBS samples)
const VALID_IBANS = {
  DE: 'DE89 3704 0044 0532 0130 00',
  AT: 'AT61 1904 3002 3457 3201',
  CH: 'CH93 0076 2011 6238 5295 7',
  FR: 'FR14 2004 1010 0505 0001 3M02 606',
  GB: 'GB29 NWBK 6016 1331 9268 19',
  MT: 'MT84 MALT 0110 0001 2345 MTLC AST0 01S',
  NO: 'NO93 8601 1117 947',
  BE: 'BE68 5390 0754 7034',
  NL: 'NL91 ABNA 0417 1643 00',
  VA: 'VA59 0011 2300 0012 3456 78',
}

describe('SEPA_IBAN_COUNTRIES', () => {
  it('covers all 35 SEPA countries with an IBAN prefix of their own', () => {
    expect(Object.keys(SEPA_IBAN_COUNTRIES)).toHaveLength(35)
  })

  it('fits the longest IBAN (Malta) including spaces', () => {
    expect(MAX_FORMATTED_IBAN_LENGTH).toBe(38)
  })
})

describe('cleanIban', () => {
  it('strips whitespace and upper-cases', () => {
    expect(cleanIban(' de89 3704 ')).toBe('DE893704')
    expect(cleanIban(null)).toBe('')
  })
})

describe('validateIban', () => {
  it.each(Object.entries(VALID_IBANS))('accepts a valid %s IBAN', (country, iban) => {
    const result = validateIban(cleanIban(iban))

    expect(result.isValid).toBe(true)
    expect(result.errors).toEqual([])
    expect(result.country).toBe(country)
    expect(result.countryName).toBe(SEPA_IBAN_COUNTRIES[country].name)
  })

  it('rejects countries outside SEPA', () => {
    // Valid Turkish IBAN, but Turkey is not part of SEPA
    const result = validateIban('TR330006100519786457841326')

    expect(result.isValid).toBe(false)
    expect(result.errors).toEqual(['Es werden nur IBANs aus SEPA-Ländern unterstützt'])
  })

  it('rejects a wrong country-specific length', () => {
    const result = validateIban('FR142004101005050001302606')

    expect(result.errors).toEqual(['IBAN für Frankreich muss 27 Zeichen haben (aktuell: 26)'])
  })

  it('rejects wrong check digits', () => {
    expect(validateIban('DE88370400440532013000').errors).toEqual(['Ungültige Prüfziffer'])
  })

  it('rejects too short and malformed input', () => {
    expect(validateIban('DE89').errors).toEqual(['IBAN ist zu kurz'])
    expect(validateIban('1289370400440532013000').errors).toEqual(['Ungültiges IBAN-Format'])
  })

  it('requires a value only when requested', () => {
    expect(validateIban('').errors).toEqual([])
    expect(validateIban('', { required: true }).errors).toEqual(['IBAN ist erforderlich'])
  })
})

describe('ibanChecksum', () => {
  it('returns 1 for a valid IBAN', () => {
    expect(ibanChecksum('GB29NWBK60161331926819')).toBe(1)
  })
})
