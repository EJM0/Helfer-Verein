<script setup lang="ts">
import { ref } from 'vue'
import { t } from '../l10n.ts'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import type { DonationList } from '../types.ts'

const props = defineProps<{ list: DonationList | null }>()
const emit = defineEmits<{ close: [], save: [data: { title: string, description: string, needed: number | null }] }>()

const title = ref(props.list?.title ?? t('helfer', 'Kuchenliste'))
const description = ref(props.list?.description ?? '')
const needed = ref(props.list?.needed ? String(props.list.needed) : '')

function submit() {
	const value = parseInt(String(needed.value), 10)
	emit('save', { title: title.value, description: description.value, needed: Number.isNaN(value) || value <= 0 ? null : value })
}
</script>

<template>
	<NcDialog :name="props.list ? t('helfer', 'Liste bearbeiten') : t('helfer', 'Spendenliste anlegen')"
		:open="true"
		is-form
		@submit.prevent="submit"
		@update:open="(open: boolean) => !open && emit('close')">
		<div class="list-dialog">
			<NcTextField v-model="title" :label="t('helfer', 'Name der Liste (z. B. Kuchenliste, Salate)')" required />
			<NcTextArea v-model="description" :label="t('helfer', 'Beschreibung (z. B. Es werden 20 Kuchen benötigt)')" resize="vertical" />
			<NcTextField v-model="needed"
				type="number"
				min="0"
				:label="t('helfer', 'Benötigte Anzahl (optional)')" />
		</div>
		<template #actions>
			<NcButton @click="emit('close')">
				{{ t('helfer', 'Abbrechen') }}
			</NcButton>
			<NcButton variant="primary" type="submit">
				{{ t('helfer', 'Speichern') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<style scoped>
.list-dialog {
	display: flex;
	flex-direction: column;
	gap: 12px;
}
</style>
