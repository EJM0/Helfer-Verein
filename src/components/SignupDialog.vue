<script setup lang="ts">
import { computed, ref } from 'vue'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { slotOptions } from './slots.ts'
import type { FullEvent, Signup } from '../types.ts'

const props = defineProps<{ data: FullEvent, signup: Signup | null, slotId: number }>()
const emit = defineEmits<{ close: [], save: [payload: Record<string, unknown>], delete: [] }>()

const event = computed(() => props.data.event)
const options = computed(() => slotOptions(props.data, event.value.timezone))

const form = ref({
	slotId: props.signup?.slotId ?? props.slotId,
	name: props.signup?.name ?? '',
	email: props.signup?.email ?? '',
	phone: props.signup?.phone ?? '',
	groupName: props.signup?.groupName ?? '',
	role: props.signup?.role ?? '',
	comment: props.signup?.comment ?? '',
	answers: { ...(props.signup?.answers ?? {}) } as Record<string, string>,
})

const selectOptions = computed(() => options.value.map((o) => ({ id: o.slot.id, label: o.label })))
const slotOption = computed({
	get: () => selectOptions.value.find((o) => o.id === form.value.slotId) ?? null,
	set: (value: { id: number, label: string } | null) => {
		if (value) {
			form.value.slotId = value.id
		}
	},
})
const groupOption = computed({
	get: () => form.value.groupName || null,
	set: (value: string | null) => { form.value.groupName = value ?? '' },
})
const roleOption = computed({
	get: () => form.value.role || null,
	set: (value: string | null) => { form.value.role = value ?? '' },
})

const roleSuggestions = computed(() => {
	const roles = new Set<string>([t('helfer', 'Schichtleitung'), t('helfer', 'Kasse')])
	for (const s of props.data.signups) {
		if (s.role) {
			roles.add(s.role)
		}
	}
	return [...roles]
})
</script>

<template>
	<NcDialog :name="props.signup ? t('helfer', 'Helfer bearbeiten') : t('helfer', 'Helfer eintragen')"
		:open="true"
		is-form
		@submit.prevent="emit('save', form)"
		@update:open="(open: boolean) => !open && emit('close')">
		<div class="signup-dialog">
			<NcSelect v-model="slotOption"
				:options="selectOptions"
				:input-label="t('helfer', 'Schicht und Stand')"
				:clearable="false" />
			<NcTextField v-model="form.name" :label="t('helfer', 'Name')" required />
			<NcTextField v-model="form.email" type="email" :label="t('helfer', 'E-Mail')" />
			<NcTextField v-model="form.phone" type="tel" :label="t('helfer', 'Telefon')" />
			<NcSelect v-if="event.askGroup || form.groupName"
				v-model="groupOption"
				:options="event.groupOptions"
				:input-label="t('helfer', 'Gruppe / Verein')"
				taggable
				push-tags />
			<NcSelect v-model="roleOption"
				:options="roleSuggestions"
				:input-label="t('helfer', 'Rolle (z. B. Schichtleitung, Kasse)')"
				:placeholder="t('helfer', 'Auswählen oder eingeben')"
				taggable
				push-tags />
			<NcTextField v-for="question in event.questions"
				:key="question.id"
				v-model="form.answers[question.id]"
				:label="question.label" />
			<NcTextField v-model="form.comment" :label="t('helfer', 'Kommentar')" />
		</div>
		<template #actions>
			<NcButton v-if="props.signup" variant="error" @click="emit('delete')">
				{{ t('helfer', 'Austragen') }}
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
.signup-dialog {
	display: flex;
	flex-direction: column;
	gap: 8px;
}

</style>
