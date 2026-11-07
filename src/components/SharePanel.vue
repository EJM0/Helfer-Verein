<script setup lang="ts">
import { computed, ref } from 'vue'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { getCurrentUser } from '@nextcloud/auth'
import { t } from '../l10n.ts'
import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import { adminApi, errorMessage } from '../api.ts'
import { confirmAction } from '../confirm.ts'
import type { FullEvent, ShareEntry, Sharee } from '../types.ts'
import { mdiAccountMultipleOutline, mdiAccountRemove, mdiClose } from '../icons.ts'

const props = defineProps<{ data: FullEvent }>()
const emit = defineEmits<{ update: [data: FullEvent], leave: [] }>()

const currentUid = getCurrentUser()?.uid ?? null
const isMe = (share: ShareEntry) => share.type === 'user' && share.shareWith === currentUid
/** Access only through a group: the user is not listed themselves. */
const viaGroupOnly = computed(() => !props.data.isOwner && !props.data.shares.some(isMe))

interface Option extends Sharee {
	id: string
	label: string
}

const options = ref<Option[]>([])
const selected = ref<Option | null>(null)
const searching = ref(false)
let timer: ReturnType<typeof setTimeout> | undefined

function onSearch(query: string) {
	clearTimeout(timer)
	if (query.trim().length < 1) {
		options.value = []
		return
	}
	timer = setTimeout(async () => {
		searching.value = true
		try {
			const found = await adminApi.sharees(query)
			const taken = new Set(props.data.shares.map((s) => `${s.type}:${s.shareWith}`))
			options.value = found
				.filter((s) => !taken.has(`${s.type}:${s.shareWith}`))
				.map((s) => ({ ...s, id: `${s.type}:${s.shareWith}`, label: s.type === 'group' ? `${s.displayName} (${t('helfer', 'Gruppe')})` : s.displayName }))
		} catch (e) {
			showError(errorMessage(e, t('helfer', 'Suche fehlgeschlagen.')))
		} finally {
			searching.value = false
		}
	}, 250)
}

async function onSelect(option: Option | null) {
	if (!option) {
		return
	}
	try {
		emit('update', await adminApi.addShare(props.data.event.id, option.type, option.shareWith))
		showSuccess(t('helfer', 'Mit {name} geteilt', { name: option.displayName }))
	} catch (e) {
		showError(errorMessage(e, t('helfer', 'Teilen fehlgeschlagen.')))
	} finally {
		selected.value = null
		options.value = []
	}
}

async function remove(share: ShareEntry) {
	if (!await confirmAction(t('helfer', 'Freigabe entfernen'), t('helfer', 'Freigabe für {name} entfernen?', { name: share.displayName }), t('helfer', 'Entfernen'))) {
		return
	}
	try {
		emit('update', await adminApi.removeShare(props.data.event.id, share.id))
	} catch (e) {
		showError(errorMessage(e, t('helfer', 'Entfernen fehlgeschlagen.')))
	}
}
</script>

<template>
	<div class="share-panel">
		<NcNoteCard v-if="data.isOwner" type="info">
			{{ t('helfer', 'Personen und Gruppen, mit denen du die Umfrage teilst, können alles bearbeiten – Schichten, Stände, Helfer, Spenden und Einstellungen. Nur den Export-Pfad, das Löschen der Umfrage und das Teilen kannst ausschließlich du ändern.') }}
		</NcNoteCard>
		<NcNoteCard v-else type="info">
			{{ t('helfer', '{owner} hat diese Umfrage mit dir geteilt. Du kannst alles bearbeiten außer dem Export-Pfad.', { owner: data.owner.displayName }) }}
			<template v-if="viaGroupOnly">
				{{ t('helfer', 'Du hast Zugriff über eine Gruppe.') }}
			</template>
		</NcNoteCard>
		<div v-if="viaGroupOnly" class="share-panel__leave">
			<NcButton variant="error" @click="emit('leave')">
				<template #icon>
					<NcIconSvgWrapper :path="mdiAccountRemove" />
				</template>
				{{ t('helfer', 'Mich aus der Umfrage entfernen') }}
			</NcButton>
		</div>

		<NcSelect v-if="data.isOwner"
			v-model="selected"
			class="share-panel__search"
			:options="options"
			:loading="searching"
			:filterable="false"
			:input-label="t('helfer', 'Mit Personen oder Gruppen teilen')"
			:placeholder="t('helfer', 'Name oder Gruppe suchen …')"
			@search="onSearch"
			@update:model-value="onSelect" />

		<ul class="share-panel__list">
			<li>
				<NcAvatar :user="data.owner.uid" :display-name="data.owner.displayName" :size="32" />
				<span class="share-panel__name">{{ data.owner.displayName }}</span>
				<span class="share-panel__role">{{ t('helfer', 'Besitzer') }}</span>
			</li>
			<li v-for="share in data.shares" :key="share.id" :class="{ 'share-panel__me': isMe(share) }">
				<NcAvatar v-if="share.type === 'user'"
					:user="share.shareWith"
					:display-name="share.displayName"
					:size="32" />
				<span v-else class="share-panel__group">
					<NcIconSvgWrapper :path="mdiAccountMultipleOutline" :size="20" />
				</span>
				<span class="share-panel__name">
					{{ share.displayName }}
					<template v-if="share.type === 'group'"> ({{ t('helfer', 'Gruppe') }})</template>
					<template v-if="isMe(share)"> ({{ t('helfer', 'du') }})</template>
				</span>
				<span class="share-panel__role">{{ t('helfer', 'Kann bearbeiten') }}</span>
				<NcButton v-if="isMe(share) && !data.isOwner"
					variant="error"
					size="small"
					@click="emit('leave')">
					<template #icon>
						<NcIconSvgWrapper :path="mdiAccountRemove" :size="18" />
					</template>
					{{ t('helfer', 'Mich entfernen') }}
				</NcButton>
				<NcButton v-if="data.isOwner"
					variant="tertiary"
					:aria-label="t('helfer', 'Freigabe entfernen')"
					@click="remove(share)">
					<template #icon>
						<NcIconSvgWrapper :path="mdiClose" />
					</template>
				</NcButton>
			</li>
		</ul>
		<p v-if="data.shares.length === 0" class="share-panel__empty">
			{{ t('helfer', 'Noch mit niemandem geteilt.') }}
		</p>
	</div>
</template>

<style scoped>
.share-panel {
	max-width: 640px;
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.share-panel__search {
	width: 100%;
}

.share-panel__list li {
	display: flex;
	align-items: center;
	gap: 12px;
	min-height: 48px;
	border-bottom: 1px solid var(--color-border);
}

.share-panel__group {
	display: flex;
	align-items: center;
	justify-content: center;
	width: 32px;
	height: 32px;
	border-radius: 50%;
	background: var(--color-background-dark);
}

.share-panel__name {
	flex: 1;
}

.share-panel__me {
	font-weight: bold;
}

.share-panel__leave {
	display: flex;
}

.share-panel__role, .share-panel__empty {
	color: var(--color-text-maxcontrast);
}
</style>
