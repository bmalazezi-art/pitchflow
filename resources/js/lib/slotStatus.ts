import { addDays } from './dateControls';

export type SlotStatus = 'past' | 'current' | 'available' | 'reserved' | 'closed';

type ReservationStatus = 'pending' | 'confirmed' | 'completed' | 'reserved' | 'occupied' | 'cancelled' | 'voided' | 'late_cancelled' | 'no_show' | 'closed' | null | undefined;

const pad = (value: number) => String(value).padStart(2, '0');
const resolveTimezone = (timezone: string) => timezone === 'Europe/Pristina' ? 'Europe/Belgrade' : timezone;

export function zonedNowInput(timezone = 'Europe/Belgrade') {
    const resolvedTimezone = resolveTimezone(timezone);

    try {
        const parts = new Intl.DateTimeFormat('en-CA', {
            timeZone: resolvedTimezone,
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            hourCycle: 'h23',
        }).formatToParts(new Date());
        const part = (type: Intl.DateTimeFormatPartTypes) => parts.find(item => item.type === type)?.value ?? '';

        return `${part('year')}-${part('month')}-${part('day')}T${part('hour')}:${part('minute')}`;
    } catch {
        const now = new Date();
        return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}T${pad(now.getHours())}:${pad(now.getMinutes())}`;
    }
}

export function isSlotBlockedByReservation(status?: ReservationStatus) {
    if (!status || ['cancelled', 'voided', 'closed'].includes(status)) {
        return false;
    }

    return true;
}

export function getSlotStatus({
    selectedDate,
    startTime,
    endTime,
    reservationStatus,
    timezone = 'Europe/Belgrade',
    now,
}: {
    selectedDate: string;
    startTime: string;
    endTime: string;
    reservationStatus?: ReservationStatus;
    timezone?: string;
    now?: string;
}): SlotStatus {
    const slotStart = `${selectedDate}T${startTime.slice(0, 5)}`;
    let slotEnd = `${selectedDate}T${endTime.slice(0, 5)}`;

    if (slotEnd <= slotStart) {
        slotEnd = `${addDays(selectedDate, 1)}T${endTime.slice(0, 5)}`;
    }

    const localNow = now ?? zonedNowInput(timezone);

    if (reservationStatus === 'closed') {
        return 'closed';
    }

    if (slotEnd <= localNow) {
        return 'past';
    }

    if (slotStart <= localNow && slotEnd > localNow) {
        return 'current';
    }

    return isSlotBlockedByReservation(reservationStatus)
        ? 'reserved'
        : 'available';
}
