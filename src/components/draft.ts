import type { HelferEvent } from '../types.ts'
import type { SettingsDraft } from './SettingsFields.vue'
import { fromDate, toDate } from '../time.ts'

export function emptyDraft(): SettingsDraft {
	return {
		title: '',
		description: '',
		location: '',
		contact: '',
		endDate: null,
		closeDate: null,
		closeAction: 'close',
		exportFolder: '',
		askEmail: true,
		emailRequired: false,
		askPhone: false,
		askGroup: false,
		groupOptions: '',
		questions: [],
		publicNames: true,
	}
}

export function draftFromEvent(event: HelferEvent): SettingsDraft {
	return {
		title: event.title,
		description: event.description,
		location: event.location,
		contact: event.contact,
		endDate: toDate(event.endDate),
		closeDate: toDate(event.closeDate),
		closeAction: event.closeAction,
		exportFolder: event.exportFolder ?? '',
		askEmail: event.askEmail,
		emailRequired: event.emailRequired,
		askPhone: event.askPhone,
		askGroup: event.askGroup,
		groupOptions: event.groupOptions.join('\n'),
		questions: event.questions.map((q) => ({ ...q })),
		publicNames: event.publicNames,
	}
}

export function draftToPayload(draft: SettingsDraft): Record<string, unknown> {
	return {
		title: draft.title,
		description: draft.description,
		location: draft.location,
		contact: draft.contact,
		endDate: fromDate(draft.endDate),
		closeDate: fromDate(draft.closeDate),
		closeAction: draft.closeAction,
		exportFolder: draft.exportFolder,
		askEmail: draft.askEmail,
		emailRequired: draft.askEmail && draft.emailRequired,
		askPhone: draft.askPhone,
		askGroup: draft.askGroup,
		groupOptions: draft.groupOptions.split('\n').map((s) => s.trim()).filter(Boolean),
		questions: draft.questions.filter((q) => q.label.trim() !== ''),
		publicNames: draft.publicNames,
	}
}
