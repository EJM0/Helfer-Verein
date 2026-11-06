<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActionLink from '@nextcloud/vue/components/NcActionLink'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import PlanBoard from './PlanBoard.vue'
import StructureEditor from './StructureEditor.vue'
import HelperTable from './HelperTable.vue'
import DonationEditor from './DonationEditor.vue'
import SharePanel from './SharePanel.vue'
import QrCodeDialog from './QrCodeDialog.vue'
import SettingsFields from './SettingsFields.vue'
import { draftFromEvent, draftToPayload } from './draft.ts'
import { adminApi, errorMessage } from '../api.ts'
import { pickFolder } from '../folder.ts'
import { confirmAction } from '../confirm.ts'
import { formatDateTime } from '../time.ts'
import type { FullEvent } from '../types.ts'
import {
	mdiAccountGroup, mdiCalendarClock, mdiCalendarExport, mdiGift, mdiShareVariant, mdiCog, mdiContentCopy, mdiDownload, mdiFolderOutline, mdiLinkVariant,
	mdiLinkVariantOff, mdiOpenInNew, mdiQrcode, mdiRefresh, mdiTableArrowRight, mdiStorefrontOutline,
} from '../icons.ts'

const props = defineProps<{ data: FullEvent }>()
const emit = defineEmits<{ update: [data: FullEvent], leave: [] }>()

type Tab = 'plan' | 'structure' | 'helpers' | 'donations' | 'share' | 'settings'
const tab = ref<Tab>(props.data.shifts.length === 0 || props.data.stations.length === 0 ? 'structure' : 'plan')
const event = computed(() => props.data.event)
const tz = computed(() => event.value.timezone)

const draft = ref(draftFromEvent(props.data.event))
const saving = ref(false)
const exporting = ref(false)
const qrOpen = ref(false)
watch(() => props.data.event.updatedAt, () => {
	if (!saving.value) {
		draft.value = draftFromEvent(props.data.event)
	}
})

const status = computed(() => {
	if (event.value.closed) {
		return { type: 'error' as const, text: t('helfer', 'Geschlossen – der öffentliche Link ist nicht mehr aktiv. Die Umfrage bleibt hier intern erhalten.') }
	}
	if (event.value.signupClosed && !event.value.ended) {
		return { type: 'warning' as const, text: t('helfer', 'Anmeldeschluss vorbei – Helfer können sich nicht mehr eintragen oder austragen. Änderungen sind nur noch hier möglich.') }
	}
	if (event.value.ended) {
		return { type: 'warning' as const, text: t('helfer', 'Beendet – Helfer können sich nicht mehr eintragen oder austragen. Änderungen sind nur noch hier möglich.') }
	}
	return null
})

const filled = computed(() => {
	let needed = 0
	let have = 0
	for (const slot of props.data.slots) {
		needed += slot.needed
		have += Math.min(slot.needed, props.data.signups.filter((s) => s.slotId === slot.id).length)
	}
	return { needed, have }
})

const exportInfo = computed(() => {
	if (!event.value.exportedAt) {
		return null
	}
	if (!event.value.exportPath) {
		return t('helfer', 'Automatischer Export am {date} fehlgeschlagen. Bitte Ordner prüfen und manuell exportieren.', { date: formatDateTime(event.value.exportedAt, tz.value) })
	}
	if (!props.data.isOwner) {
		return t('helfer', 'Automatischer Export am {date} in die Dateien von {owner}', { date: formatDateTime(event.value.exportedAt, tz.value), owner: props.data.owner.displayName })
	}
	return t('helfer', 'Zuletzt exportiert am {date} nach {path}', { date: formatDateTime(event.value.exportedAt, tz.value), path: event.value.exportPath })
})

async function save() {
	saving.value = true
	try {
		const data = await adminApi.update(event.value.id, draftToPayload(draft.value) as never)
		emit('update', data)
		draft.value = draftFromEvent(data.event)
		showSuccess(t('helfer', 'Gespeichert'))
	} catch (e) {
		showError(errorMessage(e, t('helfer', 'Speichern fehlgeschlagen.')))
	} finally {
		saving.value = false
	}
}

