<script setup lang="ts">
import { computed, ref } from 'vue'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcProgressBar from '@nextcloud/vue/components/NcProgressBar'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import type { Donation, DonationList, PublicEvent } from '../types.ts'
import type { OwnDonation, Person } from './storage.ts'
import { mdiClose, mdiPlus } from '../icons.ts'

const props = defineProps<{
	list: DonationList
	donations: Donation[]
	own: OwnDonation[]
	event: PublicEvent
	writable: boolean
	submitting: boolean
	people: Person[]
}>()
const emit = defineEmits<{ submit: [payload: Record<string, unknown>], cancel: [donation: Donation] }>()

const open = ref(false)
const form = ref({ item: '', name: '', email: '', comment: '' })
const ownById = computed(() => new Map(props.own.map((o) => [o.id, o])))

function nameOf(donation: Donation): string {
	return donation.name ?? ownById.value.get(donation.id)?.name ?? '–'
}

function start() {
	const last = props.people[0]
	form.value = { item: '', name: last?.name ?? '', email: last?.email ?? '', comment: '' }
	open.value = true
}

function submit() {
	emit('submit', { ...form.value, listId: props.list.id })
}

defineExpose({ close: () => { open.value = false } })
</script>

<template>
	<section class="donation-card">
		<h3>{{ list.title }}</h3>
		<p v-if="list.description" class="donation-card__description">
			{{ list.description }}
		</p>
		<div v-if="list.needed" class="donation-card__progress">
			<NcProgressBar :value="Math.min(100, Math.round(donations.length / list.needed * 100))" size="medium" />
			<span>{{ t('helfer', '{n} von {needed}', { n: donations.length, needed: list.needed }) }}</span>
		</div>
		<table v-if="donations.length > 0" class="donation-card__table">
			<thead>
				<tr>
					<th>{{ t('helfer', 'Spende') }}</th>
					<th>{{ t('helfer', 'Name') }}</th>
					<th v-if="writable">
						<span class="hidden-visually">{{ t('helfer', 'Aktion') }}</span>
					</th>
				</tr>
			</thead>
			<tbody>
				<tr v-for="donation in donations" :key="donation.id" :class="{ 'donation-card__mine': ownById.has(donation.id) }">
					<td>{{ donation.item }}</td>
					<td>{{ nameOf(donation) }}</td>
					<td v-if="writable" class="donation-card__action">
						<NcButton v-if="ownById.has(donation.id)"
							variant="tertiary"
							size="small"
							:aria-label="t('helfer', 'Austragen')"
							:title="t('helfer', 'Austragen')"
							@click="emit('cancel', donation)">
							<template #icon>
								<NcIconSvgWrapper :path="mdiClose" :size="18" />
							</template>
						</NcButton>
					</td>
				</tr>
			</tbody>
		</table>

		<form v-if="open" class="donation-card__form" @submit.prevent="submit">
			<NcTextField v-model="form.item" :label="t('helfer', 'Was bringst du mit?')" required />
			<NcTextField v-model="form.name" :label="t('helfer', 'Name')" required />
			<NcTextField v-if="event.askEmail"
				v-model="form.email"
				type="email"
				:label="event.emailRequired ? t('helfer', 'E-Mail-Adresse') : t('helfer', 'E-Mail-Adresse (optional)')"
				:required="event.emailRequired" />
			<NcTextField v-model="form.comment" :label="t('helfer', 'Kommentar (optional)')" />
			<div class="donation-card__buttons">
				<NcButton @click="open = false">
					{{ t('helfer', 'Abbrechen') }}
				</NcButton>
				<NcButton variant="primary" type="submit" :disabled="submitting">
					{{ t('helfer', 'Eintragen') }}
				</NcButton>
			</div>
		</form>
		<NcButton v-else-if="writable" wide @click="start">
			<template #icon>
				<NcIconSvgWrapper :path="mdiPlus" />
			</template>
			{{ t('helfer', 'Spende hinzufügen') }}
		</NcButton>
	</section>
</template>

<style scoped>
.donation-card {
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.donation-card h3 {
	font-weight: bold;
	font-size: 1.15em;
	margin: 0;
	color: var(--color-primary-element);
}

.donation-card__description {
	margin: 0;
	white-space: pre-wrap;
}

.donation-card__progress {
	display: flex;
	align-items: center;
	gap: 12px;
}

.donation-card__progress > :first-child {
	flex: 1 1 auto;
	min-width: 80px;
}

.donation-card__progress span {
	white-space: nowrap;
	color: var(--color-text-maxcontrast);
}

.donation-card__table {
	width: 100%;
	border-collapse: collapse;
}

.donation-card__table th, .donation-card__table td {
	text-align: start;
	padding: 6px 8px;
	border-bottom: 1px solid var(--color-border);
}

.donation-card__table th {
	font-weight: bold;
	background: var(--color-background-hover);
}

.donation-card__mine td {
	background: var(--color-primary-element-light);
	color: var(--color-primary-element-light-text);
}

.donation-card__action {
	width: 44px;
}

.donation-card__form {
	display: flex;
	flex-direction: column;
	gap: 8px;
	padding: 12px;
	border-radius: var(--border-radius-large);
	background: var(--color-background-hover);
}

.donation-card__buttons {
	display: flex;
	justify-content: flex-end;
	gap: 8px;
}
</style>
