<script setup lang="ts">
import { computed, ref } from 'vue'
import { showError } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcProgressBar from '@nextcloud/vue/components/NcProgressBar'
import DonationDialog from './DonationDialog.vue'
import DonationListDialog from './DonationListDialog.vue'
import { adminApi, errorMessage } from '../api.ts'
import { confirmAction } from '../confirm.ts'
import type { Donation, DonationList, FullEvent } from '../types.ts'
import { mdiDelete, mdiGift, mdiPencil, mdiPlus } from '../icons.ts'

const props = defineProps<{ data: FullEvent }>()
const emit = defineEmits<{ update: [data: FullEvent] }>()

const id = computed(() => props.data.event.id)
const listDialog = ref<{ list: DonationList | null } | null>(null)
const donationDialog = ref<{ donation: Donation | null, listId: number } | null>(null)

function donationsOf(list: DonationList) {
	return props.data.donations.filter((d) => d.listId === list.id)
}

async function run(promise: Promise<FullEvent>, fallback: string): Promise<boolean> {
	try {
		emit('update', await promise)
		return true
	} catch (e) {
		showError(errorMessage(e, fallback))
		return false
	}
}

async function saveList(data: { title: string, description: string, needed: number | null }) {
	const current = listDialog.value
	if (!current) {
		return
	}
	const ok = current.list
		? await run(adminApi.updateDonationList(id.value, current.list.id, data), t('helfer', 'Speichern fehlgeschlagen.'))
		: await run(adminApi.addDonationList(id.value, data), t('helfer', 'Liste konnte nicht angelegt werden.'))
	if (ok) {
		listDialog.value = null
	}
}

async function deleteList(list: DonationList) {
	const count = donationsOf(list).length
	const text = count > 0
		? t('helfer', '„{title}“ mit {n} Einträgen löschen?', { title: list.title, n: count })
		: t('helfer', '„{title}“ löschen?', { title: list.title })
	if (await confirmAction(t('helfer', 'Liste löschen'), text, t('helfer', 'Löschen'))) {
		await run(adminApi.deleteDonationList(id.value, list.id), t('helfer', 'Löschen fehlgeschlagen.'))
	}
}

async function saveDonation(payload: Record<string, unknown>) {
	const current = donationDialog.value
	if (!current) {
		return
	}
	const ok = current.donation
		? await run(adminApi.updateDonation(id.value, current.donation.id, payload), t('helfer', 'Speichern fehlgeschlagen.'))
		: await run(adminApi.addDonation(id.value, payload), t('helfer', 'Speichern fehlgeschlagen.'))
	if (ok) {
		donationDialog.value = null
	}
}

async function deleteDonation(donation?: Donation | null) {
	const target = donation ?? donationDialog.value?.donation
	if (!target) {
		return
	}
	if (await confirmAction(t('helfer', 'Spende löschen'), t('helfer', '„{item}“ von {name} löschen?', { item: target.item, name: target.name ?? '' }), t('helfer', 'Löschen'))) {
		if (await run(adminApi.deleteDonation(id.value, target.id), t('helfer', 'Löschen fehlgeschlagen.'))) {
			donationDialog.value = null
		}
	}
}
</script>

