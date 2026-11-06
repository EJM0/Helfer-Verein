<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActionLink from '@nextcloud/vue/components/NcActionLink'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcChip from '@nextcloud/vue/components/NcChip'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import SlotSignupForm from './SlotSignupForm.vue'
import DonationListCard from './DonationListCard.vue'
import { errorMessage, publicApi } from '../api.ts'
import { confirmAction } from '../confirm.ts'
import { dayKey, formatDateTime, formatDay, formatRange } from '../time.ts'
import type { Donation, PublicData, Shift, Signup } from '../types.ts'
import { loadOwn, loadOwnDonations, loadPeople, rememberPerson, saveOwn, saveOwnDonations, type OwnDonation, type OwnSignup, type Person } from './storage.ts'
import {
	mdiAccount, mdiAccountPlus, mdiCalendarExport, mdiCalendarPlus, mdiClipboardTextOutline, mdiClockOutline,
	mdiCalendar, mdiClose, mdiMapMarker,
} from '../icons.ts'

const token = loadState<string>('helfer', 'token', '')
const data = ref<PublicData | null>(null)
const loading = ref(true)
const notFound = ref(false)
const submitting = ref(false)
const openSlot = ref<number | null>(null)
const own = ref<OwnSignup[]>(loadOwn(token))
const people = ref<Person[]>(loadPeople())
const ownDonations = ref<OwnDonation[]>(loadOwnDonations(token))
const stationFilter = ref<{ id: number, label: string } | null>(null)
const onlyFree = ref(false)

const event = computed(() => data.value?.event)
const tz = computed(() => event.value?.timezone)
const writable = computed(() => !!event.value && !event.value.ended && !event.value.closed && !event.value.signupClosed)
/** Until when people can sign up or cancel: the earlier of signup deadline and end date. */
const writableUntil = computed(() => {
	const dates = [event.value?.signupDeadline, event.value?.endDate].filter((d): d is number => !!d)
	return dates.length > 0 ? Math.min(...dates) : null
})
// Own entries: remembered in this browser and/or recognised by the server via the device cookie.
const ownById = computed(() => {
	const map = new Map<number, OwnSignup>(own.value.map((o) => [o.id, o]))
	for (const signup of data.value?.signups ?? []) {
		if (signup.mine && !map.has(signup.id)) {
			map.set(signup.id, { id: signup.id, editToken: '', slotId: signup.slotId, name: signup.name ?? undefined })
		}
	}
	return map
})
const ownDonationsAll = computed(() => {
	const list = [...ownDonations.value]
	for (const donation of data.value?.donations ?? []) {
		if (donation.mine && !list.some((o) => o.id === donation.id)) {
			list.push({ id: donation.id, editToken: '', name: donation.name ?? undefined })
		}
	}
	return list
})

const days = computed(() => {
	const groups: { key: string, label: string, shifts: Shift[] }[] = []
	for (const shift of data.value?.shifts ?? []) {
		const key = dayKey(shift.startTime, tz.value)
		let group = groups.find((g) => g.key === key)
		if (!group) {
			group = { key, label: formatDay(shift.startTime, tz.value), shifts: [] }
			groups.push(group)
		}
		group.shifts.push(shift)
	}
	return groups
})

/**
 * Name to show for a signup: own entries are known locally even when names are hidden.
 * @param signup
 */
function nameOf(signup: Signup): string | null {
	return signup.name ?? ownById.value.get(signup.id)?.name ?? null
}

function rows(shift: Shift) {
	const d = data.value
	if (!d) {
		return []
	}
	return d.stations
		.map((station) => {
			const slot = d.slots.find((s) => s.shiftId === shift.id && s.stationId === station.id)
			if (!slot) {
				return null
			}
			const signups = d.signups.filter((s) => s.slotId === slot.id)
			const visible = signups.filter((s) => event.value?.publicNames || ownById.value.has(s.id))
			return {
				station,
				slot,
				signups,
				visible,
				hidden: signups.length - visible.length,
				free: Math.max(0, slot.needed - signups.length),
			}
		})
		.filter((row): row is NonNullable<typeof row> => row !== null)
		.filter((row) => !stationFilter.value || row.station.id === stationFilter.value.id)
		.filter((row) => !onlyFree.value || row.free > 0)
}

const stationOptions = computed(() => (data.value?.stations ?? []).map((s) => ({ id: s.id, label: s.title })))
const visibleDays = computed(() => days.value.filter((day) => day.shifts.some((shift) => rows(shift).length > 0)))

