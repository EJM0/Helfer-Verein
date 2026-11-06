<script setup lang="ts">
import { computed, ref } from 'vue'
import { showError } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import ShiftDialog from './ShiftDialog.vue'
import StationDialog from './StationDialog.vue'
import { adminApi, errorMessage } from '../api.ts'
import { confirmAction } from '../confirm.ts'
import { formatDay, formatRange, hours, toDate } from '../time.ts'
import type { FullEvent, Shift, Station } from '../types.ts'
import { mdiDelete, mdiPencil, mdiPlus } from '../icons.ts'

const props = defineProps<{ data: FullEvent }>()
const emit = defineEmits<{ update: [data: FullEvent] }>()

const tz = computed(() => props.data.event.timezone)
const id = computed(() => props.data.event.id)

const shiftDialog = ref<{ shift: Shift | null, start: Date | null, end: Date | null } | null>(null)
const stationDialog = ref<{ station: Station | null } | null>(null)
const fillText = ref('1')
const fillValue = computed(() => Math.max(0, Math.min(1000, parseInt(String(fillText.value) || '0', 10) || 0)))

function slotFor(shiftId: number, stationId: number) {
	return props.data.slots.find((s) => s.shiftId === shiftId && s.stationId === stationId)
}

function takenFor(shiftId: number, stationId: number) {
	const slot = slotFor(shiftId, stationId)
	return slot ? props.data.signups.filter((s) => s.slotId === slot.id).length : 0
}

async function run(promise: Promise<FullEvent>, fallback: string): Promise<FullEvent | null> {
	try {
		const data = await promise
		emit('update', data)
		return data
	} catch (e) {
		showError(errorMessage(e, fallback))
		return null
	}
}

function newShift() {
	const last = props.data.shifts[props.data.shifts.length - 1]
	if (last) {
		const duration = last.endTime - last.startTime
		shiftDialog.value = { shift: null, start: toDate(last.endTime), end: toDate(last.endTime + duration) }
	} else if (props.data.event.startDate) {
		const start = toDate(props.data.event.startDate)!
		shiftDialog.value = { shift: null, start, end: new Date(start.getTime() + 2 * 3600 * 1000) }
	} else {
		const start = new Date()
		start.setDate(start.getDate() + 7)
		start.setHours(10, 0, 0, 0)
		shiftDialog.value = { shift: null, start, end: new Date(start.getTime() + 2 * 3600 * 1000) }
	}
}

function editShift(shift: Shift) {
	shiftDialog.value = { shift, start: toDate(shift.startTime), end: toDate(shift.endTime) }
}

async function saveShift(data: { title: string, startTime: number | null, endTime: number | null }) {
	const current = shiftDialog.value
	if (!current) {
		return
	}
	const ok = current.shift
		? await run(adminApi.updateShift(id.value, current.shift.id, data), t('helfer', 'Schicht konnte nicht gespeichert werden.'))
		: await run(adminApi.addShift(id.value, data), t('helfer', 'Schicht konnte nicht angelegt werden.'))
	if (ok) {
		shiftDialog.value = null
		// A new shift needs one helper at every station by default.
		const shift = current.shift ? null : ok.shifts.reduce<Shift | null>((a, b) => (!a || b.id > a.id ? b : a), null)
		if (shift) {
			for (const station of ok.stations) {
				await run(adminApi.setSlot(id.value, shift.id, station.id, 1), t('helfer', 'Speichern fehlgeschlagen.'))
			}
		}
	}
}

async function deleteShift(shift: Shift) {
	const count = props.data.slots.filter((s) => s.shiftId === shift.id)
		.reduce((sum, slot) => sum + props.data.signups.filter((s) => s.slotId === slot.id).length, 0)
	const question = count > 0
		? t('helfer', 'Schicht löschen? {n} eingetragene Helfer werden dabei entfernt.', { n: count })
		: t('helfer', 'Schicht löschen?')
	if (await confirmAction(t('helfer', 'Schicht löschen'), question, t('helfer', 'Löschen'))) {
		await run(adminApi.deleteShift(id.value, shift.id), t('helfer', 'Schicht konnte nicht gelöscht werden.'))
	}
}

