<script setup lang="ts">
import { t } from '../l10n.ts'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcDateTimePickerNative from '@nextcloud/vue/components/NcDateTimePickerNative'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { pickFolder } from '../folder.ts'
import { mdiDelete, mdiFolderOutline, mdiPlus } from '../icons.ts'
import type { Question } from '../types.ts'

export interface SettingsDraft {
	title: string
	description: string
	location: string
	contact: string
	contactEmail: string
	organizer: string
	startDate: Date | null
	endDate: Date | null
	signupDeadline: Date | null
	exportPeriodic: boolean
	closeDate: Date | null
	closeAction: 'close' | 'delete'
	exportFolder: string
	askEmail: boolean
	emailRequired: boolean
	askPhone: boolean
	askGroup: boolean
	groupOptions: string
	questions: Question[]
	publicNames: boolean
}

const props = defineProps<{ full?: boolean, exportLockedBy?: string | null }>()
const draft = defineModel<SettingsDraft>({ required: true })

async function chooseFolder() {
	const path = await pickFolder(draft.value.exportFolder || null)
	if (path !== null) {
		draft.value.exportFolder = path
	}
}

function addQuestion() {
	draft.value.questions.push({ id: '', label: '', required: false })
}

function removeQuestion(index: number) {
	draft.value.questions.splice(index, 1)
}
</script>

