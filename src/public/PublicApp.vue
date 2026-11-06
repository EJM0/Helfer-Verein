<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { errorMessage, publicApi } from '../api.ts'
import { dayKey, formatDateTime, formatDay, formatRange } from '../time.ts'
import type { PublicData, Shift } from '../types.ts'
import { loadOwn, loadProfile, saveOwn, saveProfile, type OwnSignup } from './storage.ts'
import { mdiAccount, mdiCheck, mdiClockOutline, mdiEmailOutline, mdiMapMarker } from '../icons.ts'

const token = loadState<string>('helfer', 'token', '')
const data = ref<PublicData | null>(null)
const loading = ref(true)
const notFound = ref(false)
const submitting = ref(false)
const selected = ref<number[]>([])
const own = ref<OwnSignup[]>(loadOwn(token))
const formEl = ref<HTMLElement | null>(null)

const profile = loadProfile()
const form = ref({
	name: profile?.name ?? '',
	email: profile?.email ?? '',
	phone: profile?.phone ?? '',
	groupName: profile?.groupName ?? '',
	comment: '',
	answers: {} as Record<string, string>,
})

const event = computed(() => data.value?.event)
const tz = computed(() => event.value?.timezone)
const writable = computed(() => !!event.value && !event.value.ended && !event.value.closed)

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

const ownIds = computed(() => new Set(own.value.map((o) => o.id)))

function rows(shift: Shift) {
	if (!data.value) {
		return []
	}
	const d = data.value
	return d.stations
		.map((station) => {
			const slot = d.slots.find((s) => s.shiftId === shift.id && s.stationId === station.id)
			if (!slot) {
				return null
			}
			const signups = d.signups.filter((s) => s.slotId === slot.id)
			return { station, slot, signups, free: Math.max(0, slot.needed - signups.length), mine: signups.some((s) => ownIds.value.has(s.id)) }
		})
		.filter((row): row is NonNullable<typeof row> => row !== null)
}

const mySignups = computed(() => {
	if (!data.value) {
		return []
	}
	const d = data.value
	return d.signups
		.filter((s) => ownIds.value.has(s.id))
		.map((signup) => {
			const slot = d.slots.find((s) => s.id === signup.slotId)
			const shift = d.shifts.find((s) => s.id === slot?.shiftId)
			const station = d.stations.find((s) => s.id === slot?.stationId)
			return { signup, shift, station }
		})
		.filter((e) => e.shift && e.station)
		.sort((a, b) => a.shift!.startTime - b.shift!.startTime)
})

function toggle(slotId: number, value: boolean) {
	selected.value = value ? [...selected.value, slotId] : selected.value.filter((id) => id !== slotId)
}