<template>
	<div class="donations">
		<NcEmptyContent v-if="data.donationLists.length === 0"
			:name="t('helfer', 'Keine Spendenliste')"
			:description="t('helfer', 'Mit einer Spendenliste können Helfer eintragen, was sie mitbringen – z. B. Kuchen oder Salate.')">
			<template #icon>
				<NcIconSvgWrapper :path="mdiGift" />
			</template>
			<template #action>
				<NcButton variant="primary" @click="listDialog = { list: null }">
					{{ t('helfer', 'Spendenliste anlegen') }}
				</NcButton>
			</template>
		</NcEmptyContent>

		<section v-for="list in data.donationLists" :key="list.id" class="donations__list">
			<header class="donations__head">
				<div>
					<h3>{{ list.title }}</h3>
					<p v-if="list.description" class="donations__muted">
						{{ list.description }}
					</p>
				</div>
				<div class="donations__actions">
					<NcButton variant="tertiary" :aria-label="t('helfer', 'Bearbeiten')" @click="listDialog = { list }">
						<template #icon>
							<NcIconSvgWrapper :path="mdiPencil" />
						</template>
					</NcButton>
					<NcButton variant="tertiary" :aria-label="t('helfer', 'Löschen')" @click="deleteList(list)">
						<template #icon>
							<NcIconSvgWrapper :path="mdiDelete" />
						</template>
					</NcButton>
				</div>
			</header>
			<div v-if="list.needed" class="donations__progress">
				<NcProgressBar :value="Math.min(100, Math.round(donationsOf(list).length / list.needed * 100))" size="medium" />
				<span>{{ t('helfer', '{n} von {needed}', { n: donationsOf(list).length, needed: list.needed }) }}</span>
			</div>
			<table class="donations__table">
				<thead>
					<tr>
						<th>{{ t('helfer', 'Spende') }}</th>
						<th>{{ t('helfer', 'Name') }}</th>
						<th>{{ t('helfer', 'Kontakt') }}</th>
						<th>{{ t('helfer', 'Kommentar') }}</th>
						<th />
					</tr>
				</thead>
				<tbody>
					<tr v-for="donation in donationsOf(list)" :key="donation.id">
						<td><strong>{{ donation.item }}</strong></td>
						<td>{{ donation.name }}</td>
						<td>
							<a v-if="donation.email" :href="`mailto:${donation.email}`">{{ donation.email }}</a>
						</td>
						<td>{{ donation.comment }}</td>
						<td>
							<div class="donations__actions">
								<NcButton variant="tertiary" :aria-label="t('helfer', 'Bearbeiten')" @click="donationDialog = { donation, listId: list.id }">
									<template #icon>
										<NcIconSvgWrapper :path="mdiPencil" />
									</template>
								</NcButton>
								<NcButton variant="tertiary" :aria-label="t('helfer', 'Löschen')" @click="deleteDonation(donation)">
									<template #icon>
										<NcIconSvgWrapper :path="mdiDelete" />
									</template>
								</NcButton>
							</div>
						</td>
					</tr>
					<tr v-if="donationsOf(list).length === 0">
						<td colspan="5" class="donations__muted donations__empty">
							{{ t('helfer', 'Noch keine Einträge.') }}
						</td>
					</tr>
				</tbody>
			</table>
			<NcButton @click="donationDialog = { donation: null, listId: list.id }">
				<template #icon>
					<NcIconSvgWrapper :path="mdiPlus" />
				</template>
				{{ t('helfer', 'Spende eintragen') }}
			</NcButton>
		</section>

		<NcButton v-if="data.donationLists.length > 0" variant="secondary" @click="listDialog = { list: null }">
			<template #icon>
				<NcIconSvgWrapper :path="mdiPlus" />
			</template>
			{{ t('helfer', 'Weitere Liste anlegen') }}
		</NcButton>

		<DonationListDialog v-if="listDialog"
			:list="listDialog.list"
			@close="listDialog = null"
			@save="saveList" />
		<DonationDialog v-if="donationDialog"
			:lists="data.donationLists"
			:donation="donationDialog.donation"
			:list-id="donationDialog.listId"
			@close="donationDialog = null"
			@save="saveDonation"
			@delete="deleteDonation()" />
	</div>
</template>

<style scoped>
.donations {
	display: flex;
	flex-direction: column;
	gap: 24px;
	align-items: flex-start;
}

.donations__list {
	width: 100%;
	max-width: 900px;
	display: flex;
	flex-direction: column;
	gap: 8px;
	align-items: flex-start;
}

.donations__head {
	width: 100%;
	display: flex;
	justify-content: space-between;
	gap: 8px;
}

.donations__head h3 {
	font-weight: bold;
	font-size: 1.1em;
	margin: 0;
}

.donations__muted {
	color: var(--color-text-maxcontrast);
	margin: 0;
}

.donations__progress {
	width: 100%;
	display: flex;
	align-items: center;
	gap: 12px;
}

.donations__progress > :first-child {
	flex: 1 1 auto;
	min-width: 80px;
}

.donations__progress span {
	white-space: nowrap;
	color: var(--color-text-maxcontrast);
}

.donations__table {
	width: 100%;
	border-collapse: collapse;
}

.donations__table th, .donations__table td {
	text-align: start;
	padding: 6px 8px;
	border-bottom: 1px solid var(--color-border);
	vertical-align: top;
}

.donations__table th {
	color: var(--color-text-maxcontrast);
	font-weight: normal;
}

.donations__actions {
	display: flex;
	white-space: nowrap;
}

.donations__empty {
	text-align: center !important;
	padding: 16px !important;
}
</style>