async function saveStation(data: { title: string, description: string }) {
	const current = stationDialog.value
	if (!current) {
		return
	}
	const isNew = !current.station
	const ok = current.station
		? await run(adminApi.updateStation(id.value, current.station.id, data), t('helfer', 'Stand konnte nicht gespeichert werden.'))
		: await run(adminApi.addStation(id.value, data), t('helfer', 'Stand konnte nicht angelegt werden.'))
	if (ok) {
		stationDialog.value = null
		// A new station needs one helper in every shift by default.
		const station = isNew ? ok.stations.reduce<Station | null>((a, b) => (!a || b.id > a.id ? b : a), null) : null
		if (station) {
			for (const shift of ok.shifts) {
				await run(adminApi.setSlot(id.value, shift.id, station.id, 1), t('helfer', 'Speichern fehlgeschlagen.'))
			}
		}
	}
}

async function deleteStation(station: Station) {
	const count = props.data.slots.filter((s) => s.stationId === station.id)
		.reduce((sum, slot) => sum + props.data.signups.filter((s) => s.slotId === slot.id).length, 0)
	const question = count > 0
		? t('helfer', '„{title}“ löschen? {n} eingetragene Helfer werden dabei entfernt.', { title: station.title, n: count })
		: t('helfer', '„{title}“ löschen?', { title: station.title })
	if (await confirmAction(t('helfer', 'Stand löschen'), question, t('helfer', 'Löschen'))) {
		await run(adminApi.deleteStation(id.value, station.id), t('helfer', 'Stand konnte nicht gelöscht werden.'))
	}
}

async function setNeeded(shiftId: number, stationId: number, raw: string) {
	const needed = Math.max(0, Math.min(1000, parseInt(raw || '0', 10) || 0))
	const slot = slotFor(shiftId, stationId)
	if ((slot?.needed ?? 0) === needed) {
		return
	}
	await run(adminApi.setSlot(id.value, shiftId, stationId, needed), t('helfer', 'Speichern fehlgeschlagen.'))
}

async function fillAll() {
	for (const station of props.data.stations) {
		for (const shift of props.data.shifts) {
			const slot = slotFor(shift.id, station.id)
			if (!slot || slot.needed !== fillValue.value) {
				if (fillValue.value === 0 && takenFor(shift.id, station.id) > 0) {
					continue
				}
				await run(adminApi.setSlot(id.value, shift.id, station.id, fillValue.value), t('helfer', 'Speichern fehlgeschlagen.'))
			}
		}
	}
}
</script>

