// utils/formatters.js
// Centralized formatting utilities used across the application

/**
 * Standard date formatting (DD.MM.YYYY)
 * @param {string|Date} date - The date to format
 * @returns {string} Formatted date or '-' if no date provided
 */
export function formatDate(date) {
  if (!date) return '-'
  return new Date(date).toLocaleDateString('de-DE', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric'
  })
}

/**
 * Date and time formatting (DD.MM.YYYY, HH:MM)
 * @param {string|Date} datetime - The datetime to format
 * @returns {string} Formatted datetime or '-' if no datetime provided
 */
export function formatDateTime(datetime) {
  if (!datetime) return '-'
  return new Date(datetime).toLocaleString('de-DE', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

/**
 * Time only formatting (HH:MM)
 * @param {string|Date} datetime - The datetime to format
 * @returns {string} Formatted time or '-' if no datetime provided
 */
export function formatTime(datetime) {
  if (!datetime) return '-'
  return new Date(datetime).toLocaleTimeString('de-DE', {
    hour: '2-digit',
    minute: '2-digit'
  })
}

/**
 * Relative time formatting (e.g., "vor 2 Tagen")
 * @param {string|Date} date - The date to format
 * @returns {string} Relative time string
 */
export function formatRelativeTime(date) {
  if (!date) return ''

  const now = new Date()
  const past = new Date(date)
  const diffInSeconds = Math.floor((now - past) / 1000)

  if (diffInSeconds < 60) return 'gerade eben'
  if (diffInSeconds < 3600) return `vor ${Math.floor(diffInSeconds / 60)} Minuten`
  if (diffInSeconds < 86400) return `vor ${Math.floor(diffInSeconds / 3600)} Stunden`
  if (diffInSeconds < 2592000) return `vor ${Math.floor(diffInSeconds / 86400)} Tagen`
  if (diffInSeconds < 31536000) return `vor ${Math.floor(diffInSeconds / 2592000)} Monaten`
  return `vor ${Math.floor(diffInSeconds / 31536000)} Jahren`
}

/**
 * Relative date formatting with friendly labels (Heute, Gestern, Vor X Tagen)
 * Used in notifications and activity feeds
 * @param {string|Date} dateString - The date to format
 * @returns {string} Relative date string or formatted date if older than 7 days
 */
export function formatDateRelative(dateString) {
  const date = new Date(dateString)
  const now = new Date()
  const diffTime = Math.abs(now - date)
  const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24))

  if (diffDays === 1) {
    return 'Heute'
  } else if (diffDays === 2) {
    return 'Gestern'
  } else if (diffDays <= 7) {
    return `Vor ${diffDays - 1} Tagen`
  } else {
    return date.toLocaleDateString('de-DE', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric'
    })
  }
}

/**
 * Long date format with full month name (e.g., "15. Januar 2024")
 * @param {string|Date} dateString - The date to format
 * @returns {string} Formatted date with full month name
 */
export function formatDateLong(dateString) {
  if (!dateString) return '-'
  return new Date(dateString).toLocaleDateString('de-DE', {
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  })
}

/**
 * Short date format with abbreviated month (e.g., "15. Jan 2024")
 * @param {string|Date} dateString - The date to format
 * @returns {string} Formatted date with abbreviated month
 */
export function formatDateShort(dateString) {
  if (!dateString) return '-'
  return new Date(dateString).toLocaleDateString('de-DE', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  })
}

/**
 * Month and year formatting (MM/YY)
 * @param {string|Date} date - The date to format
 * @returns {string} Formatted month/year or '-' if no date provided
 */
export function formatMonthYear(date) {
  if (!date) return '-'
  return new Date(date).toLocaleDateString('de-DE', {
    month: '2-digit',
    year: '2-digit'
  })
}

/**
 * Format date for HTML input fields (YYYY-MM-DD)
 * Handles both ISO strings and various date formats
 * @param {string|Date} dateString - The date to format
 * @returns {string} Date in YYYY-MM-DD format or empty string
 */
export function formatDateForInput(dateString) {
  if (!dateString) return ''

  // If already in YYYY-MM-DD format, return as is
  if (/^\d{4}-\d{2}-\d{2}/.test(dateString)) {
    return dateString.split('T')[0]
  }

  // Parse the date using UTC methods to avoid timezone issues
  const date = new Date(dateString)
  if (isNaN(date.getTime())) return ''

  const year = date.getUTCFullYear()
  const month = String(date.getUTCMonth() + 1).padStart(2, '0')
  const day = String(date.getUTCDate()).padStart(2, '0')

  return `${year}-${month}-${day}`
}

/**
 * Currency formatting in EUR
 * @param {number} amount - The amount to format
 * @returns {string} Formatted currency string
 */
export function formatCurrency(amount) {
  return new Intl.NumberFormat('de-DE', {
    style: 'currency',
    currency: 'EUR'
  }).format(amount)
}

/**
 * Alias for formatCurrency - used in pricing contexts
 * @param {number} price - The price to format
 * @returns {string} Formatted price string
 */
export function formatPrice(price) {
  return formatCurrency(price)
}

/**
 * Format billing cycle to German label
 * @param {string} cycle - Billing cycle ('monthly', 'quarterly', 'yearly')
 * @returns {string} German label for the billing cycle
 */
export function formatBillingCycle(cycle) {
  const cycles = {
    monthly: 'Monat',
    quarterly: 'Quartal',
    yearly: 'Jahr'
  }
  return cycles[cycle] || cycle
}

/**
 * Format IBAN for display with spaces every 4 characters
 * @param {string} iban - The IBAN to format
 * @returns {string} Formatted IBAN with spaces
 */
export function formatIbanDisplay(iban) {
  if (!iban) return ''

  const cleanIban = iban.replace(/\s/g, '').toUpperCase()
  return cleanIban.replace(/(.{4})/g, '$1 ').trim()
}

/**
 * Export all formatters as default object for convenience
 */
export default {
  formatDate,
  formatDateTime,
  formatTime,
  formatRelativeTime,
  formatDateRelative,
  formatDateLong,
  formatDateShort,
  formatMonthYear,
  formatDateForInput,
  formatCurrency,
  formatPrice,
  formatBillingCycle,
  formatIbanDisplay
}
