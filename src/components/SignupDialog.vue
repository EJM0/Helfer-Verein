<script setup lang="ts">
import { computed, ref } from 'vue'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
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
			<label class="signup-dialog__label" for="helfer-slot">{{ t('helfer', 'Schicht und Stand') }}</label>
			<select id="helfer-slot" v-model.number="form.slotId">
				<option v-for="option in options" :key="option.slot.id" :value="option.slot.id">
					{{ option.label }}
				</option>
			</select>
			<NcTextField v-model="form.name" :label="t('helfer', 'Name')" required />
			<NcTextField v-model="form.email" type="email" :label="t('helfer', 'E-Mail')" />
			<NcTextField v-model="form.phone" type="tel" :label="t('helfer', 'Telefon')" />
			<template v-if="event.askGroup || form.groupName">
				<label class="signup-dialog__label" for="helfer-group">{{ t('helfer', 'Gruppe / Verein') }}</label>
				<input id="helfer-group"
					v-model="form.groupName"
					list="helfer-group-options"
					type="text">
				<datalist id="helfer-group-options">
					<option v-for="option in event.groupOptions" :key="option" :value="option" />
				</datalist>
			</template>
			<label class="signup-dialog__label" for="helfer-role">{{ t('helfer', 'Rolle (z. B. Schichtleitung, Kasse)') }}</label>
			<input id="helfer-role"
				v-model="form.role"
				list="helfer-role-options"
				type="text">
			<datalist id="helfer-role-options">
				<option v-for="role in roleSuggestions" :key="role" :value="role" />
			</datalist>
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

.signup-dialog select, .signup-dialog input[type='text'] {
	width: 100%;
	margin: 0;
}

.signup-dialog__label {
	margin-top: 4px;
	color: var(--color-text-maxcontrast);
}
</style>
