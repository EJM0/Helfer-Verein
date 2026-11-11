<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { showError, showSuccess, showWarning } from '@nextcloud/dialogs'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { adminApi, errorMessage, type MailAccount } from '../api.ts'
import { t } from '../l10n.ts'
import { formatDay, formatRange } from '../time.ts'
import type { FullEvent } from '../types.ts'
import { mdiContentCopy, mdiEmailOutline } from '../icons.ts'

const props = defineProps<{ data: FullEvent }>()
const emit = defineEmits<{ close: [] }>()

/** Longest mailto link that common mail programs and browsers accept reliably. */
const MAILTO_LIMIT = 1900

interface Person {
	email: string
	name: string
	details: string[]
	slotIds: Set<number>
	donor: boolean
}

const tz = computed(() => props.data.event.timezone)

// Everybody with an e-mail address, once per address.
const people = computed<Person[]>(() => {
	const map = new Map<string, Person>()
	const stationOf = new Map(props.data.slots.map((s) => [s.id, props.data.stations.find((st) => st.id === s.stationId)?.title ?? '']))
	const add = (email: string | undefined, name: string | null, detail: string, slotId?: number, donor = false) => {
		const key = (email ?? '').trim().toLowerCase()
		if (!key) {
			return
		}
		const person = map.get(key) ?? { email: key, name: name ?? key, details: [], slotIds: new Set<number>(), donor: false }
		if (detail && !person.details.includes(detail)) {
			person.details.push(detail)
		}
		if (slotId !== undefined) {
			person.slotIds.add(slotId)
		}
		person.donor ||= donor
		map.set(key, person)
	}
	for (const signup of props.data.signups) {
		add(signup.email, signup.name, stationOf.get(signup.slotId) ?? '', signup.slotId)
	}
	for (const donation of props.data.donations) {
		add(donation.email, donation.name, t('helfer', 'Spende: {item}', { item: donation.item }), undefined, true)
	}
	return [...map.values()].sort((a, b) => a.name.localeCompare(b.name))
})

const withoutEmail = computed(() => {
	const names = new Set<string>()
	for (const s of props.data.signups) {
		if (!s.email) {
			names.add((s.name ?? '').toLowerCase())
		}
	}
	return names.size
})

const selected = ref<Set<string>>(new Set(people.value.map((p) => p.email)))
const subject = ref(props.data.event.title)
const body = ref('')
const accounts = ref<MailAccount[]>([])
const account = ref<MailAccount | null>(null)
const loadingAccounts = ref(true)
const sending = ref(false)

const selectedList = computed(() => people.value.filter((p) => selected.value.has(p.email)).map((p) => p.email))

// Quick selection: by shift, by station or the donors.
const groups = computed(() => {
	const result: { id: string, label: string, emails: string[] }[] = []
	for (const shift of props.data.shifts) {
		const slotIds = new Set(props.data.slots.filter((s) => s.shiftId === shift.id).map((s) => s.id))
		const emails = people.value.filter((p) => [...p.slotIds].some((id) => slotIds.has(id))).map((p) => p.email)
		if (emails.length > 0) {
			result.push({ id: `shift-${shift.id}`, label: `${t('helfer', 'Schicht')}: ${formatDay(shift.startTime, tz.value)}, ${formatRange(shift.startTime, shift.endTime, tz.value)}${shift.title ? ' (' + shift.title + ')' : ''}`, emails })
		}
	}
	for (const station of props.data.stations) {
		const slotIds = new Set(props.data.slots.filter((s) => s.stationId === station.id).map((s) => s.id))
		const emails = people.value.filter((p) => [...p.slotIds].some((id) => slotIds.has(id))).map((p) => p.email)
		if (emails.length > 0) {
			result.push({ id: `station-${station.id}`, label: `${t('helfer', 'Stand')}: ${station.title}`, emails })
		}
	}
	const donors = people.value.filter((p) => p.donor).map((p) => p.email)
	if (donors.length > 0) {
		result.push({ id: 'donors', label: t('helfer', 'Alle Spender'), emails: donors })
	}
	return result
})
const group = ref<{ id: string, label: string, emails: string[] } | null>(null)

function selectGroup(value: { id: string, label: string, emails: string[] } | null) {
	group.value = value
	if (value) {
		selected.value = new Set(value.emails)
	}
}

function selectAll(all: boolean) {
	group.value = null
	selected.value = new Set(all ? people.value.map((p) => p.email) : [])
}

function toggle(email: string, on: boolean) {
	group.value = null
	const next = new Set(selected.value)
	if (on) {
		next.add(email)
	} else {
		next.delete(email)
	}
	selected.value = next
}

