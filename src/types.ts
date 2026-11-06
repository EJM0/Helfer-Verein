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
	token: string | null
	timezone: string
	endDate: number | null
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
}

export interface FullEvent {
	event: HelferEvent
	shifts: Shift[]
	stations: Station[]
	slots: Slot[]
	signups: Signup[]
	publicUrl: string | null
}

export type PublicEvent = Pick<HelferEvent, 'title' | 'description' | 'location' | 'contact' | 'timezone' | 'endDate'
	| 'closeDate' | 'ended' | 'closed' | 'askEmail' | 'emailRequired' | 'askPhone' | 'askGroup' | 'groupOptions' | 'questions' | 'publicNames'>

export interface PublicData {
	event: PublicEvent
	shifts: Shift[]
	stations: Station[]
	slots: Slot[]
	signups: Signup[]
	editToken?: string
	created?: number[]
}
