import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, ShieldCheck } from 'lucide-react';
import { useEffect, useState } from 'react';
import { PublicNav } from './Availability';
import { useTranslation } from '../../lib/i18n';
import type { SharedProps } from '../../types';

export default function Legal({ document }: { document: 'privacy' | 'terms' }) {
    const t = useTranslation();
    const { locale } = usePage<SharedProps>().props;
    const [dark, setDark] = useState(() => localStorage.getItem('public-theme') === 'dark');
    const title = document === 'privacy' ? t('privacyPolicy') : t('terms');
    const sections = document === 'privacy'
        ? [[t('informationWeUse'), t('privacyInformationText')], [t('publicPrivacy'), t('publicPrivacyText')], [t('contact'), t('legalContactText')]]
        : [[t('availabilityService'), t('availabilityServiceText')], [t('directReservations'), t('directReservationsText')], [t('acceptableUse'), t('acceptableUseText')]];

    useEffect(() => localStorage.setItem('public-theme', dark ? 'dark' : 'light'), [dark]);

    return <div className={`public-page ${dark ? 'public-dark' : ''}`}>
        <Head title={title} />
        <PublicNav locale={locale} dark={dark} setLocale={nextLocale => router.post('/locale', { locale: nextLocale }, { preserveScroll: true })} setDark={setDark} />
        <main className="public-legal-page"><Link href="/" className="legal-back"><ArrowLeft size={17} />{t('backToSearch')}</Link><header><span><ShieldCheck size={16} />PitchFlow</span><h1>{title}</h1><p>{t('legalUpdated')}</p></header><div>{sections.map(([heading, text]) => <section key={heading}><h2>{heading}</h2><p>{text}</p></section>)}</div></main>
    </div>;
}
