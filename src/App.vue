<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcAppNavigation from '@nextcloud/vue/components/NcAppNavigation'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcAppNavigationNew from '@nextcloud/vue/components/NcAppNavigationNew'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcContent from '@nextcloud/vue/components/NcContent'
import NcCounterBubble from '@nextcloud/vue/components/NcCounterBubble'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import EventView from './components/EventView.vue'
import CreateDialog from './components/CreateDialog.vue'
import { adminApi, errorMessage } from './api.ts'
import { confirmAction } from './confirm.ts'
import type { FullEvent, HelferEvent } from './types.ts'
import { mdiAccountGroup, mdiClipboardTextOutline, mdiDelete, mdiLockOutline, mdiPlus, mdiContentCopy } from './icons.ts'

const events = ref<HelferEvent[]>([])
const loading = ref(true)
const current = ref<FullEvent | null>(null)
const loadingEvent = ref(false)
const showCreate = ref(false)

const currentId = computed(() => current.value?.event.id ?? null)

async function loadList() {
	try {
		events.value = await adminApi.list()
	} catch (e) {
		showError(errorMessage(e, t('helfer', 'Umfragen konnten nicht geladen werden.')))
	} finally {
		loading.value = false
	}
}

async function open(id: number) {
	if (currentId.value === id) {
		return
	}
	loadingEvent.value = true
	try {
		current.value = await adminApi.get(id)
		window.location.hash = `#/umfrage/${id}`
	} catch (e) {
		showError(errorMessage(e, t('helfer', 'Umfrage konnte nicht geladen werden.')))
	} finally {
		loadingEvent.value = false
	}
}

function onUpdated(data: FullEvent) {
	current.value = data
	const index = events.value.findIndex((e) => e.id === data.event.id)
	const stats = computeStats(data)
	const entry = { ...data.event, stats, publicUrl: data.publicUrl }
	if (index === -1) {
		events.value.unshift(entry)
	} else {
		events.value.splice(index, 1, entry)
	}
}

function computeStats(data: FullEvent) {
	let needed = 0
	let filled = 0
	for (const slot of data.slots) {
		const count = data.signups.filter((s) => s.slotId === slot.id).length
		needed += slot.needed
		filled += Math.min(slot.needed, count)
	}
	return { needed, filled, signups: data.signups.length }
}

async function onCreated(data: FullEvent) {
	showCreate.value = false
	onUpdated(data)
	window.location.hash = `#/umfrage/${data.event.id}`
}

async function duplicate(id: number) {
	try {
		const data = await adminApi.duplicate(id)
		onUpdated(data)
		window.location.hash = `#/umfrage/${data.event.id}`
		showSuccess(t('helfer', 'Umfrage kopiert'))
	} catch (e) {
		showError(errorMessage(e, t('helfer', 'Kopieren fehlgeschlagen.')))
	}
}

async function remove(event: HelferEvent) {
	if (!await confirmAction(t('helfer', 'Umfrage löschen'), t('helfer', 'Umfrage „{title}“ mit allen Helfern endgültig löschen?', { title: event.title }), t('helfer', 'Löschen'))) {
		return
	}
	try {
		await adminApi.remove(event.id)
		events.value = events.value.filter((e) => e.id !== event.id)
		if (currentId.value === event.id) {
			current.value = null
			window.location.hash = ''
		}
	} catch (e) {
		showError(errorMessage(e, t('helfer', 'Löschen fehlgeschlagen.')))
	}
}

onMounted(async () => {
	await loadList()
	const match = window.location.hash.match(/^#\/umfrage\/(\d+)/)
	if (match) {
		await open(Number(match[1]))
	} else if (events.value.length > 0) {
		await open(events.value[0].id)
	}
})
</script>

<template>
	<NcContent app-name="helfer">
		<NcAppNavigation :aria-label="t('helfer', 'Umfragen')">
			<NcAppNavigationNew :text="t('helfer', 'Neue Umfrage')" @click="showCreate = true">
				<template #icon>
					<NcIconSvgWrapper :path="mdiPlus" />
				</template>
			</NcAppNavigationNew>
			<template #list>
				<NcLoadingIcon v-if="loading" :size="32" />
				<NcAppNavigationItem v-for="event in events"
					:key="event.id"
					:name="event.title"
					:active="event.id === currentId"
					@click="open(event.id)">
					<template #icon>
						<NcIconSvgWrapper :path="event.closed ? mdiLockOutline : mdiClipboardTextOutline" />
					</template>
					<template #counter>
						<NcCounterBubble v-if="event.stats && event.stats.signups > 0" :count="event.stats.signups" />
					</template>
					<template #actions>
						<NcActionButton close-after-click @click="duplicate(event.id)">
							<template #icon>
								<NcIconSvgWrapper :path="mdiContentCopy" />
							</template>
							{{ t('helfer', 'Kopieren') }}
						</NcActionButton>
						<NcActionButton close-after-click @click="remove(event)">
							<template #icon>
								<NcIconSvgWrapper :path="mdiDelete" />
							</template>
							{{ t('helfer', 'Löschen') }}
						</NcActionButton>
					</template>
				</NcAppNavigationItem>
			</template>
		</NcAppNavigation>

		<NcAppContent>
			<div v-if="loadingEvent && !current" class="helfer-center">
				<NcLoadingIcon :size="48" />
			</div>
			<EventView v-else-if="current"
				:key="current.event.id"
				:data="current"
				@update="onUpdated" />
			<NcEmptyContent v-else-if="!loading"
				:name="t('helfer', 'Noch keine Umfrage')"
				:description="t('helfer', 'Lege eine Umfrage für deine Veranstaltung an, plane Schichten und Stände und teile den Link mit deinen Helfern.')">
				<template #icon>
					<NcIconSvgWrapper :path="mdiAccountGroup" />
				</template>
				<template #action>
					<NcButton variant="primary" @click="showCreate = true">
						{{ t('helfer', 'Neue Umfrage') }}
					</NcButton>
				</template>
			</NcEmptyContent>
		</NcAppContent>

		<CreateDialog v-if="showCreate" @close="showCreate = false" @created="onCreated" />
	</NcContent>
</template>

<style scoped>
.helfer-center {
	display: flex;
	justify-content: center;
	padding: 64px;
}
</style>
