import { showConfirmation } from '@nextcloud/dialogs'
import { t } from './l10n.ts'

/**
 * Nextcloud styled yes/no question. Resolves to false when the dialog is closed.
 * @param name
 * @param text
 * @param labelConfirm
 */
export async function confirmAction(name: string, text: string, labelConfirm?: string): Promise<boolean> {
	try {
		return await showConfirmation({
			name,
			text,
			labelConfirm: labelConfirm ?? t('helfer', 'Ja'),
			labelReject: t('helfer', 'Abbrechen'),
			severity: 'warning',
		})
	} catch {
		return false
	}
}