const mine = computed(() => {
	const d = data.value
	if (!d) {
		return []
	}
	return d.signups
		.filter((s) => ownById.value.has(s.id))
		.map((signup) => {
			const slot = d.slots.find((s) => s.id === signup.slotId)
			return {
				signup,
				name: nameOf(signup) ?? '',
				shift: d.shifts.find((s) => s.id === slot?.shiftId),
				station: d.stations.find((s) => s.id === slot?.stationId),
			}
		})
		.filter((e) => e.shift && e.station)
		.sort((a, b) => a.shift!.startTime - b.shift!.startTime || a.name.localeCompare(b.name))
})

function calendarUrl(entries: { slotId: number, name?: string | null }[]): string {
	const slots = [...new Set(entries.map((e) => e.slotId))].join(',')
	const names = entries.filter((e) => e.name).map((e) => `${e.slotId}:${e.name}`).join('|')
	return generateUrl('/apps/helfer/s/{token}/kalender.ics', { token }) + `?slots=${encodeURIComponent(slots)}`
		+ (names ? `&names=${encodeURIComponent(names)}` : '')
}

const allMineCalendarUrl = computed(() => calendarUrl(mine.value.map((e) => ({ slotId: e.signup.slotId, name: e.name }))))

function apply(result: PublicData) {
	data.value = result
	const existingDonations = new Set(result.donations.map((d) => d.id))
	ownDonations.value = ownDonations.value.filter((o) => existingDonations.has(o.id))
	saveOwnDonations(token, ownDonations.value)
	// Forget own entries the organisers removed.
	const existing = new Set(result.signups.map((s) => s.id))
	own.value = own.value.filter((o) => existing.has(o.id))
	saveOwn(token, own.value)
}

async function load() {
	try {
		apply(await publicApi.get(token))
	} catch {
		notFound.value = true
	} finally {
		loading.value = false
	}
}

async function submit(slotId: number, payload: Record<string, unknown>) {
	submitting.value = true
	try {
		const result = await publicApi.signup(token, { ...payload, slotIds: [slotId] })
		const name = String(payload.name ?? '').trim()
		if (result.editToken && result.created) {
			own.value = [...own.value, ...result.created.map((id) => ({ id, editToken: result.editToken!, slotId, name }))]
		}
		people.value = rememberPerson({
			name,
			email: String(payload.email ?? ''),
			phone: String(payload.phone ?? ''),
			groupName: String(payload.groupName ?? ''),
		})
		openSlot.value = null
		apply(result)
		showSuccess(t('helfer', '{name} ist eingetragen. Danke!', { name }))
	} catch (e) {
		showError(errorMessage(e, t('helfer', 'Eintragen fehlgeschlagen.')))
		if ((e as { response?: { status?: number } })?.response?.status === 400) {
			apply(await publicApi.get(token))
		}
	} finally {
		submitting.value = false
	}
}

async function cancel(signup: Signup) {
	const entry = ownById.value.get(signup.id)
	if (!entry) {
		return
	}
	const name = nameOf(signup) ?? ''
	if (!await confirmAction(t('helfer', 'Austragen'), t('helfer', '{name} wirklich aus dieser Schicht austragen?', { name }), t('helfer', 'Austragen'))) {
		return
	}
	try {
		const result = await publicApi.cancel(token, signup.id, entry.editToken)
		own.value = own.value.filter((o) => o.id !== signup.id)
		apply(result)
		showSuccess(t('helfer', '{name} wurde ausgetragen.', { name }))
	} catch (e) {
		showError(errorMessage(e, t('helfer', 'Austragen fehlgeschlagen.')))
	}
}

const donationCards = ref<InstanceType<typeof DonationListCard>[]>([])

async function donate(payload: Record<string, unknown>) {
	submitting.value = true
	try {
		const result = await publicApi.donate(token, payload)
		const name = String(payload.name ?? '').trim()
		if (result.editToken && result.created) {
			ownDonations.value = [...ownDonations.value, ...result.created.map((id) => ({ id, editToken: result.editToken!, name }))]
		}
		people.value = rememberPerson({ name, email: String(payload.email ?? ''), phone: '', groupName: '' })
		apply(result)
		donationCards.value.forEach((card) => card.close())
		showSuccess(t('helfer', 'Danke, {name}! „{item}“ ist eingetragen.', { name, item: String(payload.item ?? '') }))
	} catch (e) {
		showError(errorMessage(e, t('helfer', 'Eintragen fehlgeschlagen.')))
	} finally {
		submitting.value = false
	}
}

