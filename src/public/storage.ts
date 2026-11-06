export interface OwnSignup {
	id: number
	editToken: string
}

const key = (token: string) => `helfer-own-${token}`

export function loadOwn(token: string): OwnSignup[] {
	try {
		const raw = window.localStorage.getItem(key(token))
		const parsed = raw ? JSON.parse(raw) : []
		return Array.isArray(parsed) ? parsed.filter((e) => typeof e?.id === 'number' && typeof e?.editToken === 'string') : []
	} catch {
		return []
	}
}

export function saveOwn(token: string, entries: OwnSignup[]): void {
	try {
		window.localStorage.setItem(key(token), JSON.stringify(entries))
	} catch {
		// Private mode etc. – cancelling is then only possible via the organisers.
	}
}

const profileKey = 'helfer-profile'

export interface Profile {
	name: string
	email: string
	phone: string
	groupName: string
}

export function loadProfile(): Profile | null {
	try {
		const raw = window.localStorage.getItem(profileKey)
		return raw ? { name: '', email: '', phone: '', groupName: '', ...JSON.parse(raw) } : null
	} catch {
		return null
	}
}

export function saveProfile(profile: Profile): void {
	try {
		window.localStorage.setItem(profileKey, JSON.stringify(profile))
	} catch {
		// ignore
	}
}
