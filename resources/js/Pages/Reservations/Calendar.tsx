import { Head, usePage } from '@inertiajs/react';
import { lazy, Suspense } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import EmployeeBookingBoard from '../../Components/EmployeeBookingBoard';
import { useTranslation } from '../../lib/i18n';
import type { SharedProps } from '../../types';

export interface CalendarProps {
    reservations: any[];
    fields: any[];
    timezone: string;
    selectedField?: number | null;
    selectedReservation?: number | null;
    initialDate?: string;
}

const OwnerCalendar = lazy(() => import('../../Components/OwnerCalendar'));

export default function Calendar(props: CalendarProps) {
    const t = useTranslation();
    const { auth } = usePage<SharedProps>().props;

    if (auth.user?.role === 'employee') {
        return <AppLayout title={t('bookingBoard')}><Head title={t('bookingBoard')} /><EmployeeBookingBoard {...props} /></AppLayout>;
    }

    return <Suspense fallback={<AppLayout title={t('calendar')}><div className="calendar-shell">{t('calendar')}</div></AppLayout>}><OwnerCalendar {...props} /></Suspense>;
}