async function copyAddresses(silent = false) {
	try {
		await navigator.clipboard.writeText(selectedList.value.join(', '))
		if (!silent) {
			showSuccess(t('helfer', '{n} Adressen kopiert', { n: selectedList.value.length }))
		}
		return true
	} catch {
		showError(t('helfer', 'Adressen konnten nicht kopiert werden.'))
		return false
	}
}

/** Open the default mail program; helpers go into BCC so they do not see each other. */
async function openMailProgram() {
	if (selectedList.value.length === 0) {
		showError(t('helfer', 'Bitte mindestens einen Empfänger auswählen.'))
		return
	}
	const params = (withBcc: boolean) => [
		withBcc ? 'bcc=' + selectedList.value.map(encodeURIComponent).join(',') : '',
		'subject=' + encodeURIComponent(subject.value),
		body.value ? 'body=' + encodeURIComponent(body.value) : '',
	].filter(Boolean).join('&')
	let url = 'mailto:?' + params(true)
	if (url.length > MAILTO_LIMIT) {
		// Too many addresses for a mailto link: copy them, open the mail program without them.
		url = 'mailto:?' + params(false)
		if (await copyAddresses(true)) {
			showWarning(t('helfer', 'Zu viele Empfänger für einen direkten Link: Die {n} Adressen sind in der Zwischenablage – bitte im E-Mail-Programm ins BCC-Feld einfügen.', { n: selectedList.value.length }), { timeout: 15000 })
		}
	}
	window.location.href = url
}

async function send() {
	if (!account.value) {
		return
	}
	sending.value = true
	try {
		const result = await adminApi.sendMail(props.data.event.id, {
			providerId: account.value.providerId,
			serviceId: account.value.serviceId,
			emails: selectedList.value,
			subject: subject.value,
			body: body.value,
		})
		showSuccess(t('helfer', 'E-Mail an {n} Empfänger an die Mail-App übergeben. Den Versandstatus siehst du dort im Postausgang bzw. Gesendet-Ordner.', { n: result.sent }), { timeout: 10000 })
		emit('close')
	} catch (e) {
		showError(errorMessage(e, t('helfer', 'Die E-Mail konnte nicht gesendet werden.')))
	} finally {
		sending.value = false
	}
}

onMounted(async () => {
	try {
		accounts.value = await adminApi.mailAccounts()
		account.value = accounts.value[0] ?? null
	} catch {
		accounts.value = []
	} finally {
		loadingAccounts.value = false
	}
})

const accountOptions = computed(() => accounts.value.map((a) => ({ ...a, id: `${a.providerId}/${a.serviceId}`, label: a.label && a.label !== a.address ? `${a.label} <${a.address}>` : a.address })))
const accountOption = computed({
	get: () => accountOptions.value.find((o) => account.value && o.serviceId === account.value.serviceId && o.providerId === account.value.providerId) ?? null,
	set: (value: MailAccount | null) => { account.value = value },
})
</script>

