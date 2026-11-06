<script setup lang="ts">
import { computed } from 'vue'
import { t } from '@nextcloud/l10n'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcChip from '@nextcloud/vue/components/NcChip'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import SignupDialog from './SignupDialog.vue'
import { useSignupEditor } from './useSignupEditor.ts'
import { dayKey, formatDay, formatRange } from '../time.ts'
import type { FullEvent, Shift } from '../types.ts'
import { mdiAccountPlus, mdiCalendarClock, mdiDelete, mdiPencil } from '../icons.ts'

const props = defineProps<{ data: FullEvent }>()
const emit = defineEmits<{ update: [data: FullEvent], editStructure: [] }>()

const tz = computed(() => props.data.event.timezone)
const editor = useSignupEditor(() => props.data, (d) => emit('update', d))

const days = computed(() => {
	const groups: { key: string, label: string, shifts: Shift[] }[] = []
	for (const shift of props.data.shifts) {
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

function rows(shift: Shift) {
	return props.data.stations
		.map((station) => {
			const slot = props.data.slots.find((s) => s.shiftId === shift.id && s.stationId === station.id)
			if (!slot) {
				return null
			}
			return { station, slot, signups: props.data.signups.filter((s) => s.slotId === slot.id) }
		})
		.filter((row): row is NonNullable<typeof row> => row !== null)
}
</script>

<template>
	<div class="plan">
		<NcEmptyContent v-if="data.slots.length === 0"
			:name="t('helfer', 'Noch keine Einteilung')"
			:description="t('helfer', 'Lege zuerst Schichten und Stände an.')">
			<template #icon>
				<NcIconSvgWrapper :path="mdiCalendarClock" />
			</template>
			<template #action>
				<NcButton variant="primary" @click="emit('editStructure')">
					{{ t('helfer', 'Schichten & Stände anlegen') }}
				</NcButton>
			</template>
		</NcEmptyContent>

		<section v-for="day in days" :key="day.key" class="plan__day">
			<h3>{{ day.label }}</h3>
			<div class="plan__shifts">
				<article v-for="shift in day.shifts"
					v-show="rows(shift).length > 0"
					:key="shift.id"
					class="plan__shift">
					<header>
						<strong>{{ formatRange(shift.startTime, shift.endTime, tz) }}</strong>
						<span v-if="shift.title">{{ shift.title }}</span>
					</header>
					<div v-for="row in rows(shift)" :key="row.slot.id" class="plan__row">
						<div class="plan__station">
							<span>{{ row.station.title }}</span>
							<span class="plan__count" :class="{ 'plan__count--full': row.signups.length >= row.slot.needed, 'plan__count--over': row.signups.length > row.slot.needed }">
								{{ row.signups.length }}/{{ row.slot.needed }}
							</span>
						</div>
						<ul class="plan__people">
							<li v-for="signup in row.signups" :key="signup.id">
								<NcChip :text="signup.name + (signup.role ? ' · ' + signup.role : '')" no-close>
									<template #actions>
										<NcActionButton close-after-click @click="editor.edit(signup)">
											<template #icon>
												<NcIconSvgWrapper :path="mdiPencil" />
											</template>
											{{ t('helfer', 'Bearbeiten') }}
										</NcActionButton>
										<NcActionButton close-after-click @click="editor.remove(signup)">
											<template #icon>
												<NcIconSvgWrapper :path="mdiDelete" />
											</template>
											{{ t('helfer', 'Austragen') }}
										</NcActionButton>
									</template>
								</NcChip>
							</li>
							<li>
								<NcButton variant="tertiary"
									size="small"
									:aria-label="t('helfer', 'Helfer eintragen')"
									@click="editor.add(row.slot.id)">
									<template #icon>
										<NcIconSvgWrapper :path="mdiAccountPlus" :size="18" />
									</template>
								</NcButton>
							</li>
						</ul>
					</div>
				</article>
			</div>
		</section>

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
.plan {
	display: flex;
	flex-direction: column;
	gap: 24px;
}

.plan__day h3 {
	font-weight: bold;
	font-size: 1.1em;
	margin: 0 0 8px;
}

.plan__shifts {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
	gap: 12px;
}

.plan__shift {
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	padding: 12px;
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.plan__shift header {
	display: flex;
	gap: 8px;
	align-items: baseline;
	flex-wrap: wrap;
}

.plan__shift header span {
	color: var(--color-text-maxcontrast);
}

.plan__row {
	border-top: 1px solid var(--color-border);
	padding-top: 6px;
}

.plan__station {
	display: flex;
	justify-content: space-between;
	font-weight: 600;
}

.plan__count {
	padding: 0 8px;
	border-radius: var(--border-radius-pill);
	border: 1px solid var(--color-warning);
	color: var(--color-warning-text);
	font-size: 0.9em;
}

.plan__count--full {
	border-color: var(--color-success);
	color: var(--color-success-text);
}

.plan__count--over {
	border-color: var(--color-primary-element);
	color: var(--color-primary-element);
}

.plan__people {
	display: flex;
	flex-wrap: wrap;
	gap: 4px;
	align-items: center;
	margin-top: 4px;
}
</style>
