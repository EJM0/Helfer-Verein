import { generateUrl } from '@nextcloud/router'
import { encode } from 'uqr'

const MIN_SIZE_PX = 1024
const QUIET_ZONE_MODULES = 4
// Share of the code's width freed for the logo. Error correction H recovers up to 30 % of the data.
const LOGO_HOLE_RATIO = 0.22

/**
 * The logo set in the Nextcloud theming settings, or null if there is none.
 */
export function loadThemingLogo(): Promise<HTMLImageElement | null> {
	return new Promise((resolve) => {
		const image = new Image()
		image.onload = () => resolve(image.naturalWidth > 0 ? image : null)
		image.onerror = () => resolve(null)
		image.src = generateUrl('/apps/theming/image/logo')
	})
}

/**
 * Finds the visible part of the logo, theming logos often have wide transparent margins.
 * @param ctx
 * @param logo
 */
function visibleBox(ctx: CanvasRenderingContext2D, logo: HTMLImageElement) {
	const { width, height } = logo
	ctx.canvas.width = width
	ctx.canvas.height = height
	ctx.drawImage(logo, 0, 0)
	const { data } = ctx.getImageData(0, 0, width, height)
	let left = width
	let right = 0
	let top = height
	let bottom = 0
	for (let y = 0; y < height; y++) {
		for (let x = 0; x < width; x++) {
			if (data[(y * width + x) * 4 + 3] > 16) {
				left = Math.min(left, x)
				right = Math.max(right, x + 1)
				top = Math.min(top, y)
				bottom = Math.max(bottom, y + 1)
			}
		}
	}
	return right > left ? { x: left, y: top, width: right - left, height: bottom - top } : { x: 0, y: 0, width, height }
}

/**
 * Draws the QR code for the text, with the logo in the middle if there is one.
 * @param canvas
 * @param text
 * @param logo
 */
export function drawQrCode(canvas: HTMLCanvasElement, text: string, logo: HTMLImageElement | null): void {
	const ctx = canvas.getContext('2d', { willReadFrequently: true })!
	const box = logo && visibleBox(ctx, logo)
	const { data: modules, size } = encode(text, { ecc: 'H', border: QUIET_ZONE_MODULES })
	const scale = Math.ceil(MIN_SIZE_PX / size)
	// A QR code has an odd number of modules, an odd hole stays centered on the grid.
	const hole = logo ? Math.round(size * LOGO_HOLE_RATIO) | 1 : 0
	const holeStart = (size - hole) / 2
	const inHole = (i: number) => i >= holeStart && i < holeStart + hole

	canvas.width = canvas.height = size * scale
	ctx.fillStyle = '#fff'
	ctx.fillRect(0, 0, canvas.width, canvas.height)
	ctx.fillStyle = '#000'
	modules.forEach((row, y) => row.forEach((dark, x) => {
		if (dark && !(inHole(x) && inHole(y))) {
			ctx.fillRect(x * scale, y * scale, scale, scale)
		}
	}))

	if (logo && box) {
		const available = (hole - 1) * scale
		const fit = available / Math.max(box.width, box.height)
		const width = box.width * fit
		const height = box.height * fit
		ctx.imageSmoothingQuality = 'high'
		ctx.drawImage(logo, box.x, box.y, box.width, box.height, (canvas.width - width) / 2, (canvas.height - height) / 2, width, height)
	}
}