<template>
	<NcDialog :name="t('helfer', 'E-Mail an Helfer')"
		size="large"
		:open="true"
		@update:open="(open: boolean) => !open && emit('close')">
		<div class="mail-dialog">
			<NcNoteCard v-if="people.length === 0" type="warning" :text="t('helfer', 'Bisher hat niemand eine E-Mail-Adresse angegeben.')" />
			<template v-else>
				<section class="mail-dialog__recipients">
					<div class="mail-dialog__head">
						<strong>{{ t('helfer', 'Empfänger: {n} von {total} ausgewählt', { n: selectedList.length, total: people.length }) }}</strong>
						<div class="mail-dialog__quick">
							<NcButton size="small" @click="selectAll(true)">
								{{ t('helfer', 'Alle') }}
							</NcButton>
							<NcButton size="small" @click="selectAll(false)">
								{{ t('helfer', 'Keine') }}
							</NcButton>
						</div>
					</div>
					<NcSelect v-if="groups.length > 0"
						:model-value="group"
						:options="groups"
						:input-label="t('helfer', 'Auswahl nach Schicht, Stand oder Spendern')"
						:placeholder="t('helfer', 'z. B. alle vom Kuchenstand')"
						@update:model-value="selectGroup" />
					<ul class="mail-dialog__list">
						<li v-for="person in people" :key="person.email">
							<NcCheckboxRadioSwitch :model-value="selected.has(person.email)" @update:model-value="(v: boolean) => toggle(person.email, v)">
								<span class="mail-dialog__person">
									<strong>{{ person.name }}</strong>
									<span>{{ person.email }}<template v-if="person.details.length"> · {{ person.details.join(', ') }}</template></span>
								</span>
							</NcCheckboxRadioSwitch>
						</li>
					</ul>
					<p v-if="withoutEmail > 0" class="mail-dialog__hint">
						{{ withoutEmail === 1 ? t('helfer', '1 Helfer hat keine E-Mail-Adresse angegeben und wird nicht erreicht.') : t('helfer', '{n} Helfer haben keine E-Mail-Adresse angegeben und werden nicht erreicht.', { n: withoutEmail }) }}
					</p>
				</section>

				<section class="mail-dialog__compose">
					<div v-if="loadingAccounts" class="mail-dialog__loading">
						<NcLoadingIcon :size="24" />
					</div>
					<template v-else-if="accounts.length > 0">
						<NcSelect v-if="accounts.length > 1"
							v-model="accountOption"
							:options="accountOptions"
							:input-label="t('helfer', 'Absender (Konto aus der Mail-App)')"
							:clearable="false" />
						<p v-else class="mail-dialog__hint">
							{{ t('helfer', 'Absender: {account} (Mail-App)', { account: accountOptions[0].label }) }}
						</p>
					</template>
					<NcNoteCard v-else type="info">
						{{ t('helfer', 'In der Nextcloud-Mail-App ist kein E-Mail-Konto eingerichtet. Die E-Mail wird deshalb in deinem Standard-E-Mail-Programm geöffnet.') }}
					</NcNoteCard>

					<NcTextField v-model="subject" :label="t('helfer', 'Betreff')" required />
					<NcTextArea v-model="body"
						:label="t('helfer', 'Nachricht')"
						resize="vertical"
						rows="8" />
					<p class="mail-dialog__hint">
						{{ t('helfer', 'Alle Empfänger stehen im BCC und sehen die Adressen der anderen nicht.') }}
						<template v-if="accounts.length > 0">
							{{ t('helfer', 'Du selbst erhältst die E-Mail als Empfänger „An“, sie liegt danach auch in deinem Gesendet-Ordner.') }}
						</template>
					</p>
				</section>
			</template>
		</div>
		<template #actions>
			<NcButton v-if="people.length > 0" variant="tertiary" @click="copyAddresses()">
				<template #icon>
					<NcIconSvgWrapper :path="mdiContentCopy" />
				</template>
				{{ t('helfer', 'Adressen kopieren') }}
			</NcButton>
			<NcButton v-if="people.length > 0 && accounts.length > 0" variant="secondary" @click="openMailProgram">
				{{ t('helfer', 'Im E-Mail-Programm öffnen') }}
			</NcButton>
			<NcButton v-if="people.length > 0 && accounts.length > 0"
				variant="primary"
				:disabled="sending || selectedList.length === 0 || !subject.trim() || !body.trim()"
				@click="send">
				<template #icon>
					<NcLoadingIcon v-if="sending" :size="20" />
					<NcIconSvgWrapper v-else :path="mdiEmailOutline" />
				</template>
				{{ t('helfer', 'Senden ({n})', { n: selectedList.length }) }}
			</NcButton>
			<NcButton v-else-if="people.length > 0 && !loadingAccounts"
				variant="primary"
				:disabled="selectedList.length === 0"
				@click="openMailProgram">
				<template #icon>
					<NcIconSvgWrapper :path="mdiEmailOutline" />
				</template>
				{{ t('helfer', 'Im E-Mail-Programm öffnen ({n})', { n: selectedList.length }) }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<style scoped>
.mail-dialog {
	display: grid;
	grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
	gap: 24px;
}

@media (max-width: 900px) {
	.mail-dialog {
		grid-template-columns: 1fr;
	}
}

.mail-dialog > :only-child {
	grid-column: 1 / -1;
}

.mail-dialog section {
	display: flex;
	flex-direction: column;
	gap: 8px;
	min-width: 0;
}

.mail-dialog__head {
	display: flex;
	justify-content: space-between;
	align-items: center;
	gap: 8px;
	flex-wrap: wrap;
}

.mail-dialog__quick {
	display: flex;
	gap: 4px;
}

.mail-dialog__list {
	max-height: 320px;
	overflow-y: auto;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	padding: 4px 8px;
}

.mail-dialog__person {
	display: flex;
	flex-direction: column;
	min-width: 0;
}

.mail-dialog__person span, .mail-dialog__hint {
	color: var(--color-text-maxcontrast);
	font-size: 0.95em;
	overflow-wrap: anywhere;
}

.mail-dialog__hint {
	margin: 0;
}

.mail-dialog__loading {
	display: flex;
	justify-content: center;
	padding: 8px;
}
</style>