async function cancelDonation(donation: Donation) {
	const entry = ownDonationsAll.value.find((o) => o.id === donation.id)
	if (!entry || !await confirmAction(t('helfer', 'Spende austragen'), t('helfer', '„{item}“ wieder austragen?', { item: donation.item }), t('helfer', 'Austragen'))) {
		return
	}
	try {
		const result = await publicApi.cancelDonation(token, donation.id, entry.editToken)
		ownDonations.value = ownDonations.value.filter((o) => o.id !== donation.id)
		apply(result)
	} catch (e) {
		showError(errorMessage(e, t('helfer', 'Austragen fehlgeschlagen.')))
	}
}

onMounted(load)
</script>

<template>
	<div class="helfer-public">
		<div v-if="loading" class="helfer-public__center">
			<NcLoadingIcon :size="48" />
		</div>

		<div v-else-if="notFound || !data || !event" class="helfer-public__card">
			<NcEmptyContent :name="t('helfer', 'Umfrage nicht gefunden')"
				:description="t('helfer', 'Dieser Link ist ungültig oder die Umfrage wurde geschlossen.')">
				<template #icon>
					<NcIconSvgWrapper :path="mdiClipboardTextOutline" />
				</template>
			</NcEmptyContent>
		</div>

		<template v-else>
			<header class="helfer-public__card">
				<h2>{{ event.title }}</h2>
				<ul class="helfer-public__facts">
					<li v-if="event.startDate">
						<NcIconSvgWrapper :path="mdiCalendar" :size="18" /> {{ formatDateTime(event.startDate, tz) }}
					</li>
					<li v-if="event.location">
						<NcIconSvgWrapper :path="mdiMapMarker" :size="18" /> {{ event.location }}
					</li>
					<li v-if="writableUntil && writable">
						<NcIconSvgWrapper :path="mdiClockOutline" :size="18" />
						{{ t('helfer', 'Anmeldeschluss: Eintragen und Austragen möglich bis {date}', { date: formatDateTime(writableUntil, tz) }) }}
					</li>
				</ul>
				<p v-if="writable" class="helfer-public__hint">
					{{ t('helfer', 'Klicke in einer Schicht auf „Eintragen“ und gib deinen Namen ein. Du kannst auch andere Personen mit eintragen.') }}
				</p>
			</header>

			<section v-if="event.organizer || event.contact || event.contactEmail || event.description" class="helfer-public__card helfer-public__info">
				<template v-if="event.organizer || event.contact || event.contactEmail">
					<h3>{{ t('helfer', 'Information') }}</h3>
					<p v-if="event.organizer">
						{{ t('helfer', 'Veranstalter: {organizer}', { organizer: event.organizer }) }}
					</p>
					<p v-if="event.contact || event.contactEmail" class="helfer-public__contact">
						<NcIconSvgWrapper :path="mdiAccount" :size="18" />
						<span>
							{{ event.contact }}
							<a v-if="event.contactEmail" :href="`mailto:${event.contactEmail}`">
								<template v-if="event.contact">({{ event.contactEmail }})</template>
								<template v-else>{{ event.contactEmail }}</template>
							</a>
						</span>
					</p>
				</template>
				<template v-if="event.description">
					<h3>{{ t('helfer', 'Bemerkungen') }}</h3>
					<p class="helfer-public__description">
						{{ event.description }}
					</p>
				</template>
			</section>

			<NcNoteCard v-if="event.closed" type="error" :text="t('helfer', 'Diese Umfrage ist geschlossen.')" />
			<NcNoteCard v-else-if="event.signupClosed && !event.ended" type="warning" :text="t('helfer', 'Der Anmeldeschluss ist vorbei. Eintragungen und Änderungen sind nicht mehr möglich. Bei Fragen wende dich bitte an die Organisatoren.')" />
			<NcNoteCard v-else-if="event.ended" type="warning" :text="t('helfer', 'Die Veranstaltung ist beendet. Eintragungen und Änderungen sind nicht mehr möglich. Bei Fragen wende dich bitte an die Organisatoren.')" />

			<section v-if="mine.length > 0" class="helfer-public__card helfer-public__mine">
				<div class="helfer-public__mine-head">
					<h3>{{ t('helfer', 'Von diesem Gerät eingetragen') }}</h3>
					<NcButton :href="allMineCalendarUrl" download variant="secondary">
						<template #icon>
							<NcIconSvgWrapper :path="mdiCalendarExport" />
						</template>
						{{ t('helfer', 'Alle in den Kalender') }}
					</NcButton>
				</div>
				<ul>
					<li v-for="entry in mine" :key="entry.signup.id">
						<div class="helfer-public__mine-text">
							<strong>{{ entry.name }}</strong>
							<span>{{ entry.station!.title }} · {{ formatDay(entry.shift!.startTime, tz) }}, {{ formatRange(entry.shift!.startTime, entry.shift!.endTime, tz) }}</span>
						</div>
						<NcActions :force-name="false">
							<NcActionLink :href="calendarUrl([{ slotId: entry.signup.slotId, name: entry.name }])" download>
								<template #icon>
									<NcIconSvgWrapper :path="mdiCalendarPlus" />
								</template>
								{{ t('helfer', 'In den Kalender') }}
							</NcActionLink>
							<NcActionButton v-if="writable" close-after-click @click="cancel(entry.signup)">
								<template #icon>
									<NcIconSvgWrapper :path="mdiClose" />
								</template>
								{{ t('helfer', 'Austragen') }}
							</NcActionButton>
						</NcActions>
					</li>
				</ul>
			</section>

			<div v-for="list in data.donationLists" :key="list.id" class="helfer-public__card">
				<DonationListCard ref="donationCards"
					:list="list"
					:donations="data.donations.filter((d) => d.listId === list.id)"
					:own="ownDonationsAll"
					:event="event"
					:writable="writable"
					:submitting="submitting"
					:people="people"
					@submit="donate"
					@cancel="cancelDonation" />
			</div>

			<section v-if="data.stations.length > 1 || writable" class="helfer-public__card helfer-public__filter">
				<h3>{{ t('helfer', 'Filter') }}</h3>
				<NcSelect v-if="data.stations.length > 1"
					v-model="stationFilter"
					:options="stationOptions"
					:input-label="t('helfer', 'Stand / Aufgabe')"
					:placeholder="t('helfer', 'Alle Stände')" />
				<NcCheckboxRadioSwitch v-if="writable" v-model="onlyFree" type="switch">
					{{ t('helfer', 'Nur freie Plätze anzeigen') }}
				</NcCheckboxRadioSwitch>
			</section>

			<section v-for="day in visibleDays" :key="day.key" class="helfer-public__day">
				<h3>{{ day.label }}</h3>
				<article v-for="shift in day.shifts"
					v-show="rows(shift).length > 0"
					:key="shift.id"
					class="helfer-public__card helfer-public__shift">
					<header>
						<strong>{{ formatRange(shift.startTime, shift.endTime, tz) }}</strong>
						<span v-if="shift.title">{{ shift.title }}</span>
					</header>
					<div v-for="row in rows(shift)" :key="row.slot.id" class="helfer-public__row">
						<div class="helfer-public__station">
							<strong>{{ row.station.title }}</strong>
							<span class="helfer-public__free">
								<template v-if="row.free === 0">{{ t('helfer', 'voll') }}</template>
								<template v-else>{{ row.free === 1 ? t('helfer', 'noch 1 Platz frei') : t('helfer', 'noch {n} Plätze frei', { n: row.free }) }}</template>
							</span>
						</div>
						<p v-if="row.station.description" class="helfer-public__hint">
							{{ row.station.description }}
						</p>
						<div class="helfer-public__names">
							<template v-for="signup in row.visible" :key="signup.id">
								<NcChip v-if="ownById.has(signup.id)"
									class="helfer-chip-menu"
									:text="nameOf(signup) ?? ''"
									variant="primary"
									no-close>
									<template #actions>
										<NcActionLink :href="calendarUrl([{ slotId: row.slot.id, name: nameOf(signup) }])" download>
											<template #icon>
												<NcIconSvgWrapper :path="mdiCalendarPlus" />
											</template>
											{{ t('helfer', 'In den Kalender') }}
										</NcActionLink>
										<NcActionButton v-if="writable" close-after-click @click="cancel(signup)">
											<template #icon>
												<NcIconSvgWrapper :path="mdiClose" />
											</template>
											{{ t('helfer', 'Austragen') }}
										</NcActionButton>
									</template>
								</NcChip>
								<NcChip v-else
									:text="(nameOf(signup) ?? '') + (signup.role ? ' · ' + signup.role : '')"
									variant="tertiary"
									no-close />
							</template>
							<span v-if="row.hidden > 0" class="helfer-public__hint">
								{{ row.hidden === 1 ? t('helfer', '1 weitere Person') : t('helfer', '{n} weitere Personen', { n: row.hidden }) }}
							</span>
							<NcButton v-if="writable && row.free > 0 && openSlot !== row.slot.id"
								size="small"
								variant="tertiary"
								@click="openSlot = row.slot.id">
								<template #icon>
									<NcIconSvgWrapper :path="mdiAccountPlus" :size="18" />
								</template>
								{{ t('helfer', 'Eintragen') }}
							</NcButton>
						</div>
						<SlotSignupForm v-if="writable && openSlot === row.slot.id"
							:event="event"
							:people="people"
							:taken-names="row.signups.map((s) => nameOf(s) ?? '').filter(Boolean)"
							:submitting="submitting"
							@submit="(payload) => submit(row.slot.id, payload)"
							@cancel="openSlot = null" />
					</div>
				</article>
			</section>

			<p v-if="days.length === 0" class="helfer-public__card">
				{{ t('helfer', 'Es wurden noch keine Schichten eingetragen.') }}
			</p>
			<p v-else-if="visibleDays.length === 0" class="helfer-public__card">
				{{ t('helfer', 'Keine passenden Schichten gefunden.') }}
			</p>
		</template>
	</div>