async function copyLink() {
	if (!props.data.publicUrl) {
		return
	}
	try {
		await navigator.clipboard.writeText(props.data.publicUrl)
		showSuccess(t('helfer', 'Link kopiert'))
	} catch {
		showError(t('helfer', 'Link konnte nicht kopiert werden. Bitte manuell markieren und kopieren.'))
	}
}

async function regenerate() {
	if (props.data.publicUrl
		&& !await confirmAction(t('helfer', 'Neuen Link erzeugen'), t('helfer', 'Einen neuen Link erzeugen? Der bisherige Link funktioniert dann nicht mehr.'))) {
		return
	}
	try {
		emit('update', await adminApi.regenerateToken(event.value.id))
	} catch (e) {
		showError(errorMessage(e, t('helfer', 'Link konnte nicht erzeugt werden.')))
	}
}

async function removeLink() {
	if (!await confirmAction(t('helfer', 'Link löschen'), t('helfer', 'Öffentlichen Link löschen? Helfer können die Umfrage dann nicht mehr öffnen.'), t('helfer', 'Löschen'))) {
		return
	}
	try {
		emit('update', await adminApi.removeToken(event.value.id))
	} catch (e) {
		showError(errorMessage(e, t('helfer', 'Link konnte nicht gelöscht werden.')))
	}
}

async function exportNow(choose: boolean) {
	// Shared users export into a folder of their own files.
	let folder: string | undefined = props.data.isOwner ? (event.value.exportFolder ?? undefined) : undefined
	if (choose || !folder) {
		const picked = await pickFolder(folder)
		if (picked === null) {
			return
		}
		folder = picked
	}
	exporting.value = true
	try {
		const result = await adminApi.exportToFolder(event.value.id, folder)
		emit('update', result)
		showSuccess(t('helfer', 'Exportiert nach {path}', { path: result.path }))
	} catch (e) {
		showError(errorMessage(e, t('helfer', 'Export fehlgeschlagen.')))
	} finally {
		exporting.value = false
	}
}

function download() {
	window.location.href = adminApi.downloadUrl(event.value.id)
}

function openPublic() {
	if (props.data.publicUrl) {
		window.open(props.data.publicUrl, '_blank')
	}
}

function openExportedFile() {
	const path = event.value.exportPath
	if (!path) {
		return
	}
	const dir = path.substring(0, path.lastIndexOf('/')) || '/'
	window.open(generateUrl('/apps/files/?dir={dir}', { dir }), '_blank')
}
</script>