<template>
	<div class="settings-fields">
		<NcTextField v-model="draft.title" :label="t('helfer', 'Titel der Veranstaltung')" required />
		<template v-if="props.full">
			<NcTextField v-model="draft.location" :label="t('helfer', 'Ort')" />
			<h3>{{ t('helfer', 'Information') }}</h3>
			<NcTextField v-model="draft.organizer" :label="t('helfer', 'Veranstalter (z. B. Verein, Abteilung)')" />
			<div class="settings-fields__row">
				<NcTextField v-model="draft.contact" :label="t('helfer', 'Ansprechpartner (Name)')" />
				<NcTextField v-model="draft.contactEmail" type="email" :label="t('helfer', 'E-Mail des Ansprechpartners')" />
			</div>
			<NcTextArea v-model="draft.description" :label="t('helfer', 'Bemerkungen / Infos für Helfer')" resize="vertical" />
		</template>

		<h3>{{ t('helfer', 'Termine') }}</h3>
		<div class="settings-fields__row">
			<NcDateTimePickerNative v-model="draft.startDate"
				type="datetime-local"
				clearable
				:label="t('helfer', 'Beginn der Veranstaltung')" />
			<NcDateTimePickerNative v-model="draft.endDate"
				type="datetime-local"
				clearable
				:min="draft.startDate"
				:label="t('helfer', 'Enddatum der Veranstaltung')" />
		</div>
		<p class="settings-fields__hint">
			{{ t('helfer', 'Nach dem Enddatum sind über den Link keine Änderungen mehr möglich – nur noch hier in der Verwaltung – und die Helferliste wird automatisch exportiert (falls ein Ordner angegeben ist).') }}
		</p>
		<div class="settings-fields__row">
			<div>
				<NcDateTimePickerNative v-model="draft.signupDeadline"
					type="datetime-local"
					clearable
					:max="draft.endDate"
					:label="t('helfer', 'Anmeldeschluss (Eintragen möglich bis)')" />
				<p class="settings-fields__hint">
					{{ t('helfer', 'Optional, z. B. eine Woche vor der Veranstaltung. Danach können Helfer sich nicht mehr eintragen oder austragen. Ohne Anmeldeschluss gilt das Enddatum.') }}
				</p>
			</div>
			<div>
				<NcDateTimePickerNative v-model="draft.closeDate"
					type="datetime-local"
					clearable
					:min="draft.endDate"
					:label="t('helfer', 'Schließungsdatum')" />
				<p class="settings-fields__hint">
					{{ t('helfer', 'Danach ist der öffentliche Link nicht mehr erreichbar. Die Umfrage bleibt intern erhalten.') }}
				</p>
			</div>
		</div>
		<div v-if="draft.closeDate" class="settings-fields__radios">
			<NcCheckboxRadioSwitch v-model="draft.closeAction"
				type="radio"
				value="close"
				name="closeAction">
				{{ t('helfer', 'Link schließen (Seite zeigt „geschlossen“)') }}
			</NcCheckboxRadioSwitch>
			<NcCheckboxRadioSwitch v-model="draft.closeAction"
				type="radio"
				value="delete"
				name="closeAction">
				{{ t('helfer', 'Link löschen (Seite nicht mehr auffindbar)') }}
			</NcCheckboxRadioSwitch>
		</div>

		<h3>{{ t('helfer', 'Export') }}</h3>
		<div class="settings-fields__folder">
			<NcTextField v-model="draft.exportFolder"
				:disabled="!!props.exportLockedBy"
				:label="t('helfer', 'Ziel-Ordner für den Export')"
				:placeholder="t('helfer', 'z. B. /Verein/Helferlisten')"
				:trailing-button-label="t('helfer', 'Ordner leeren')"
				:show-trailing-button="draft.exportFolder !== '' && !props.exportLockedBy"
				@trailing-button-click="draft.exportFolder = ''" />
			<NcButton v-if="!props.exportLockedBy" class="settings-fields__folderbutton" @click="chooseFolder">
				<template #icon>
					<NcIconSvgWrapper :path="mdiFolderOutline" />
				</template>
				{{ t('helfer', 'Auswählen') }}
			</NcButton>
		</div>
		<p class="settings-fields__hint">
			<template v-if="props.exportLockedBy">
				{{ t('helfer', 'Der Export-Pfad liegt in den Dateien von {owner} und kann nur dort geändert werden. Über „Exportieren …“ kannst du jederzeit in deine eigenen Dateien exportieren.', { owner: props.exportLockedBy }) }}
			</template>
			<template v-else-if="draft.exportFolder && draft.endDate">
				{{ t('helfer', 'Nach dem Enddatum wird die endgültige Helferliste automatisch als Excel-Datei in diesen Ordner exportiert.') }}
			</template>
			<template v-else-if="draft.exportFolder">
				{{ t('helfer', 'Mit einem Enddatum wird danach zusätzlich die endgültige Liste exportiert. Manuell kannst du jederzeit exportieren.') }}
			</template>
			<template v-else>
				{{ t('helfer', 'Optional. Ohne Ordner kannst du die Liste jederzeit manuell exportieren oder herunterladen.') }}
			</template>
		</p>

		<NcCheckboxRadioSwitch v-if="draft.exportFolder"
			v-model="draft.exportPeriodic"
			:disabled="!!props.exportLockedBy"
			type="switch">
			{{ t('helfer', 'Bei Änderungen automatisch aktualisieren (höchstens alle 15 Minuten)') }}
		</NcCheckboxRadioSwitch>

		<template v-if="props.full">
			<h3>{{ t('helfer', 'Angaben der Helfer') }}</h3>
			<NcCheckboxRadioSwitch v-model="draft.askEmail" type="switch">
				{{ t('helfer', 'E-Mail-Adresse abfragen') }}
			</NcCheckboxRadioSwitch>
			<NcCheckboxRadioSwitch v-if="draft.askEmail"
				v-model="draft.emailRequired"
				type="switch"
				class="settings-fields__indent">
				{{ t('helfer', 'E-Mail-Adresse ist Pflicht (sonst freiwillig)') }}
			</NcCheckboxRadioSwitch>
			<NcCheckboxRadioSwitch v-model="draft.askPhone" type="switch">
				{{ t('helfer', 'Telefonnummer abfragen (optional)') }}
			</NcCheckboxRadioSwitch>
			<NcCheckboxRadioSwitch v-model="draft.askGroup" type="switch">
				{{ t('helfer', 'Gruppe / Abteilung / Verein abfragen') }}
			</NcCheckboxRadioSwitch>
			<NcTextArea v-if="draft.askGroup"
				v-model="draft.groupOptions"
				:label="t('helfer', 'Auswahlmöglichkeiten (eine pro Zeile, leer = freie Eingabe)')"
				resize="vertical" />
			<NcCheckboxRadioSwitch v-model="draft.publicNames" type="switch">
				{{ t('helfer', 'Namen der eingetragenen Helfer öffentlich anzeigen') }}
			</NcCheckboxRadioSwitch>

			<h4>{{ t('helfer', 'Eigene Fragen') }}</h4>
			<div v-for="(question, index) in draft.questions" :key="index" class="settings-fields__question">
				<NcTextField v-model="question.label" :label="t('helfer', 'Frage {n}', { n: index + 1 })" />
				<NcCheckboxRadioSwitch v-model="question.required">
					{{ t('helfer', 'Pflicht') }}
				</NcCheckboxRadioSwitch>
				<NcButton variant="tertiary" :aria-label="t('helfer', 'Frage entfernen')" @click="removeQuestion(index)">
					<template #icon>
						<NcIconSvgWrapper :path="mdiDelete" />
					</template>
				</NcButton>
			</div>
			<NcButton variant="tertiary" @click="addQuestion">
				<template #icon>
					<NcIconSvgWrapper :path="mdiPlus" />
				</template>
				{{ t('helfer', 'Frage hinzufügen (z. B. T-Shirt-Größe)') }}
			</NcButton>
		</template>
	</div>
</template>

<style scoped>
.settings-fields {
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.settings-fields h3 {
	margin: 16px 0 0;
	font-size: 1.1em;
	font-weight: bold;
}

.settings-fields h4 {
	margin: 12px 0 0;
	font-weight: bold;
}

.settings-fields__row {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
	gap: 12px;
}

.settings-fields__hint {
	color: var(--color-text-maxcontrast);
	font-size: 0.9em;
	margin: 4px 0 0;
}

.settings-fields__folder {
	display: flex;
	flex-wrap: wrap;
	gap: 8px;
	align-items: flex-end;
}

.settings-fields__folder > :first-child {
	flex: 1 1 240px;
}

.settings-fields__folderbutton {
	flex-shrink: 0;
}

.settings-fields__question {
	display: flex;
	gap: 8px;
	align-items: center;
}

.settings-fields__indent {
	margin-inline-start: 24px;
}

.settings-fields__radios {
	display: flex;
	flex-wrap: wrap;
	gap: 0 24px;
}
</style>
