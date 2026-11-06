<script setup lang="ts">
import { computed, ref } from 'vue'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import type { Donation, DonationList } from '../types.ts'

const props = defineProps<{ lists: DonationList[], donation: Donation | null, listId: number }>()
const emit = defineEmits<{ close: [], save: [payload: Record<string, unknown>], delete: [] }>()

const form = ref({
	listId: props.donation?.listId ?? props.listId,
	item: props.donation?.item ?? '',
	name: props.donation?.name ?? '',
	email: props.donation?.email ?? '',
	comment: props.donation?.comment ?? '',
})

const listOptions = computed(() => props.lists.map((l) => ({ id: l.id, label: l.title })))
const listOption = computed({
	get: () => listOptions.value.find((o) => o.id === form.value.listId) ?? null,
	set: (value: { id: number, label: string } | null) => {
		if (value) {
			form.value.listId = value.id
		}
	},
})
</script>

<template>
	<NcDialog :name="props.donation ? t('helfer', 'Spende bearbeiten') : t('helfer', 'Spende eintragen')"
		:open="true"
		is-form
		@submit.prevent="emit('save', form)"
		@update:open="(open: boolean) => !open && emit('close')">
		<div class="donation-dialog">
			<NcSelect v-if="lists.length > 1"
				v-model="listOption"
				:options="listOptions"
				:input-label="t('helfer', 'Liste')"
				:clearable="false" />
			<NcTextField v-model="form.item" :label="t('helfer', 'Spende (z. B. Muffins)')" required />
			<NcTextField v-model="form.name" :label="t('helfer', 'Name')" required />
			<NcTextField v-model="form.email" type="email" :label="t('helfer', 'E-Mail')" />
			<NcTextField v-model="form.comment" :label="t('helfer', 'Kommentar')" />
		</div>
		<template #actions>
			<NcButton v-if="props.donation" variant="error" @click="emit('delete')">
				{{ t('helfer', 'Löschen') }}
			</NcButton>
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
.donation-dialog {
	display: flex;
	flex-direction: column;
	gap: 8px;
}
</style>
