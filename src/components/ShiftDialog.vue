<script setup lang="ts">
import { ref } from 'vue'
import { t } from '../l10n.ts'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDateTimePickerNative from '@nextcloud/vue/components/NcDateTimePickerNative'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { fromDate } from '../time.ts'

const props = defineProps<{ title?: string, start: Date | null, end: Date | null, isNew: boolean }>()
const emit = defineEmits<{ close: [], save: [data: { title: string, startTime: number | null, endTime: number | null }] }>()

const name = ref(props.title ?? '')
const start = ref<Date | null>(props.start)
const end = ref<Date | null>(props.end)

function onStartChange(value: Date | null) {
	// Keep the duration when the start moves.
	if (value && start.value && end.value) {
		const duration = end.value.getTime() - start.value.getTime()
		end.value = new Date(value.getTime() + duration)
	}
	start.value = value
}

function submit() {
	emit('save', { title: name.value, startTime: fromDate(start.value), endTime: fromDate(end.value) })
}
</script>

<template>
	<NcDialog :name="props.isNew ? t('helfer', 'Schicht hinzufügen') : t('helfer', 'Schicht bearbeiten')"
		:open="true"
		is-form
		@submit.prevent="submit"
		@update:open="(open: boolean) => !open && emit('close')">
		<div class="shift-dialog">
			<NcTextField v-model="name" :label="t('helfer', 'Bezeichnung (optional, z. B. Frühschicht)')" />
			<NcDateTimePickerNative :model-value="start"
				type="datetime-local"
				:label="t('helfer', 'Beginn')"
				@update:model-value="onStartChange" />
			<NcDateTimePickerNative v-model="end"
				type="datetime-local"
				:min="start"
				:label="t('helfer', 'Ende')" />
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
.shift-dialog {
	display: flex;
	flex-direction: column;
	gap: 12px;
}
</style>
