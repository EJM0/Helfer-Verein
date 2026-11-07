<script setup lang="ts">
import { ref } from 'vue'
import { showError } from '@nextcloud/dialogs'
import { t } from '../l10n.ts'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import SettingsFields from './SettingsFields.vue'
import { draftToPayload, emptyDraft } from './draft.ts'
import { adminApi, errorMessage } from '../api.ts'
import { browserTimezone } from '../time.ts'
import type { FullEvent } from '../types.ts'

const emit = defineEmits<{ close: [], created: [data: FullEvent] }>()
const draft = ref(emptyDraft())
const saving = ref(false)

async function submit() {
	if (draft.value.title.trim() === '') {
		showError(t('helfer', 'Bitte einen Titel angeben.'))
		return
	}
	saving.value = true
	try {
		const data = await adminApi.create({ ...draftToPayload(draft.value), timezone: browserTimezone() } as never)
		emit('created', data)
	} catch (e) {
		showError(errorMessage(e, t('helfer', 'Umfrage konnte nicht angelegt werden.')))
	} finally {
		saving.value = false
	}
}
</script>

<template>
	<NcDialog :name="t('helfer', 'Neue Umfrage')"
		size="normal"
		is-form
		:open="true"
		@submit.prevent="submit"
		@update:open="(open: boolean) => !open && emit('close')">
		<SettingsFields v-model="draft" />
		<template #actions>
			<NcButton @click="emit('close')">
				{{ t('helfer', 'Abbrechen') }}
			</NcButton>
			<NcButton variant="primary" type="submit" :disabled="saving">
				{{ t('helfer', 'Anlegen') }}
			</NcButton>
		</template>
	</NcDialog>
</template>
