import { translate, translatePlural } from '@nextcloud/l10n'

/*
 * Translation helpers for this app.
 *
 * @nextcloud/l10n HTML-escapes placeholder values and sanitizes the result with DOMPurify by default
 * ("Sport & Spiel" → "Sport &amp; Spiel"). That is meant for strings inserted as HTML. This app only renders text through Vue's text
 * interpolation (which escapes by itself) or through Nextcloud dialogs/toasts in text mode; v-html
 * is never used. Escaping here would therefore show "&amp;" to the user, so it is switched off.
 */

type Vars = Record<string, string | number>

export function t(app: string, text: string, vars?: Vars, count?: number): string {
	return translate(app, text, vars, count, { escape: false, sanitize: false })
}

export function n(app: string, singular: string, plural: string, count: number, vars?: Vars): string {
	return translatePlural(app, singular, plural, count, vars, { escape: false, sanitize: false })
}
