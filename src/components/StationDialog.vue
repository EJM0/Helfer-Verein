<script setup lang="ts">
import { ref } from 'vue'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import NcTextField from '@nextcloud/vue/components/NcTextField'

const props = defineProps<{ title?: string, description?: string, isNew: boolean }>()
const emit = defineEmits<{ close: [], save: [data: { title: string, description: string }] }>()

const name = ref(props.title ?? '')
const description = ref(props.description ?? '')
</script>

<template>
	<NcDialog :name="props.isNew ? t('helfer', 'Stand / Aufgabe hinzufügen') : t('helfer', 'Stand / Aufgabe bearbeiten')"
		:open="true"
		is-form
		@submit.prevent="emit('save', { title: name, description })"
		@update:open="(open: boolean) => !open && emit('close')">
		<div class="station-dialog">
			<NcTextField v-model="name" :label="t('helfer', 'Name (z. B. Kuchenstand, Grill, Kasse)')" required />
			<NcTextArea v-model="description" :label="t('helfer', 'Beschreibung (optional)')" resize="vertical" />
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
.station-dialog {
	display: flex;
	flex-direction: column;
	gap: 12px;
}
</style>
