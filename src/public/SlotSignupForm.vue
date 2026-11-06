<script setup lang="ts">
import { computed, nextTick, onMounted, ref } from 'vue'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import type { PublicEvent } from '../types.ts'
import type { Person } from './storage.ts'
import { mdiAccount } from '../icons.ts'

const props = defineProps<{ event: PublicEvent, people: Person[], takenNames: string[], submitting: boolean }>()
const emit = defineEmits<{ submit: [payload: Record<string, unknown>], cancel: [] }>()

const form = ref({
	name: '',
	email: '',
	phone: '',
	groupName: '',
	comment: '',
	answers: {} as Record<string, string>,
})
const nameField = ref<InstanceType<typeof NcTextField> | null>(null)

// People from this device who are not yet in this shift.
const suggestions = computed(() => props.people
	.filter((p) => !props.takenNames.some((n) => n.toLowerCase() === p.name.toLowerCase()))
	.slice(0, 6))

const group = computed({
	get: () => form.value.groupName || null,
	set: (value: string | null) => { form.value.groupName = value ?? '' },
})

function use(person: Person) {
	form.value.name = person.name
	form.value.email = person.email
	form.value.phone = person.phone
	form.value.groupName = person.groupName
}

onMounted(async () => {
	await nextTick()
	nameField.value?.focus?.()
})
</script>

<template>
	<form class="slot-form" @submit.prevent="emit('submit', { ...form })">
		<div v-if="suggestions.length > 0" class="slot-form__people">
			<span>{{ t('helfer', 'Schnell eintragen:') }}</span>
			<NcButton v-for="person in suggestions"
				:key="person.name"
				size="small"
				variant="secondary"
				@click="use(person)">
				<template #icon>
					<NcIconSvgWrapper :path="mdiAccount" :size="16" />
				</template>
				{{ person.name }}
			</NcButton>
		</div>
		<NcTextField ref="nameField"
			v-model="form.name"
			:label="t('helfer', 'Name')"
			autocomplete="off"
			required />
		<NcTextField v-if="event.askEmail"
			v-model="form.email"
			type="email"
			:label="event.emailRequired ? t('helfer', 'E-Mail-Adresse') : t('helfer', 'E-Mail-Adresse (optional)')"
			:required="event.emailRequired" />
		<NcTextField v-if="event.askPhone"
			v-model="form.phone"
			type="tel"
			:label="t('helfer', 'Telefon (optional)')" />
		<template v-if="event.askGroup">
			<NcSelect v-if="event.groupOptions.length > 0"
				v-model="group"
				:options="event.groupOptions"
				:input-label="t('helfer', 'Gruppe / Abteilung / Verein')"
				:placeholder="t('helfer', 'Bitte auswählen')"
				required />
			<NcTextField v-else
				v-model="form.groupName"
				:label="t('helfer', 'Gruppe / Abteilung / Verein')"
				required />
		</template>
		<NcTextField v-for="question in event.questions"
			:key="question.id"
			v-model="form.answers[question.id]"
			:label="question.label + (question.required ? '' : ' ' + t('helfer', '(optional)'))"
			:required="question.required" />
		<NcTextField v-model="form.comment" :label="t('helfer', 'Kommentar (optional)')" />
		<div class="slot-form__actions">
			<NcButton @click="emit('cancel')">
				{{ t('helfer', 'Abbrechen') }}
			</NcButton>
			<NcButton variant="primary" type="submit" :disabled="submitting">
				{{ t('helfer', 'Eintragen') }}
			</NcButton>
		</div>
	</form>
</template>

<style scoped>
.slot-form {
	display: flex;
	flex-direction: column;
	gap: 8px;
	margin-top: 8px;
	padding: 12px;
	border-radius: var(--border-radius-large);
	background: var(--color-background-hover);
}

.slot-form__people {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: 4px;
	color: var(--color-text-maxcontrast);
}

.slot-form__actions {
	display: flex;
	justify-content: flex-end;
	gap: 8px;
}
</style>