function apply(result: PublicData) {
	data.value = result
	// Forget own entries the organisers removed.
	const existing = new Set(result.signups.map((s) => s.id))
	own.value = own.value.filter((o) => existing.has(o.id))
	saveOwn(token, own.value)
	selected.value = selected.value.filter((id) => {
		const slot = result.slots.find((s) => s.id === id)
		return slot && result.signups.filter((s) => s.slotId === id).length < slot.needed
	})
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

function scrollToForm() {
	formEl.value?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

async function submit() {
	if (selected.value.length === 0) {
		showError(t('helfer', 'Bitte mindestens eine Schicht auswählen.'))
		return
	}
	submitting.value = true
	try {
		const result = await publicApi.signup(token, { slotIds: selected.value, ...form.value })
		if (result.editToken && result.created) {
			own.value = [...own.value, ...result.created.map((id) => ({ id, editToken: result.editToken! }))]
		}
		saveProfile({ name: form.value.name, email: form.value.email, phone: form.value.phone, groupName: form.value.groupName })
		selected.value = []
		form.value.comment = ''
		apply(result)
		showSuccess(t('helfer', 'Danke! Du bist eingetragen.'))
		document.getElementById('helfer-public')?.scrollTo({ top: 0, behavior: 'smooth' })
	} catch (e) {
		showError(errorMessage(e, t('helfer', 'Eintragen fehlgeschlagen.')))
		if ((e as { response?: { status?: number } })?.response?.status === 400) {
			await load()
		}
	} finally {
		submitting.value = false
	}
}

async function cancel(signupId: number) {
	const entry = own.value.find((o) => o.id === signupId)
	// eslint-disable-next-line no-alert
	if (!entry || !window.confirm(t('helfer', 'Wirklich austragen?'))) {
		return
	}
	try {
		const result = await publicApi.cancel(token, signupId, entry.editToken)
		own.value = own.value.filter((o) => o.id !== signupId)
		apply(result)
		showSuccess(t('helfer', 'Du wurdest ausgetragen.'))
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
			<h2>{{ t('helfer', 'Umfrage nicht gefunden') }}</h2>
			<p>{{ t('helfer', 'Dieser Link ist ungültig oder die Umfrage wurde geschlossen.') }}</p>
		</div>

		<template v-else>
			<header class="helfer-public__card">
				<h2>{{ event.title }}</h2>
				<p v-if="event.description" class="helfer-public__description">
					{{ event.description }}
				</p>
				<ul class="helfer-public__facts">
					<li v-if="event.location">
						<NcIconSvgWrapper :path="mdiMapMarker" :size="18" /> {{ event.location }}
					</li>
					<li v-if="event.contact">
						<NcIconSvgWrapper :path="mdiAccount" :size="18" /> {{ event.contact }}
					</li>
					<li v-if="event.endDate && writable">
						<NcIconSvgWrapper :path="mdiClockOutline" :size="18" />
						{{ t('helfer', 'Eintragen und Austragen möglich bis {date}', { date: formatDateTime(event.endDate, tz) }) }}
					</li>
				</ul>
			</header>

			<NcNoteCard v-if="event.closed" type="error" :text="t('helfer', 'Diese Umfrage ist geschlossen.')" />
			<NcNoteCard v-else-if="event.ended" type="warning" :text="t('helfer', 'Die Veranstaltung ist beendet. Eintragungen und Änderungen sind nicht mehr möglich. Bei Fragen wende dich bitte an die Organisatoren.')" />

			<section v-if="mySignups.length > 0" class="helfer-public__card helfer-public__mine">
				<h3>{{ t('helfer', 'Deine Eintragungen') }}</h3>
				<ul>
					<li v-for="{ signup, shift, station } in mySignups" :key="signup.id">
						<NcIconSvgWrapper :path="mdiCheck" :size="18" />
						<span class="helfer-public__mine-text">
							<strong>{{ station!.title }}</strong> –
							{{ formatDay(shift!.startTime, tz) }}, {{ formatRange(shift!.startTime, shift!.endTime, tz) }}
						</span>
						<NcButton v-if="writable"
							variant="tertiary"
							size="small"
							@click="cancel(signup.id)">
							{{ t('helfer', 'Austragen') }}
						</NcButton>
					</li>
				</ul>
			</section>

			<section v-for="day in days" :key="day.key" class="helfer-public__day">
				<h3>{{ day.label }}</h3>
				<article v-for="shift in day.shifts"
					v-show="rows(shift).length > 0"
					:key="shift.id"
					class="helfer-public__card helfer-public__shift">
					<header>
						<strong>{{ formatRange(shift.startTime, shift.endTime, tz) }}</strong>
						<span v-if="shift.title">{{ shift.title }}</span>
					</header>
					<div v-for="row in rows(shift)"
						:key="row.slot.id"
						class="helfer-public__row"
						:class="{ 'helfer-public__row--full': row.free === 0 }">
						<div class="helfer-public__select">
							<NcCheckboxRadioSwitch v-if="writable && row.free > 0 && !row.mine"
								:model-value="selected.includes(row.slot.id)"
								@update:model-value="(v: boolean) => toggle(row.slot.id, v)">
								<strong>{{ row.station.title }}</strong>
							</NcCheckboxRadioSwitch>
							<strong v-else class="helfer-public__station">{{ row.station.title }}</strong>
							<span class="helfer-public__free">
								<template v-if="row.mine">{{ t('helfer', 'Du bist dabei') }}</template>
								<template v-else-if="row.free === 0">{{ t('helfer', 'voll') }}</template>
								<template v-else>{{ row.free === 1 ? t('helfer', 'noch 1 Platz frei') : t('helfer', 'noch {n} Plätze frei', { n: row.free }) }}</template>
							</span>
						</div>
						<p v-if="row.station.description" class="helfer-public__hint">
							{{ row.station.description }}
						</p>
						<ul v-if="event.publicNames && row.signups.length > 0" class="helfer-public__names">
							<li v-for="signup in row.signups" :key="signup.id" :class="{ 'helfer-public__names--mine': ownIds.has(signup.id) }">
								{{ signup.name }}<em v-if="signup.role"> · {{ signup.role }}</em>
							</li>
						</ul>
						<p v-else-if="row.signups.length > 0" class="helfer-public__hint">
							{{ t('helfer', '{n} von {needed} belegt', { n: row.signups.length, needed: row.slot.needed }) }}
						</p>
					</div>
				</article>
			</section>

			<p v-if="days.length === 0" class="helfer-public__card">
				{{ t('helfer', 'Es wurden noch keine Schichten eingetragen.') }}
			</p>

			<form v-if="writable && data.slots.length > 0"
				ref="formEl"
				class="helfer-public__card helfer-public__form"
				@submit.prevent="submit">
				<h3>{{ t('helfer', 'Eintragen') }}</h3>
				<p class="helfer-public__hint">
					{{ selected.length === 0
						? t('helfer', 'Wähle oben eine oder mehrere Schichten aus.')
						: (selected.length === 1 ? t('helfer', '1 Schicht ausgewählt') : t('helfer', '{n} Schichten ausgewählt', { n: selected.length })) }}
				</p>
				<NcTextField v-model="form.name"
					:label="t('helfer', 'Dein Name')"
					required
					autocomplete="name" />
				<NcTextField v-if="event.askEmail"
					v-model="form.email"
					type="email"
					:label="t('helfer', 'E-Mail-Adresse')"
					required
					autocomplete="email" />
				<NcTextField v-if="event.askPhone"
					v-model="form.phone"
					type="tel"
					:label="t('helfer', 'Telefon (optional)')"
					autocomplete="tel" />
				<template v-if="event.askGroup">
					<label v-if="event.groupOptions.length > 0" class="helfer-public__label">
						{{ t('helfer', 'Gruppe / Abteilung / Verein') }}
						<select v-model="form.groupName" required>
							<option value="" disabled>{{ t('helfer', 'Bitte auswählen') }}</option>
							<option v-for="option in event.groupOptions" :key="option" :value="option">{{ option }}</option>
						</select>
					</label>
					<NcTextField v-else
						v-model="form.groupName"
						:label="t('helfer', 'Gruppe / Abteilung / Verein')"
						required />
				</template>
				<NcTextField v-for="question in event.questions"
					:key="question.id"
					v-model="form.answers[question.id]"
					:label="question.label + (question.required ? '' : ' ' + t('helfer', '(optional)'))"
					:required="question.required" />
				<NcTextField v-model="form.comment" :label="t('helfer', 'Kommentar (optional)')" />
				<NcButton variant="primary"
					type="submit"
					:disabled="submitting || selected.length === 0"
					wide>
					{{ t('helfer', 'Verbindlich eintragen') }}
				</NcButton>
			</form>

			<div v-if="writable && selected.length > 0" class="helfer-public__bar">
				<span>{{ selected.length === 1 ? t('helfer', '1 Schicht ausgewählt') : t('helfer', '{n} Schichten ausgewählt', { n: selected.length }) }}</span>
				<NcButton variant="primary" @click="scrollToForm">
					<template #icon>
						<NcIconSvgWrapper :path="mdiEmailOutline" />
					</template>
					{{ t('helfer', 'Weiter zum Eintragen') }}
				</NcButton>
			</div>
		</template>
	</div>
</template>

<style scoped>
.helfer-public {
	max-width: 760px;
	width: 100%;
	margin: 0 auto;
	padding: 16px 12px 96px;
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
	margin: 0 0 8px;
}

.helfer-public__day > h3 {
	margin: 16px 4px 8px;
	color: var(--color-primary-element-text, #fff);
	text-shadow: 0 0 6px rgba(0, 0, 0, 0.4);
}

.helfer-public__day {
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.helfer-public__description {
	white-space: pre-wrap;
}

.helfer-public__facts {
	margin-top: 8px;
	color: var(--color-text-maxcontrast);
}

.helfer-public__facts li, .helfer-public__mine li {
	display: flex;
	align-items: center;
	gap: 6px;
	padding: 2px 0;
}

.helfer-public__mine-text {
	flex: 1;
}

.helfer-public__shift header {
	display: flex;
	gap: 8px;
	align-items: baseline;
	flex-wrap: wrap;
	font-size: 1.1em;
	margin-bottom: 4px;
}

.helfer-public__shift header span, .helfer-public__hint, .helfer-public__free {
	color: var(--color-text-maxcontrast);
}

.helfer-public__row {
	border-top: 1px solid var(--color-border);
	padding: 6px 0;
}

.helfer-public__row--full .helfer-public__station {
	color: var(--color-text-maxcontrast);
}

.helfer-public__select {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 8px;
	min-height: 44px;
}

.helfer-public__station {
	padding-inline-start: 4px;
}

.helfer-public__hint {
	margin: 0;
	font-size: 0.95em;
}

.helfer-public__names {
	display: flex;
	flex-wrap: wrap;
	gap: 4px;
}

.helfer-public__names li {
	padding: 2px 10px;
	border-radius: var(--border-radius-pill);
	background: var(--color-background-dark);
}

.helfer-public__names li.helfer-public__names--mine {
	background: var(--color-primary-element-light);
	color: var(--color-primary-element-light-text);
}

.helfer-public__form {
	display: flex;
	flex-direction: column;
	gap: 10px;
}

.helfer-public__label {
	display: flex;
	flex-direction: column;
	gap: 4px;
}

.helfer-public__label select {
	width: 100%;
}

.helfer-public__bar {
	position: fixed;
	bottom: 0;
	inset-inline: 0;
	display: flex;
	justify-content: center;
	align-items: center;
	gap: 16px;
	padding: 10px 16px;
	background: var(--color-main-background);
	box-shadow: 0 -2px 10px var(--color-box-shadow);
	z-index: 1000;
}
</style>
