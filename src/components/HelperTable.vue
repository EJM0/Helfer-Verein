<script setup lang="ts">
import { computed, ref } from 'vue'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import SignupDialog from './SignupDialog.vue'
import { slotOptions } from './slots.ts'
import { useSignupEditor } from './useSignupEditor.ts'
import { formatDay, formatRange, hours } from '../time.ts'
import type { FullEvent } from '../types.ts'
import { mdiAccountPlus, mdiDelete, mdiPencil } from '../icons.ts'

const props = defineProps<{ data: FullEvent }>()
const emit = defineEmits<{ update: [data: FullEvent] }>()

const tz = computed(() => props.data.event.timezone)
const editor = useSignupEditor(() => props.data, (d) => emit('update', d))
const search = ref('')
const view = ref<'assignments' | 'people'>('assignments')

const options = computed(() => slotOptions(props.data, tz.value))

const rows = computed(() => {
	const order = new Map(options.value.map((o, index) => [o.slot.id, index]))
	const query = search.value.trim().toLowerCase()
	return props.data.signups
		.map((signup) => ({ signup, option: options.value.find((o) => o.slot.id === signup.slotId) }))
		.filter((row) => row.option)
		.filter((row) => !query || [row.signup.name, row.signup.email, row.signup.groupName, row.signup.role, row.option!.station.title]
			.some((v) => (v ?? '').toLowerCase().includes(query)))
		.sort((a, b) => (order.get(a.signup.slotId)! - order.get(b.signup.slotId)!) || (a.signup.name ?? '').localeCompare(b.signup.name ?? ''))
})

const people = computed(() => {
	const map = new Map<string, { name: string, email: string, phone: string, group: string, count: number, seconds: number }>()
	for (const { signup, option } of rows.value) {
		const key = `${(signup.name ?? '').trim().toLowerCase()}|${(signup.email ?? '').trim().toLowerCase()}`
		const entry = map.get(key) ?? { name: signup.name ?? '', email: signup.email ?? '', phone: signup.phone ?? '', group: signup.groupName ?? '', count: 0, seconds: 0 }
		entry.count++
		entry.seconds += option!.shift.endTime - option!.shift.startTime
		entry.phone ||= signup.phone ?? ''
		entry.group ||= signup.groupName ?? ''
		map.set(key, entry)
	}
	return [...map.values()].sort((a, b) => a.name.localeCompare(b.name))
})

const totalHours = computed(() => people.value.reduce((sum, p) => sum + p.seconds, 0))
</script>

