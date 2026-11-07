<script setup lang="ts">
import { ref, watch } from 'vue'
import { t } from '../l10n.ts'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import { drawQrCode, loadThemingLogo } from '../qrcode.ts'
import { mdiDownload } from '../icons.ts'

const props = defineProps<{ url: string, title: string }>()
const emit = defineEmits<{ close: [] }>()

const canvas = ref<HTMLCanvasElement | null>(null)
const ready = ref(false)

watch(canvas, async (element) => {
	if (element) {
		drawQrCode(element, props.url, await loadThemingLogo())
		ready.value = true
	}
})

function download() {
	const link = document.createElement('a')
	link.href = canvas.value!.toDataURL('image/png')
	link.download = `QR-Code ${props.title}.png`
	link.click()
}
</script>

<template>
	<NcDialog :name="t('helfer', 'QR-Code')"
		:open="true"
		size="small"
		@update:open="(open: boolean) => !open && emit('close')">
		<canvas ref="canvas"
			class="qr-dialog__code"
			role="img"
			:aria-label="t('helfer', 'QR-Code für {url}', { url })" />
		<template #actions>
			<NcButton variant="primary" :disabled="!ready" @click="download">
				<template #icon>
					<NcIconSvgWrapper :path="mdiDownload" />
				</template>
				{{ t('helfer', 'Als PNG herunterladen') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<style scoped>
.qr-dialog__code {
	display: block;
	width: 100%;
	max-width: 320px;
	margin: 0 auto;
}
</style>
