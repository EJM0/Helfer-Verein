/** Formatting helpers. All timestamps are unix seconds, shown in the event's time zone. */

const locale = document.documentElement.lang || navigator.language || 'de'

function safeZone(tz: string | undefined): string | undefined {
	if (!tz) {
		return undefined
	}
	try {
		return new Intl.DateTimeFormat(locale, { timeZone: tz }).resolvedOptions().timeZone
	} catch {
		return undefined
	}
}

export function formatDay(ts: number, tz?: string): string {
	return new Intl.DateTimeFormat(locale, { weekday: 'long', day: '2-digit', month: '2-digit', year: 'numeric', timeZone: safeZone(tz) }).format(ts * 1000)
}

export function formatTime(ts: number, tz?: string): string {
	return new Intl.DateTimeFormat(locale, { hour: '2-digit', minute: '2-digit', timeZone: safeZone(tz) }).format(ts * 1000)
}

export function formatDateTime(ts: number, tz?: string): string {
	return new Intl.DateTimeFormat(locale, { weekday: 'short', day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit', timeZone: safeZone(tz) }).format(ts * 1000)
}

/**
 * Key to group shifts by calendar day in the event time zone.
 * @param ts
 * @param tz
 */
export function dayKey(ts: number, tz?: string): string {
	return new Intl.DateTimeFormat('en-CA', { year: 'numeric', month: '2-digit', day: '2-digit', timeZone: safeZone(tz) }).format(ts * 1000)
}

export function formatRange(start: number, end: number, tz?: string): string {
	const sameDay = dayKey(start, tz) === dayKey(end, tz)
	return sameDay
		? `${formatTime(start, tz)} – ${formatTime(end, tz)}`
		: `${formatTime(start, tz)} – ${formatDateTime(end, tz)}`
}

export function hours(start: number, end: number): string {
	const h = Math.round(((end - start) / 3600) * 100) / 100
	return h.toLocaleString(locale)
}

export function toDate(ts: number | null): Date | null {
	return ts ? new Date(ts * 1000) : null
}

export function fromDate(date: Date | null | undefined): number | null {
	if (!date || Number.isNaN(date.getTime())) {
		return null
	}
	return Math.floor(date.getTime() / 1000)
}

export function browserTimezone(): string {
	try {
		return Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC'
	} catch {
		return 'UTC'
	}
}