<template>
	<div class="event-view">
		<header class="event-view__header">
			<div class="event-view__title">
				<h2>{{ event.title }}</h2>
				<p v-if="!data.isOwner" class="event-view__meta">
					{{ t('helfer', 'Geteilt von {owner}', { owner: data.owner.displayName }) }}
				</p>
				<p class="event-view__meta">
					<span v-if="filled.needed > 0">
						{{ t('helfer', '{have} von {needed} Plätzen belegt', { have: filled.have, needed: filled.needed }) }}
						· {{ t('helfer', '{n} Eintragungen', { n: data.signups.length }) }}
					</span>
					<span v-if="event.startDate"> · {{ t('helfer', 'Beginn: {date}', { date: formatDateTime(event.startDate, tz) }) }}</span>
					<span v-if="event.signupDeadline"> · {{ t('helfer', 'Anmeldeschluss: {date}', { date: formatDateTime(event.signupDeadline, tz) }) }}</span>
					<span v-if="event.endDate"> · {{ t('helfer', 'Ende: {date}', { date: formatDateTime(event.endDate, tz) }) }}</span>
					<span v-if="event.closeDate"> · {{ t('helfer', 'Schließung: {date}', { date: formatDateTime(event.closeDate, tz) }) }}</span>
				</p>
			</div>
			<div class="event-view__export">
				<NcButton :disabled="exporting" @click="exportNow(false)">
					<template #icon>
						<NcIconSvgWrapper :path="mdiTableArrowRight" />
					</template>
					{{ event.exportFolder && data.isOwner ? t('helfer', 'In Ordner exportieren') : t('helfer', 'Exportieren …') }}
				</NcButton>
				<NcActions>
					<NcActionButton v-if="data.isOwner" close-after-click @click="exportNow(true)">
						<template #icon>
							<NcIconSvgWrapper :path="mdiFolderOutline" />
						</template>
						{{ t('helfer', 'In anderen Ordner exportieren …') }}
					</NcActionButton>
					<NcActionButton close-after-click @click="download">
						<template #icon>
							<NcIconSvgWrapper :path="mdiDownload" />
						</template>
						{{ t('helfer', 'Excel-Datei herunterladen') }}
					</NcActionButton>
					<NcActionLink :href="adminApi.calendarUrl(event.id)" download>
						<template #icon>
							<NcIconSvgWrapper :path="mdiCalendarExport" />
						</template>
						{{ t('helfer', 'Alle Schichten als Kalender (.ics)') }}
					</NcActionLink>
				</NcActions>
			</div>
		</header>

		<NcNoteCard v-if="status" :type="status.type" :text="status.text" />
		<p v-if="exportInfo" class="event-view__exportinfo">
			{{ exportInfo }}
			<a v-if="event.exportPath && data.isOwner" href="#" @click.prevent="openExportedFile">{{ t('helfer', 'Ordner öffnen') }}</a>
		</p>

		<section class="event-view__link">
			<template v-if="data.publicUrl">
				<NcIconSvgWrapper :path="mdiLinkVariant" />
				<NcTextField class="event-view__url"
					:model-value="data.publicUrl"
					:label="t('helfer', 'Öffentlicher Link für Helfer')"
					readonly
					@focus="($event.target as HTMLInputElement).select()" />
				<NcButton variant="primary" @click="copyLink">
					<template #icon>
						<NcIconSvgWrapper :path="mdiContentCopy" />
					</template>
					{{ t('helfer', 'Link kopieren') }}
				</NcButton>
				<NcButton @click="qrOpen = true">
					<template #icon>
						<NcIconSvgWrapper :path="mdiQrcode" />
					</template>
					{{ t('helfer', 'QR-Code') }}
				</NcButton>
				<NcActions>
					<NcActionButton close-after-click @click="openPublic">
						<template #icon>
							<NcIconSvgWrapper :path="mdiOpenInNew" />
						</template>
						{{ t('helfer', 'Helferansicht öffnen') }}
					</NcActionButton>
					<NcActionButton close-after-click @click="regenerate">
						<template #icon>
							<NcIconSvgWrapper :path="mdiRefresh" />
						</template>
						{{ t('helfer', 'Neuen Link erzeugen') }}
					</NcActionButton>
					<NcActionButton close-after-click @click="removeLink">
						<template #icon>
							<NcIconSvgWrapper :path="mdiLinkVariantOff" />
						</template>
						{{ t('helfer', 'Link löschen') }}
					</NcActionButton>
				</NcActions>
				<QrCodeDialog v-if="qrOpen"
					:url="data.publicUrl"
					:title="event.title"
					@close="qrOpen = false" />
			</template>
			<template v-else>
				<NcIconSvgWrapper :path="mdiLinkVariantOff" />
				<span class="event-view__nolink">{{ t('helfer', 'Kein öffentlicher Link – die Umfrage ist nur intern sichtbar.') }}</span>
				<NcButton @click="regenerate">
					<template #icon>
						<NcIconSvgWrapper :path="mdiLinkVariant" />
					</template>
					{{ t('helfer', 'Öffentlichen Link erstellen') }}
				</NcButton>
			</template>
		</section>

		<nav class="event-view__tabs" role="tablist">
			<NcButton :variant="tab === 'plan' ? 'primary' : 'tertiary'"
				role="tab"
				:aria-selected="tab === 'plan'"
				@click="tab = 'plan'">
				<template #icon>
					<NcIconSvgWrapper :path="mdiCalendarClock" />
				</template>
				{{ t('helfer', 'Einteilung') }}
			</NcButton>
			<NcButton :variant="tab === 'structure' ? 'primary' : 'tertiary'"
				role="tab"
				:aria-selected="tab === 'structure'"
				@click="tab = 'structure'">
				<template #icon>
					<NcIconSvgWrapper :path="mdiStorefrontOutline" />
				</template>
				{{ t('helfer', 'Schichten & Stände') }}
			</NcButton>
			<NcButton :variant="tab === 'helpers' ? 'primary' : 'tertiary'"
				role="tab"
				:aria-selected="tab === 'helpers'"
				@click="tab = 'helpers'">
				<template #icon>
					<NcIconSvgWrapper :path="mdiAccountGroup" />
				</template>
				{{ t('helfer', 'Helferliste') }}
			</NcButton>
			<NcButton :variant="tab === 'donations' ? 'primary' : 'tertiary'"
				role="tab"
				:aria-selected="tab === 'donations'"
				@click="tab = 'donations'">
				<template #icon>
					<NcIconSvgWrapper :path="mdiGift" />
				</template>
				{{ t('helfer', 'Spenden') }}
			</NcButton>
			<NcButton :variant="tab === 'share' ? 'primary' : 'tertiary'"
				role="tab"
				:aria-selected="tab === 'share'"
				@click="tab = 'share'">
				<template #icon>
					<NcIconSvgWrapper :path="mdiShareVariant" />
				</template>
				{{ t('helfer', 'Teilen') }}
			</NcButton>
			<NcButton :variant="tab === 'settings' ? 'primary' : 'tertiary'"
				role="tab"
				:aria-selected="tab === 'settings'"
				@click="tab = 'settings'">
				<template #icon>
					<NcIconSvgWrapper :path="mdiCog" />
				</template>
				{{ t('helfer', 'Einstellungen') }}
			</NcButton>
		</nav>

		<PlanBoard v-if="tab === 'plan'"
			:data="data"
			@update="(d) => emit('update', d)"
			@edit-structure="tab = 'structure'" />
		<StructureEditor v-else-if="tab === 'structure'" :data="data" @update="(d) => emit('update', d)" />
		<HelperTable v-else-if="tab === 'helpers'" :data="data" @update="(d) => emit('update', d)" />
		<DonationEditor v-else-if="tab === 'donations'" :data="data" @update="(d) => emit('update', d)" />
		<SharePanel v-else-if="tab === 'share'"
			:data="data"
			@update="(d) => emit('update', d)"
			@leave="emit('leave')" />
		<form v-else class="event-view__settings" @submit.prevent="save">
			<SettingsFields v-model="draft" full :export-locked-by="data.isOwner ? null : data.owner.displayName" />
			<div class="event-view__save">
				<NcButton variant="primary" type="submit" :disabled="saving">
					{{ t('helfer', 'Speichern') }}
				</NcButton>
			</div>
		</form>
	</div>
