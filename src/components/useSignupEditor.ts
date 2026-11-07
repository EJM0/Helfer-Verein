import { ref } from 'vue'
import { showError } from '@nextcloud/dialogs'
import { t } from '../l10n.ts'
import { adminApi, errorMessage } from '../api.ts'
import { confirmAction } from '../confirm.ts'
import type { FullEvent, Signup } from '../types.ts'

/**
 * Shared add / edit / delete logic for helpers in the admin views.
 * @param getData
 * @param emitUpdate
 */
export function useSignupEditor(getData: () => FullEvent, emitUpdate: (data: FullEvent) => void) {
	const editing = ref<{ signup: Signup | null, slotId: number } | null>(null)

	function add(slotId: number) {
		editing.value = { signup: null, slotId }
	}

	function edit(signup: Signup) {
		editing.value = { signup, slotId: signup.slotId }
	}

	async function save(payload: Record<string, unknown>) {
		const current = editing.value
		if (!current) {
			return
		}
		const id = getData().event.id
		try {
			const data = current.signup
				? await adminApi.updateSignup(id, current.signup.id, payload)
				: await adminApi.addSignup(id, payload)
			emitUpdate(data)
			editing.value = null
		} catch (e) {
			showError(errorMessage(e, t('helfer', 'Speichern fehlgeschlagen.')))
		}
	}

	async function remove(signup?: Signup | null) {
		const target = signup ?? editing.value?.signup
		if (!target) {
			return
		}
		if (!await confirmAction(t('helfer', 'Austragen'), t('helfer', '{name} austragen?', { name: target.name ?? '' }), t('helfer', 'Austragen'))) {
			return
		}
		try {
			emitUpdate(await adminApi.deleteSignup(getData().event.id, target.id))
			editing.value = null
		} catch (e) {
			showError(errorMessage(e, t('helfer', 'Austragen fehlgeschlagen.')))
		}
	}

	return { editing, add, edit, save, remove }
}
