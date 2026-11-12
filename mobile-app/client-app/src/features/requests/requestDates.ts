/** Calendar-safe YYYY-MM-DD helpers. Never treat the stored string as a timestamp. */

export const REQUEST_DATE_RE = /^\d{4}-\d{2}-\d{2}$/;

const MONTH_LABELS = [
  'January',
  'February',
  'March',
  'April',
  'May',
  'June',
  'July',
  'August',
  'September',
  'October',
  'November',
  'December',
] as const;

const MONTH_SHORT = [
  'Jan',
  'Feb',
  'Mar',
  'Apr',
  'May',
  'Jun',
  'Jul',
  'Aug',
  'Sep',
  'Oct',
  'Nov',
  'Dec',
] as const;

export type CalendarParts = { year: number; month: number; day: number };

export function parseRequestDate(value: string): CalendarParts | null {
  const trimmed = value.trim();
  if (!REQUEST_DATE_RE.test(trimmed)) return null;
  const year = Number(trimmed.slice(0, 4));
  const month = Number(trimmed.slice(5, 7));
  const day = Number(trimmed.slice(8, 10));
  if (!Number.isInteger(year) || !Number.isInteger(month) || !Number.isInteger(day)) return null;
  if (month < 1 || month > 12) return null;
  const maxDay = daysInMonth(year, month);
  if (day < 1 || day > maxDay) return null;
  return { year, month, day };
}

export function formatRequestDateYmd(year: number, month: number, day: number): string {
  return `${String(year).padStart(4, '0')}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
}

export function daysInMonth(year: number, month: number): number {
  return new Date(year, month, 0).getDate();
}

export function weekdayIndex(year: number, month: number, day = 1): number {
  return new Date(year, month - 1, day).getDay();
}

export function formatRequestDateLabel(value: string): string | null {
  const parts = parseRequestDate(value);
  if (!parts) return null;
  return `${MONTH_SHORT[parts.month - 1]} ${parts.day}, ${parts.year}`;
}

export function monthYearLabel(year: number, month: number): string {
  return `${MONTH_LABELS[month - 1]} ${year}`;
}

export function shiftMonth(year: number, month: number, delta: number): { year: number; month: number } {
  const index = year * 12 + (month - 1) + delta;
  const nextYear = Math.floor(index / 12);
  const nextMonth = (index % 12) + 1;
  return { year: nextYear, month: nextMonth };
}

export function todayParts(): CalendarParts {
  const now = new Date();
  return { year: now.getFullYear(), month: now.getMonth() + 1, day: now.getDate() };
}

/** Lexicographic compare is valid for zero-padded YYYY-MM-DD. */
export function compareRequestDates(a: string, b: string): number {
  if (a === b) return 0;
  return a < b ? -1 : 1;
}
