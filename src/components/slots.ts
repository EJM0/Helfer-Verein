import type { FullEvent, Shift, Slot, Station } from '../types.ts'
import { formatDay, formatRange } from '../time.ts'

export interface SlotOption {
	slot: Slot
	shift: Shift
	station: Station
	label: string
}

/**
 * All slots in display order (time, then station).
 * @param data
 * @param tz
 */
export function slotOptions(data: Pick<FullEvent, 'shifts' | 'stations' | 'slots'>, tz: string): SlotOption[] {
	const result: SlotOption[] = []
	for (const shift of data.shifts) {
		for (const station of data.stations) {
			const slot = data.slots.find((s) => s.shiftId === shift.id && s.stationId === station.id)
			if (slot) {
				result.push({
					slot,
					shift,
					station,
					label: `${formatDay(shift.startTime, tz)}, ${formatRange(shift.startTime, shift.endTime, tz)} – ${station.title}`,
				})
			}
		}
	}
	return result
}