<template>
	<div class="structure">
		<div class="structure__columns">
			<section>
				<h3>{{ t('helfer', 'Schichten (Zeiten)') }}</h3>
				<ul class="structure__list">
					<li v-for="shift in data.shifts" :key="shift.id">
						<div class="structure__item">
							<strong>{{ shift.title || formatRange(shift.startTime, shift.endTime, tz) }}</strong>
							<span>{{ formatDay(shift.startTime, tz) }}, {{ formatRange(shift.startTime, shift.endTime, tz) }}
								({{ t('helfer', '{h} Std.', { h: hours(shift.startTime, shift.endTime) }) }})</span>
						</div>
						<NcButton variant="tertiary" :aria-label="t('helfer', 'Bearbeiten')" @click="editShift(shift)">
							<template #icon>
								<NcIconSvgWrapper :path="mdiPencil" />
							</template>
						</NcButton>
						<NcButton variant="tertiary" :aria-label="t('helfer', 'Löschen')" @click="deleteShift(shift)">
							<template #icon>
								<NcIconSvgWrapper :path="mdiDelete" />
							</template>
						</NcButton>
					</li>
				</ul>
				<NcButton @click="newShift">
					<template #icon>
						<NcIconSvgWrapper :path="mdiPlus" />
					</template>
					{{ t('helfer', 'Schicht hinzufügen') }}
				</NcButton>
			</section>

			<section>
				<h3>{{ t('helfer', 'Stände / Aufgaben') }}</h3>
				<ul class="structure__list">
					<li v-for="station in data.stations" :key="station.id">
						<div class="structure__item">
							<strong>{{ station.title }}</strong>
							<span v-if="station.description">{{ station.description }}</span>
						</div>
						<NcButton variant="tertiary" :aria-label="t('helfer', 'Bearbeiten')" @click="stationDialog = { station }">
							<template #icon>
								<NcIconSvgWrapper :path="mdiPencil" />
							</template>
						</NcButton>
						<NcButton variant="tertiary" :aria-label="t('helfer', 'Löschen')" @click="deleteStation(station)">
							<template #icon>
								<NcIconSvgWrapper :path="mdiDelete" />
							</template>
						</NcButton>
					</li>
				</ul>
				<NcButton @click="stationDialog = { station: null }">
					<template #icon>
						<NcIconSvgWrapper :path="mdiPlus" />
					</template>
					{{ t('helfer', 'Stand / Aufgabe hinzufügen') }}
				</NcButton>
			</section>
		</div>

		<section v-if="data.shifts.length > 0 && data.stations.length > 0">
			<h3>{{ t('helfer', 'Benötigte Helfer pro Schicht') }}</h3>
			<p class="structure__hint">
				{{ t('helfer', '0 oder leer bedeutet: in dieser Schicht wird an diesem Stand niemand gebraucht.') }}
			</p>
			<div class="structure__tablewrap">
				<table class="structure__matrix">
					<thead>
						<tr>
							<th />
							<th v-for="shift in data.shifts" :key="shift.id">
								<div>{{ shift.title || formatDay(shift.startTime, tz) }}</div>
								<small>{{ formatRange(shift.startTime, shift.endTime, tz) }}</small>
							</th>
						</tr>
					</thead>
					<tbody>
						<tr v-for="station in data.stations" :key="station.id">
							<th>{{ station.title }}</th>
							<td v-for="shift in data.shifts" :key="shift.id">
								<NcTextField class="structure__number"
									type="number"
									min="0"
									max="1000"
									label-outside
									:label="t('helfer', 'Benötigte Helfer: {station}, {shift}', { station: station.title, shift: formatRange(shift.startTime, shift.endTime, tz) })"
									:model-value="String(slotFor(shift.id, station.id)?.needed ?? '')"
									@change="setNeeded(shift.id, station.id, ($event.target as HTMLInputElement).value)" />
								<small v-if="takenFor(shift.id, station.id) > 0" class="structure__taken">
									{{ t('helfer', '{n} eingetragen', { n: takenFor(shift.id, station.id) }) }}
								</small>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
			<div class="structure__fill">
				<NcTextField v-model="fillText"
					class="structure__number structure__number--fill"
					type="number"
					min="0"
					max="1000"
					:label="t('helfer', 'Alle Felder setzen auf')" />
				<NcButton @click="fillAll">
					{{ t('helfer', 'Übernehmen') }}
				</NcButton>
			</div>
		</section>
		<NcNoteCard v-else type="info" :text="t('helfer', 'Lege mindestens eine Schicht und einen Stand an. Danach kannst du festlegen, wie viele Helfer jeweils gebraucht werden.')" />

		<ShiftDialog v-if="shiftDialog"
			:is-new="!shiftDialog.shift"
			:title="shiftDialog.shift?.title"
			:start="shiftDialog.start"
			:end="shiftDialog.end"
			@close="shiftDialog = null"
			@save="saveShift" />
		<StationDialog v-if="stationDialog"
			:is-new="!stationDialog.station"
			:title="stationDialog.station?.title"
			:description="stationDialog.station?.description"
			@close="stationDialog = null"
			@save="saveStation" />
	</div>
</template>

<style scoped>
.structure {
	display: flex;
	flex-direction: column;
	gap: 24px;
}

.structure h3 {
	font-weight: bold;
	font-size: 1.1em;
	margin: 0 0 8px;
}

.structure__columns {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
	gap: 24px;
}

.structure__list {
	margin-bottom: 8px;
}

.structure__list li {
	display: flex;
	align-items: center;
	gap: 4px;
	padding: 4px 0;
	border-bottom: 1px solid var(--color-border);
}

.structure__item {
	flex: 1;
	display: flex;
	flex-direction: column;
	min-width: 0;
}

.structure__item span, .structure__hint, .structure__taken {
	color: var(--color-text-maxcontrast);
}

.structure__tablewrap {
	overflow-x: auto;
}

.structure__matrix {
	border-collapse: collapse;
}

.structure__matrix th, .structure__matrix td {
	padding: 6px 8px;
	border: 1px solid var(--color-border);
	text-align: center;
	vertical-align: top;
}

.structure__matrix tbody th {
	text-align: start;
	white-space: nowrap;
}

.structure__number {
	width: 88px;
	margin: 0 auto;
}

.structure__number--fill {
	flex: 0 0 220px;
	width: 220px;
	margin: 0;
}

.structure__taken {
	display: block;
}

.structure__fill {
	display: flex;
	align-items: flex-end;
	gap: 8px;
	margin-top: 12px;
}

</style>
