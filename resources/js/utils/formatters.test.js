import { describe, it, expect } from 'vitest';
import {
  formatDate,
  formatDateTime,
  formatTime,
  getDisplayTimezone,
  todayInDisplayTimezone,
} from '@/utils/formatters';

/**
 * Regression tests for the timezone display bug.
 *
 * A member in a negative-offset timezone (e.g. the US) saw a contract end
 * date of 31.07. rendered as 30.07., because a bare calendar date was sent
 * as a UTC-midnight timestamp and re-interpreted in the browser's local
 * timezone. All formatters must instead render in Europe/Berlin regardless
 * of where the browser sits.
 *
 * Run with TZ forced to a US zone to simulate that browser:
 *   TZ=America/Los_Angeles npx vitest run
 * The npm "test" script sets this for you.
 */
describe('formatters – timezone handling', () => {
  it('runs under a non-Berlin timezone so the regression is actually exercised', () => {
    // Guards against the suite silently passing because it happened to run
    // in Europe/Berlin (where the bug is invisible).
    const offsetMinutes = new Date('2025-07-31T00:00:00Z').getTimezoneOffset();
    expect(offsetMinutes).not.toBe(-120); // -120 == Berlin summer (UTC+2)
  });

  it('exposes Europe/Berlin as the display timezone', () => {
    expect(getDisplayTimezone()).toBe('Europe/Berlin');
  });

  describe('formatDate', () => {
    it('keeps a bare calendar date on the same day in a US timezone', () => {
      // The core regression: must be 31, never 30.
      expect(formatDate('2025-07-31')).toBe('31.07.2025');
    });

    it('keeps the day for a UTC-midnight timestamp (legacy serialization)', () => {
      // Even if the backend still sends a full UTC timestamp, the forced
      // Berlin timezone must pull it back onto the correct calendar day.
      expect(formatDate('2025-07-31T00:00:00.000000Z')).toBe('31.07.2025');
    });

    it('returns a dash for empty input', () => {
      expect(formatDate(null)).toBe('-');
      expect(formatDate('')).toBe('-');
    });
  });

  describe('formatDateTime', () => {
    it('renders a UTC timestamp in Berlin wall-clock time', () => {
      // 12:00 UTC == 14:00 in Berlin summer, on the same day.
      expect(formatDateTime('2025-07-31T12:00:00Z')).toBe('31.07.2025, 14:00');
    });

    it('returns a dash for empty input', () => {
      expect(formatDateTime(null)).toBe('-');
    });
  });

  describe('formatTime', () => {
    it('renders a UTC timestamp in Berlin wall-clock time', () => {
      expect(formatTime('2025-07-31T12:00:00Z')).toBe('14:00');
    });

    it('returns a dash for empty input', () => {
      expect(formatTime(null)).toBe('-');
    });
  });

  describe('todayInDisplayTimezone', () => {
    it('returns a YYYY-MM-DD string', () => {
      expect(todayInDisplayTimezone()).toMatch(/^\d{4}-\d{2}-\d{2}$/);
    });

    it('is comparable lexicographically against a due_date', () => {
      const today = todayInDisplayTimezone();
      // A clearly past date must sort before today; a far-future one after.
      expect('2000-01-01' < today).toBe(true);
      expect('2999-01-01' < today).toBe(false);
    });
  });
});