</template>

<style scoped>
.helfer-public {
	max-width: 760px;
	width: 100%;
	margin: 0 auto;
	padding: 16px 12px 48px;
	display: flex;
	flex-direction: column;
	gap: 12px;
	box-sizing: border-box;
}

.helfer-public__center {
	display: flex;
	justify-content: center;
	padding: 48px;
}

.helfer-public__card {
	background: var(--color-main-background);
	color: var(--color-main-text);
	border-radius: var(--border-radius-large);
	padding: 16px;
	box-shadow: 0 0 10px var(--color-box-shadow);
}

.helfer-public h2 {
	margin: 0 0 8px;
	font-size: 1.6em;
	font-weight: bold;
}

.helfer-public h3 {
	font-weight: bold;
	font-size: 1.15em;
	margin: 0;
}

.helfer-public__day {
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.helfer-public__day > h3 {
	margin: 16px 4px 0;
	color: var(--color-background-plain-text, #fff);
	text-shadow: 0 0 6px rgba(0, 0, 0, 0.4);
}

.helfer-public__description {
	white-space: pre-wrap;
	margin: 0;
}

.helfer-public__info, .helfer-public__filter {
	display: flex;
	flex-direction: column;
	gap: 6px;
}

.helfer-public__info h3, .helfer-public__filter h3 {
	color: var(--color-primary-element);
}

.helfer-public__info h3:not(:first-child) {
	margin-top: 12px;
}

.helfer-public__info p {
	margin: 0;
}

.helfer-public__contact {
	display: flex;
	align-items: center;
	gap: 6px;
}

.helfer-public__contact a {
	text-decoration: underline;
}

.helfer-public__facts {
	margin-top: 8px;
	color: var(--color-text-maxcontrast);
}

.helfer-public__facts li {
	display: flex;
	align-items: center;
	gap: 6px;
	padding: 2px 0;
}

.helfer-public__mine-head {
	display: flex;
	justify-content: space-between;
	align-items: center;
	gap: 8px;
	flex-wrap: wrap;
	margin-bottom: 8px;
}

.helfer-public__mine li {
	display: flex;
	align-items: center;
	gap: 8px;
	padding: 4px 0;
	border-top: 1px solid var(--color-border);
}

.helfer-public__mine-text {
	flex: 1;
	display: flex;
	flex-direction: column;
	min-width: 0;
}

.helfer-public__mine-text span, .helfer-public__shift header span, .helfer-public__hint, .helfer-public__free {
	color: var(--color-text-maxcontrast);
}

.helfer-public__shift header {
	display: flex;
	gap: 8px;
	align-items: baseline;
	flex-wrap: wrap;
	font-size: 1.1em;
	margin-bottom: 4px;
}

.helfer-public__row {
	border-top: 1px solid var(--color-border);
	padding: 8px 0;
}

.helfer-public__station {
	display: flex;
	align-items: baseline;
	justify-content: space-between;
	gap: 8px;
}

.helfer-public__hint {
	margin: 4px 0 0;
	font-size: 0.95em;
}

.helfer-public__names {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: 6px;
	margin-top: 6px;
}
</style>
