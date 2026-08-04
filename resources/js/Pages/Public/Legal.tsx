import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Mail, ShieldCheck } from 'lucide-react';
import { useEffect, useState } from 'react';
import { PublicNav } from './Availability';
import { setClientLocale, useLocale, useTranslation } from '../../lib/i18n';

export default function Legal({ document }: { document: 'privacy' | 'terms' }) {
    const t = useTranslation();
    const locale = useLocale();
    const [dark, setDark] = useState(() => localStorage.getItem('public-theme') === 'dark');
    const title = document === 'privacy' ? t('privacyPolicy') : t('terms');
    const content = legalContent[locale][document];

    useEffect(() => localStorage.setItem('public-theme', dark ? 'dark' : 'light'), [dark]);
    const switchLocale = (nextLocale: 'en' | 'sq') => {
        setClientLocale(nextLocale);
        router.post('/locale', { locale: nextLocale }, { preserveScroll: true, preserveState: false, replace: true });
    };

    return <div className={`public-page ${dark ? 'public-dark' : ''}`}>
        <Head title={title} />
        <PublicNav locale={locale} dark={dark} setLocale={switchLocale} setDark={setDark} />
        <main className="public-legal-page">
            <Link href="/" className="legal-back"><ArrowLeft size={17} />{t('backToHome')}</Link>
            <header>
                <span><ShieldCheck size={16} />PitchFlow</span>
                <h1>{title}</h1>
                <p>{content.intro}</p>
                <small>{t('legalUpdated')}</small>
            </header>
            <div className="legal-section-grid">
                {content.sections.map(section => <section key={section.heading}>
                    <h2>{section.heading}</h2>
                    <p>{section.body}</p>
                    {section.items && <ul>{section.items.map(item => <li key={item}>{item}</li>)}</ul>}
                </section>)}
            </div>
            <section className="legal-contact-card">
                <div><Mail size={20} /><h2>{content.contactHeading}</h2></div>
                <p>{content.contactText}</p>
                <a href="mailto:pitchflowks@hotmail.com">pitchflowks@hotmail.com</a>
            </section>
        </main>
    </div>;
}

