// utils/memberStatus.js
// Gemeinsame Utilities für Member-Status, die in mehreren Komponenten verwendet werden

import {
  UserCheck, UserX, Pause, Clock, AlertTriangle
} from 'lucide-vue-next'
import { formatDate, formatDateTime, formatTime, formatRelativeTime } from './formatters'

/**
 * Status-Konfiguration mit allen relevanten Daten
 */
export const statusConfig = {
  active: {
    label: 'Aktiv',
    color: 'green',
    classes: 'bg-green-100 text-green-700',
    icon: UserCheck,
    description: 'Vollzugriff auf alle Dienste'
  },
  inactive: {
    label: 'Inaktiv',
    color: 'gray',
    classes: 'bg-gray-100 text-gray-700',
    icon: UserX,
    description: 'Kein Zugriff, keine laufenden Kosten'
  },
  paused: {
    label: 'Pausiert',
    color: 'yellow',
    classes: 'bg-yellow-100 text-yellow-700',
    icon: Pause,
    description: 'Temporär ausgesetzt'
  },
  pending: {
    label: 'Ausstehend',
    color: 'orange',
    classes: 'bg-orange-100 text-orange-700',
    icon: Clock,
    description: 'Wartet auf Aktivierung'
  },
  overdue: {
    label: 'Überfällig',
    color: 'red',
    classes: 'bg-red-100 text-red-700',
    icon: AlertTriangle,
    description: 'Zahlungen ausstehend'
  }
}

/**
 * Gibt den lokalisierten Text für einen Status zurück
 */
export function getStatusText(status) {
  return statusConfig[status]?.label || status
}

/**
 * Gibt die CSS-Klassen für einen Status-Badge zurück
 */
export function getStatusBadgeClass(status) {
  return statusConfig[status]?.classes || 'bg-gray-100 text-gray-700'
}

/**
 * Gibt das Icon für einen Status zurück
 */
export function getStatusIcon(status) {
  return statusConfig[status]?.icon || null
}

/**
 * Gibt die Farbe für einen Status zurück (für dynamische Styles)
 */
export function getStatusColor(status) {
  return statusConfig[status]?.color || 'gray'
}

/**
 * Gibt die Beschreibung für einen Status zurück
 */
export function getStatusDescription(status) {
  return statusConfig[status]?.description || ''
}

/**
 * Validiert ob ein Status-Übergang erlaubt ist
 */
export function canTransitionStatus(fromStatus, toStatus, memberData = {}) {
  // Gleicher Status ist nicht erlaubt
  if (fromStatus === toStatus) return false

  // Spezifische Validierungen
  if (toStatus === 'inactive') {
    // Prüfe auf aktive Mitgliedschaften
    if (memberData.hasActiveMembership) return false
    // Prüfe auf ausstehende Zahlungen
    if (memberData.hasPendingPayments) return false
  }

  if (toStatus === 'active' && fromStatus === 'pending') {
    // Prüfe SEPA-Mandat und Zahlungsmethode
    if (memberData.needsSepaMandate) return false
    if (!memberData.hasActivePaymentMethod) return false
  }

  // Weitere Validierungen...

  return true
}

/**
 * Gibt alle möglichen Status-Optionen zurück
 */
export function getAllStatusOptions() {
  return Object.entries(statusConfig).map(([value, config]) => ({
    value,
    ...config
  }))
}

/**
 * Export als Default-Objekt für einfachen Import
 */
export default {
  statusConfig,
  getStatusText,
  getStatusBadgeClass,
  getStatusIcon,
  getStatusColor,
  getStatusDescription,
  canTransitionStatus,
  getAllStatusOptions
}

// Re-export formatter functions for backward compatibility
export { formatDate, formatDateTime, formatTime, formatRelativeTime }