</template>

<style scoped>
.event-view {
	padding: 16px 24px 48px 56px;
	max-width: 1200px;
	display: flex;
	flex-direction: column;
	gap: 12px;
}

@media (max-width: 1024px) {
	.event-view {
		padding: 56px 12px 32px;
	}
}

.event-view__header {
	display: flex;
	justify-content: space-between;
	gap: 16px;
	flex-wrap: wrap;
	align-items: flex-start;
}

.event-view__title h2 {
	margin: 0;
	font-size: 1.6em;
	font-weight: bold;
}

.event-view__meta, .event-view__exportinfo, .event-view__nolink {
	color: var(--color-text-maxcontrast);
	margin: 4px 0 0;
}

.event-view__exportinfo a {
	text-decoration: underline;
	margin-inline-start: 8px;
}

.event-view__export {
	display: flex;
	gap: 4px;
	align-items: center;
}

.event-view__link {
	display: flex;
	align-items: center;
	gap: 8px;
	padding: 8px 12px;
	border-radius: var(--border-radius-large);
	background: var(--color-background-dark);
	flex-wrap: wrap;
}

.event-view__url {
	flex: 1 1 260px;
	min-width: 0;
}

.event-view__tabs {
	display: flex;
	gap: 4px;
	flex-wrap: wrap;
	border-bottom: 1px solid var(--color-border);
	padding-bottom: 8px;
}

.event-view__settings {
	max-width: 760px;
}

.event-view__save {
	margin-top: 16px;
	display: flex;
	justify-content: flex-end;
}
</style>