const legalContent = {
    en: {
        privacy: {
            intro: 'A clear summary of how PitchFlow handles data while helping visitors check availability and businesses manage reservations internally.',
            contactHeading: 'Contact',
            contactText: 'Questions about privacy or data handling can be sent to:',
            sections: [
                {
                    heading: 'Introduction',
                    body: 'PitchFlow helps visitors check football field availability and helps football field businesses manage field information, staff access, customers, and reservations internally.',
                },
                {
                    heading: 'Data we collect',
                    body: 'We collect the information needed to run the platform and keep availability useful.',
                    items: ['Business account data', 'Owner and employee account data', 'Football field information', 'Reservation and customer details entered by the business', 'Analytics events such as page views, availability searches, and call clicks'],
                },
                {
                    heading: 'How we use data',
                    body: 'We use data to show public field availability, operate business dashboards, support internal reservations, improve the platform, and provide support to businesses.',
                },
                {
                    heading: 'Public information',
                    body: 'Public visitors may see business name, city or location, public phone number, field availability, opening hours, and price when provided by the business.',
                },
                {
                    heading: 'Private information',
                    body: 'Customer names, phone numbers, reservation notes, internal ratings, and staff data are private. They are visible only to authorized business users and Super Admin where necessary for support, safety, or platform operations.',
                },
                {
                    heading: 'Cookies and analytics',
                    body: 'PitchFlow may use basic analytics to understand visits, searches, call clicks, and platform usage. Analytics are used for product improvement and do not require customer accounts. Raw IP addresses are not stored in public analytics; technical identifiers may be hashed or anonymized.',
                },
                {
                    heading: 'Data security',
                    body: 'PitchFlow uses account-based access, role permissions, and organization isolation so owners and employees can access only the areas and data they are allowed to use.',
                },
            ],
        },
        terms: {
            intro: 'Simple terms for using PitchFlow as a visitor, football field owner, employee, or platform administrator.',
            contactHeading: 'Contact',
            contactText: 'Questions about these terms can be sent to:',
            sections: [
                {
                    heading: 'What PitchFlow is',
                    body: 'PitchFlow is a platform for checking football field availability and helping businesses manage internal reservations, staff access, fields, customers, and operating information.',
                },
                {
                    heading: 'No direct booking or payment',
                    body: 'PitchFlow does not currently process public bookings, payments, or reservation confirmations. Visitors must contact the football field directly to reserve. Reservations are made directly with the football field.',
                },
                {
                    heading: 'Business responsibility',
                    body: 'Football field businesses are responsible for keeping their information accurate.',
                    items: ['Keeping availability accurate', 'Updating working hours', 'Managing reservations', 'Providing correct phone and contact details', 'Handling customer communication'],
                },
                {
                    heading: 'Visitor responsibility',
                    body: 'Visitors should confirm reservations directly with the football field before going there. Availability shown on PitchFlow is helpful information, not an automatic reservation.',
                },
                {
                    heading: 'User accounts',
                    body: 'Business owners and employees are responsible for keeping login credentials safe and using only the access permissions assigned to them.',
                },
                {
                    heading: 'Acceptable use',
                    body: 'Users must not misuse PitchFlow or the data inside it.',
                    items: ['Create fake businesses', 'Misuse customer data', 'Post false field information', 'Attempt unauthorized access'],
                },
                {
                    heading: 'Platform availability',
                    body: 'PitchFlow may be updated, changed, or temporarily unavailable during maintenance. We aim to keep the platform reliable, but availability is not guaranteed at all times.',
                },
            ],
        },
    },
    sq: {
        privacy: {
            intro: 'Përmbledhje e qartë se si PitchFlow i trajton të dhënat ndërsa ndihmon vizitorët të kontrollojnë disponueshmërinë dhe bizneset të menaxhojnë rezervimet internisht.',
            contactHeading: 'Kontakti',
            contactText: 'Pyetjet rreth privatësisë ose trajtimit të të dhënave mund të dërgohen në:',
            sections: [
                {
                    heading: 'Hyrje',
                    body: 'PitchFlow ndihmon vizitorët të kontrollojnë disponueshmërinë e fushave të futbollit dhe ndihmon bizneset të menaxhojnë informacionin e fushave, qasjen e stafit, klientët dhe rezervimet internisht.',
                },
                {
                    heading: 'Të dhënat që mbledhim',
                    body: 'Ne mbledhim informacionin e nevojshëm për funksionimin e platformës dhe për ta mbajtur disponueshmërinë të dobishme.',
                    items: ['Të dhënat e llogarisë së biznesit', 'Të dhënat e llogarive të pronarit dhe punonjësve', 'Informacionet për fushat e futbollit', 'Detajet e rezervimeve dhe klientëve që futen nga biznesi', 'Ngjarje analitike si shikime faqesh, kërkime disponueshmërie dhe klikime telefonate'],
                },
                {
                    heading: 'Si i përdorim të dhënat',
                    body: 'Ne i përdorim të dhënat për të shfaqur disponueshmërinë publike të fushave, për të menaxhuar panelet e bizneseve, për të mbështetur rezervimet interne, për të përmirësuar platformën dhe për të ofruar ndihmë.',
                },
                {
                    heading: 'Informacion publik',
                    body: 'Vizitorët publikë mund të shohin emrin e biznesit, qytetin ose lokacionin, numrin publik të telefonit, disponueshmërinë e fushës, orarin e punës dhe çmimin nëse është dhënë nga biznesi.',
                },
                {
                    heading: 'Informacion privat',
                    body: 'Emrat e klientëve, numrat e telefonit, shënimet e rezervimeve, vlerësimet interne dhe të dhënat e stafit janë private. Ato shihen vetëm nga përdoruesit e autorizuar të biznesit dhe nga Super Admin kur është e nevojshme për ndihmë, siguri ose funksionim të platformës.',
                },
                {
                    heading: 'Cookies dhe analitika',
                    body: 'PitchFlow mund të përdorë analitikë bazike për të kuptuar vizitat, kërkimet, klikimet e telefonatave dhe përdorimin e platformës. Analitika përdoret për përmirësim të produktit dhe nuk kërkon llogari klientësh. Adresat IP të papërpunuara nuk ruhen në analitikën publike; identifikuesit teknikë mund të hash-ohen ose anonimizohen.',
                },
                {
                    heading: 'Siguria e të dhënave',
                    body: 'PitchFlow përdor qasje me llogari, role dhe izolim sipas organizatës në mënyrë që pronarët dhe punonjësit të kenë qasje vetëm në zonat dhe të dhënat që u lejohen.',
                },
            ],
        },
        terms: {
            intro: 'Kushte të thjeshta për përdorimin e PitchFlow nga vizitorët, pronarët e fushave, punonjësit dhe administratorët e platformës.',
            contactHeading: 'Kontakti',
            contactText: 'Pyetjet rreth këtyre kushteve mund të dërgohen në:',
            sections: [
                {
                    heading: 'Çfarë është PitchFlow',
                    body: 'PitchFlow është platformë për kontrollimin e disponueshmërisë së fushave të futbollit dhe për t’i ndihmuar bizneset të menaxhojnë rezervimet interne, qasjen e stafit, fushat, klientët dhe informacionet operative.',
                },
                {
                    heading: 'Pa rezervime ose pagesa direkte',
                    body: 'PitchFlow nuk përpunon rezervime publike, pagesa ose konfirmime rezervimesh për momentin. Vizitorët duhet ta kontaktojnë fushën e futbollit drejtpërdrejt për të rezervuar. Rezervimet bëhen drejtpërdrejt me fushën e futbollit.',
                },
                {
                    heading: 'Përgjegjësia e biznesit',
                    body: 'Bizneset e fushave të futbollit janë përgjegjëse për saktësinë e informacionit të tyre.',
                    items: ['Mbajtjen e disponueshmërisë së saktë', 'Përditësimin e orarit të punës', 'Menaxhimin e rezervimeve', 'Dhënien e numrit dhe kontaktit të saktë', 'Komunikimin me klientët'],
                },
                {
                    heading: 'Përgjegjësia e vizitorit',
                    body: 'Vizitorët duhet ta konfirmojnë rezervimin drejtpërdrejt me fushën e futbollit para se të shkojnë atje. Disponueshmëria që shfaqet në PitchFlow është informacion ndihmues, jo rezervim automatik.',
                },
                {
                    heading: 'Llogaritë e përdoruesve',
                    body: 'Pronarët dhe punonjësit janë përgjegjës për ruajtjen e kredencialeve të hyrjes dhe për përdorimin vetëm të lejeve që u janë caktuar.',
                },
                {
                    heading: 'Përdorim i pranueshëm',
                    body: 'Përdoruesit nuk duhet ta keqpërdorin PitchFlow ose të dhënat brenda tij.',
                    items: ['Të krijojnë biznese të rreme', 'Të keqpërdorin të dhënat e klientëve', 'Të publikojnë informacione të rreme për fusha', 'Të tentojnë qasje të paautorizuar'],
                },
                {
                    heading: 'Disponueshmëria e platformës',
                    body: 'PitchFlow mund të përditësohet, ndryshohet ose të jetë përkohësisht i padisponueshëm gjatë mirëmbajtjes. Synojmë ta mbajmë platformën të qëndrueshme, por disponueshmëria nuk garantohet në çdo moment.',
                },
            ],
        },
    },
};
