/** Things this browser remembers for the public page. Wrapped in try/catch: storage can be unavailable. */

export interface OwnSignup {
	id: number
	editToken: string
	slotId?: number
	name?: string
}

export interface Person {
	name: string
	email: string
	phone: string
	groupName: string
}

const ownKey = (token: string) => `helfer-own-${token}`
const peopleKey = 'helfer-people'

function read<T>(key: string): T[] {
	try {
		const raw = window.localStorage.getItem(key)
		const parsed = raw ? JSON.parse(raw) : []
		return Array.isArray(parsed) ? parsed : []
	} catch {
		return []
	}
}

function write(key: string, value: unknown): void {
	try {
		window.localStorage.setItem(key, JSON.stringify(value))
	} catch {
		// Private mode etc. – cancelling is then only possible via the organisers.
	}
}

export function loadOwn(token: string): OwnSignup[] {
	return read<OwnSignup>(ownKey(token)).filter((e) => typeof e?.id === 'number' && typeof e?.editToken === 'string')
}

export function saveOwn(token: string, entries: OwnSignup[]): void {
	write(ownKey(token), entries)
}

/** People entered on this device, most recent first – for quickly signing them up for more shifts. */
export function loadPeople(): Person[] {
	return read<Person>(peopleKey)
		.filter((p) => typeof p?.name === 'string' && p.name !== '')
		.map((p) => ({ name: p.name, email: p.email ?? '', phone: p.phone ?? '', groupName: p.groupName ?? '' }))
}

export function rememberPerson(person: Person): Person[] {
	const others = loadPeople().filter((p) => p.name.toLowerCase() !== person.name.toLowerCase())
	const people = [person, ...others].slice(0, 12)
	write(peopleKey, people)
	return people
}

export interface OwnDonation {
	id: number
	editToken: string
	name?: string
}

const ownDonationKey = (token: string) => `helfer-own-donations-${token}`

export function loadOwnDonations(token: string): OwnDonation[] {
	return read<OwnDonation>(ownDonationKey(token)).filter((e) => typeof e?.id === 'number' && typeof e?.editToken === 'string')
}

export function saveOwnDonations(token: string, entries: OwnDonation[]): void {
	write(ownDonationKey(token), entries)
}
