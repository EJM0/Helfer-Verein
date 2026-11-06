import { getFilePickerBuilder } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'

/**
 * Let the user pick a folder in their Nextcloud files. Resolves to null when cancelled.
 * @param startAt
 */
export async function pickFolder(startAt?: string | null): Promise<string | null> {
	const builder = getFilePickerBuilder(t('helfer', 'Export-Ordner auswählen'))
		.setMultiSelect(false)
		.setMimeTypeFilter(['httpd/unix-directory'])
		.allowDirectories(true)
		.addButton({
			label: t('helfer', 'Diesen Ordner wählen'),
			variant: 'primary',
			callback: () => {},
		})
	if (startAt) {
		builder.startAt(startAt)
	}
	try {
		const path = await builder.build().pick()
		return path || '/'
	} catch {
		return null
	}
}
