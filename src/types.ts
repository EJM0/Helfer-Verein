export interface Owner {
	uid: string
	displayName: string
}

export interface Question {
	id: string
	label: string
	required: boolean
}

export interface HelferEvent {
	id: number
	ownerUid: string
	title: string
	description: string
	location: string
	contact: string
	contactEmail: string
	organizer: string
	startDate: number | null
	token: string | null
	timezone: string
	endDate: number | null
	signupDeadline: number | null
	signupClosed: boolean
	exportPeriodic: boolean
	closeDate: number | null
	closeAction: 'close' | 'delete'
	closedAt: number | null
	exportFolder: string | null
	exportedAt: number | null
	exportPath: string | null
	askEmail: boolean
	emailRequired: boolean
	askPhone: boolean
	askGroup: boolean
	groupOptions: string[]
	questions: Question[]
	publicNames: boolean
	createdAt: number
	updatedAt: number
	ended: boolean
	closed: boolean
	stats?: { needed: number, filled: number, signups: number }
	publicUrl?: string | null
	isOwner?: boolean
	owner?: Owner
}

export interface ShareEntry {
	id: number
	type: 'user' | 'group'
	shareWith: string
	displayName: string
}

export interface Sharee {
	type: 'user' | 'group'
	shareWith: string
	displayName: string
}

export interface Shift {
	id: number
	eventId: number
	title: string
	startTime: number
	endTime: number
}

export interface Station {
	id: number
	eventId: number
	title: string
	description: string
	sort: number
}

export interface Slot {
	id: number
	eventId: number
	shiftId: number
	stationId: number
	needed: number
}

export interface Signup {
	id: number
	eventId?: number
	slotId: number
	name: string | null
	email?: string
	phone?: string
	groupName?: string
	role: string
	comment?: string
	answers?: Record<string, string>
	createdBy?: string | null
	createdAt?: number
	/** public view: created from this browser (device cookie) */
	mine?: boolean
}

export interface DonationList {
	id: number
	eventId: number
	title: string
	description: string
	needed: number | null
	sort: number
}

export interface Donation {
	id: number
	listId: number
	item: string
	name: string | null
	email?: string
	comment?: string
	createdBy?: string | null
	mine?: boolean
}

export interface FullEvent {
	event: HelferEvent
	shifts: Shift[]
	stations: Station[]
	slots: Slot[]
	signups: Signup[]
	donationLists: DonationList[]
	donations: Donation[]
	publicUrl: string | null
	isOwner: boolean
	owner: Owner
	shares: ShareEntry[]
}

export type PublicEvent = Pick<HelferEvent, 'title' | 'description' | 'location' | 'contact' | 'contactEmail' | 'organizer' | 'startDate' | 'timezone' | 'endDate'
	| 'closeDate' | 'signupDeadline' | 'signupClosed' | 'ended' | 'closed' | 'askEmail' | 'emailRequired' | 'askPhone' | 'askGroup' | 'groupOptions' | 'questions' | 'publicNames'>

export interface PublicData {
	event: PublicEvent
	shifts: Shift[]
	stations: Station[]
	slots: Slot[]
	signups: Signup[]
	donationLists: DonationList[]
	donations: Donation[]
	editToken?: string
	created?: number[]
}
