import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import type { FullEvent, HelferEvent, PublicData, Sharee } from './types.ts'

const base = (path: string) => generateUrl('/apps/helfer' + path)

export function errorMessage(error: unknown, fallback: string): string {
	// eslint-disable-next-line @typescript-eslint/no-explicit-any
	const message = (error as any)?.response?.data?.message
	return typeof message === 'string' && message !== '' && message !== 'Not found' ? message : fallback
}

export const adminApi = {
	async list(): Promise<HelferEvent[]> {
		return (await axios.get(base('/api/events'))).data
	},
	async create(data: Partial<HelferEvent>): Promise<FullEvent> {
		return (await axios.post(base('/api/events'), data)).data
	},
	async get(id: number): Promise<FullEvent> {
		return (await axios.get(base(`/api/events/${id}`))).data
	},
	async update(id: number, data: Partial<HelferEvent>): Promise<FullEvent> {
		return (await axios.put(base(`/api/events/${id}`), data)).data
	},
	async remove(id: number): Promise<void> {
		await axios.delete(base(`/api/events/${id}`))
	},
	async duplicate(id: number): Promise<FullEvent> {
		return (await axios.post(base(`/api/events/${id}/duplicate`))).data
	},
	async regenerateToken(id: number): Promise<FullEvent> {
		return (await axios.post(base(`/api/events/${id}/token`))).data
	},
	async removeToken(id: number): Promise<FullEvent> {
		return (await axios.delete(base(`/api/events/${id}/token`))).data
	},
	async addShift(id: number, data: object): Promise<FullEvent> {
		return (await axios.post(base(`/api/events/${id}/shifts`), data)).data
	},
	async updateShift(id: number, shiftId: number, data: object): Promise<FullEvent> {
		return (await axios.put(base(`/api/events/${id}/shifts/${shiftId}`), data)).data
	},
	async deleteShift(id: number, shiftId: number): Promise<FullEvent> {
		return (await axios.delete(base(`/api/events/${id}/shifts/${shiftId}`))).data
	},
	async addStation(id: number, data: object): Promise<FullEvent> {
		return (await axios.post(base(`/api/events/${id}/stations`), data)).data
	},
	async updateStation(id: number, stationId: number, data: object): Promise<FullEvent> {
		return (await axios.put(base(`/api/events/${id}/stations/${stationId}`), data)).data
	},
	async deleteStation(id: number, stationId: number): Promise<FullEvent> {
		return (await axios.delete(base(`/api/events/${id}/stations/${stationId}`))).data
	},
	async setSlot(id: number, shiftId: number, stationId: number, needed: number): Promise<FullEvent> {
		return (await axios.put(base(`/api/events/${id}/slots`), { shiftId, stationId, needed })).data
	},
	async addSignup(id: number, data: object): Promise<FullEvent> {
		return (await axios.post(base(`/api/events/${id}/signups`), data)).data
	},
	async updateSignup(id: number, signupId: number, data: object): Promise<FullEvent> {
		return (await axios.put(base(`/api/events/${id}/signups/${signupId}`), data)).data
	},
	async deleteSignup(id: number, signupId: number): Promise<FullEvent> {
		return (await axios.delete(base(`/api/events/${id}/signups/${signupId}`))).data
	},
	async sharees(search: string): Promise<Sharee[]> {
		return (await axios.get(base('/api/sharees'), { params: { search } })).data
	},
	async addShare(id: number, type: string, shareWith: string): Promise<FullEvent> {
		return (await axios.post(base(`/api/events/${id}/shares`), { type, shareWith })).data
	},
	async removeShare(id: number, shareId: number): Promise<FullEvent> {
		return (await axios.delete(base(`/api/events/${id}/shares/${shareId}`))).data
	},
	async addDonationList(id: number, data: object): Promise<FullEvent> {
		return (await axios.post(base(`/api/events/${id}/donation-lists`), data)).data
	},
	async updateDonationList(id: number, listId: number, data: object): Promise<FullEvent> {
		return (await axios.put(base(`/api/events/${id}/donation-lists/${listId}`), data)).data
	},
	async deleteDonationList(id: number, listId: number): Promise<FullEvent> {
		return (await axios.delete(base(`/api/events/${id}/donation-lists/${listId}`))).data
	},
	async addDonation(id: number, data: object): Promise<FullEvent> {
		return (await axios.post(base(`/api/events/${id}/donations`), data)).data
	},
	async updateDonation(id: number, donationId: number, data: object): Promise<FullEvent> {
		return (await axios.put(base(`/api/events/${id}/donations/${donationId}`), data)).data
	},
	async deleteDonation(id: number, donationId: number): Promise<FullEvent> {
		return (await axios.delete(base(`/api/events/${id}/donations/${donationId}`))).data
	},
	async exportToFolder(id: number, folder?: string): Promise<FullEvent & { path: string }> {
		return (await axios.post(base(`/api/events/${id}/export`), folder ? { folder } : {})).data
	},
	calendarUrl(id: number): string {
		return base(`/api/events/${id}/calendar.ics`)
	},
	downloadUrl(id: number): string {
		return base(`/api/events/${id}/export.xlsx`)
	},
}

export const publicApi = {
	async get(token: string): Promise<PublicData> {
		return (await axios.get(base(`/api/public/${encodeURIComponent(token)}`))).data
	},
	async signup(token: string, data: object): Promise<PublicData> {
		return (await axios.post(base(`/api/public/${encodeURIComponent(token)}/signups`), data)).data
	},
	async donate(token: string, data: object): Promise<PublicData> {
		return (await axios.post(base(`/api/public/${encodeURIComponent(token)}/donations`), data)).data
	},
	async cancelDonation(token: string, donationId: number, editToken: string): Promise<PublicData> {
		return (await axios.delete(base(`/api/public/${encodeURIComponent(token)}/donations/${donationId}`), { params: { editToken } })).data
	},
	async cancel(token: string, signupId: number, editToken: string): Promise<PublicData> {
		return (await axios.delete(base(`/api/public/${encodeURIComponent(token)}/signups/${signupId}`), { params: { editToken } })).data
	},
}
