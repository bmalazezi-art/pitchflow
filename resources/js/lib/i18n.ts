import { usePage } from '@inertiajs/react';
import type { SharedProps } from '../types';

const messages = {
    en: {
        dashboard: 'Dashboard', calendar: 'Calendar', reservations: 'Reservations', customers: 'Customers',
        fields: 'Football Fields', employees: 'Employees', reports: 'Reports', settings: 'Settings',
        organizations: 'Organizations', logout: 'Log out', search: 'Search', save: 'Save', cancel: 'Cancel',
        delete: 'Delete', edit: 'Edit', add: 'Add', close: 'Close', name: 'Name', email: 'Email',
        phone: 'Phone number', password: 'Password', language: 'Language', status: 'Status',
        actions: 'Actions', noResults: 'No results found.', todayReservations: "Today's reservations",
        todayRevenue: "Today's revenue", monthlyRevenue: 'Monthly revenue', occupancy: 'Occupancy rate',
        upcoming: 'Upcoming reservations', activeEmployees: 'Active employees', newReservation: 'New reservation',
        newField: 'Add football field', newEmployee: 'Add employee', customerName: 'Customer name',
        start: 'Start time', end: 'End time', payment: 'Payment status', notes: 'Internal notes',
        walkIn: 'Walk-in reservation', available: 'Available', occupied: 'Occupied', past: 'Past',
        selectCity: 'Select city', selectField: 'Select football field', chooseDate: 'Choose date',
        checkAvailability: 'Check field availability', login: 'Log in', register: 'Register your business',
    },
    sq: {
        dashboard: 'Paneli', calendar: 'Kalendari', reservations: 'Rezervimet', customers: 'Klientët',
        fields: 'Fushat e Futbollit', employees: 'Punonjësit', reports: 'Raportet', settings: 'Cilësimet',
        organizations: 'Organizatat', logout: 'Dil', search: 'Kërko', save: 'Ruaj', cancel: 'Anulo',
        delete: 'Fshi', edit: 'Ndrysho', add: 'Shto', close: 'Mbyll', name: 'Emri', email: 'Email',
        phone: 'Numri i telefonit', password: 'Fjalëkalimi', language: 'Gjuha', status: 'Statusi',
        actions: 'Veprimet', noResults: 'Nuk u gjetën rezultate.', todayReservations: 'Rezervimet e sotme',
        todayRevenue: 'Të hyrat e sotme', monthlyRevenue: 'Të hyrat mujore', occupancy: 'Shkalla e zënies',
        upcoming: 'Rezervimet e ardhshme', activeEmployees: 'Punonjës aktivë', newReservation: 'Rezervim i ri',
        newField: 'Shto fushë futbolli', newEmployee: 'Shto punonjës', customerName: 'Emri i klientit',
        start: 'Koha e fillimit', end: 'Koha e përfundimit', payment: 'Statusi i pagesës', notes: 'Shënime të brendshme',
        walkIn: 'Rezervim pa paralajmërim', available: 'E lirë', occupied: 'E zënë', past: 'Kaluar',
        selectCity: 'Zgjidh qytetin', selectField: 'Zgjidh fushën', chooseDate: 'Zgjidh datën',
        checkAvailability: 'Kontrollo disponueshmërinë', login: 'Hyr', register: 'Regjistro biznesin',
    },
} as const;

export function useTranslation() {
    const { locale } = usePage<SharedProps>().props;
    return (key: keyof typeof messages.en) => messages[locale]?.[key] ?? messages.en[key];
}