<template>
	<div class="helpers">
		<div class="helpers__toolbar">
			<NcTextField v-model="search" :label="t('helfer', 'Suchen')" class="helpers__search" />
			<div class="helpers__switch">
				<NcButton :variant="view === 'assignments' ? 'primary' : 'secondary'" @click="view = 'assignments'">
					{{ t('helfer', 'Einsätze') }}
				</NcButton>
				<NcButton :variant="view === 'people' ? 'primary' : 'secondary'" @click="view = 'people'">
					{{ t('helfer', 'Personen') }}
				</NcButton>
			</div>
			<NcButton v-if="options.length > 0" @click="editor.add(options[0].slot.id)">
				<template #icon>
					<NcIconSvgWrapper :path="mdiAccountPlus" />
				</template>
				{{ t('helfer', 'Helfer eintragen') }}
			</NcButton>
		</div>
		<p class="helpers__summary">
			{{ t('helfer', '{people} Personen · {count} Einsätze · {hours} Stunden', { people: people.length, count: rows.length, hours: hours(0, totalHours) }) }}
		</p>

		<div class="helpers__tablewrap">
			<table v-if="view === 'assignments'" class="helpers__table">
				<thead>
					<tr>
						<th>{{ t('helfer', 'Name') }}</th>
						<th>{{ t('helfer', 'Datum') }}</th>
						<th>{{ t('helfer', 'Zeit') }}</th>
						<th>{{ t('helfer', 'Stand / Aufgabe') }}</th>
						<th>{{ t('helfer', 'Rolle') }}</th>
						<th>{{ t('helfer', 'Kontakt') }}</th>
						<th v-if="data.event.askGroup">
							{{ t('helfer', 'Gruppe') }}
						</th>
						<th v-for="q in data.event.questions" :key="q.id">
							{{ q.label }}
						</th>
						<th>{{ t('helfer', 'Kommentar') }}</th>
						<th />
					</tr>
				</thead>
				<tbody>
					<tr v-for="{ signup, option } in rows" :key="signup.id">
						<td><strong>{{ signup.name }}</strong></td>
						<td>{{ formatDay(option!.shift.startTime, tz) }}</td>
						<td>{{ formatRange(option!.shift.startTime, option!.shift.endTime, tz) }}</td>
						<td>{{ option!.station.title }}</td>
						<td>{{ signup.role }}</td>
						<td>
							<a v-if="signup.email" :href="`mailto:${signup.email}`">{{ signup.email }}</a>
							<div v-if="signup.phone">
								{{ signup.phone }}
							</div>
						</td>
						<td v-if="data.event.askGroup">
							{{ signup.groupName }}
						</td>
						<td v-for="q in data.event.questions" :key="q.id">
							{{ signup.answers?.[q.id] }}
						</td>
						<td>{{ signup.comment }}</td>
						<td>
							<div class="helpers__actions">
								<NcButton variant="tertiary" :aria-label="t('helfer', 'Bearbeiten')" @click="editor.edit(signup)">
									<template #icon>
										<NcIconSvgWrapper :path="mdiPencil" />
									</template>
								</NcButton>
								<NcButton variant="tertiary" :aria-label="t('helfer', 'Austragen')" @click="editor.remove(signup)">
									<template #icon>
										<NcIconSvgWrapper :path="mdiDelete" />
									</template>
								</NcButton>
							</div>
						</td>
					</tr>
					<tr v-if="rows.length === 0">
						<td :colspan="8 + data.event.questions.length" class="helpers__empty">
							{{ t('helfer', 'Noch keine Helfer eingetragen.') }}
						</td>
					</tr>
				</tbody>
			</table>

			<table v-else class="helpers__table">
				<thead>
					<tr>
						<th>{{ t('helfer', 'Name') }}</th>
						<th>{{ t('helfer', 'E-Mail') }}</th>
						<th>{{ t('helfer', 'Telefon') }}</th>
						<th v-if="data.event.askGroup">
							{{ t('helfer', 'Gruppe') }}
						</th>
						<th>{{ t('helfer', 'Einsätze') }}</th>
						<th>{{ t('helfer', 'Stunden') }}</th>
					</tr>
				</thead>
				<tbody>
					<tr v-for="person in people" :key="person.name + person.email">
						<td><strong>{{ person.name }}</strong></td>
						<td>{{ person.email }}</td>
						<td>{{ person.phone }}</td>
						<td v-if="data.event.askGroup">
							{{ person.group }}
						</td>
						<td>{{ person.count }}</td>
						<td>{{ hours(0, person.seconds) }}</td>
					</tr>
				</tbody>
			</table>
		</div>

		<SignupDialog v-if="editor.editing.value"
			:data="data"
			:signup="editor.editing.value.signup"
			:slot-id="editor.editing.value.slotId"
			@close="editor.editing.value = null"
			@save="editor.save"
			@delete="editor.remove()" />
	</div>
</template>

<style scoped>
.helpers__toolbar {
	display: flex;
	gap: 8px;
	align-items: flex-end;
	flex-wrap: wrap;
}

.helpers__search {
	max-width: 280px;
}

.helpers__switch {
	display: flex;
	gap: 4px;
}

.helpers__summary {
	color: var(--color-text-maxcontrast);
	margin: 8px 0;
}

.helpers__tablewrap {
	overflow-x: auto;
}

.helpers__table {
	width: 100%;
	border-collapse: collapse;
}

.helpers__table th, .helpers__table td {
	text-align: start;
	padding: 6px 8px;
	border-bottom: 1px solid var(--color-border);
	vertical-align: top;
}

.helpers__table th {
	color: var(--color-text-maxcontrast);
	font-weight: normal;
	white-space: nowrap;
}

.helpers__actions {
	white-space: nowrap;
	display: flex;
}

.helpers__empty {
	text-align: center !important;
	color: var(--color-text-maxcontrast);
	padding: 24px !important;
}
</style>
