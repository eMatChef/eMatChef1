<?php

namespace App\Service;

/**
 * Standard-Inhalte für öffentliche Seiten (wenn noch kein DB-Eintrag existiert).
 *
 * @phpstan-type PageContent = array<string, mixed>
 */
class SitePageContentDefaults
{
    /** @return array<string, PageContent> */
    public function allDefaults(): array
    {
        return [
            'landing' => $this->landing(),
            'blog' => $this->blog(),
            'faq' => $this->faq(),
            'tos' => $this->tos(),
            'impressum' => $this->impressum(),
        ];
    }

    /** @return PageContent */
    public function forSlug(string $slug): array
    {
        return $this->allDefaults()[$slug] ?? [];
    }

    /** @return string[] */
    public function allowedSlugs(): array
    {
        return array_keys($this->allDefaults());
    }

    /** @return PageContent */
    private function landing(): array
    {
        $de = $this->landingLocaleDe();

        return [
            ...$de,
            'locales' => [
                'de' => $de,
                'en' => $this->landingLocaleEn(),
                'fr' => $this->landingLocaleFr(),
            ],
        ];
    }

    /** @return PageContent */
    private function landingLocaleDe(): array
    {
        return [
            'kicker' => 'Erste Version online',
            'heroTitle' => 'Material im Griff, Team im Blick',
            'heroSubtitle' => 'Nach über fünf Jahren Entwicklung ist eMatChef live: Du als Materialwart behältst den Überblick — deine Mitleiter planen Lager und Samstage und buchen Material direkt.',
            'primaryCta' => 'Jetzt anmelden',
            'secondaryCta' => 'Häufige Fragen',
            'secondaryCtaPath' => '/faq',
            'introTitle' => 'Für Materialwart und Team',
            'introParagraph1' => 'eMatChef ist die gemeinsame Stelle für Material und Anlässe in deiner Abteilung. Als Materialwart pflegst du Bestände, Lagerorte und Buchungen — und siehst jederzeit, was für welches Lager, welchen Samstag oder welches Event reserviert ist. Deine Mitleiter legen Aktivitäten an, tragen Material ein und reichen ein. Du prüfst, packst und behältst den Überblick bis zur Retour.',
            'introParagraph2' => 'Alles läuft im Browser, ohne Installation. Rollen legen fest, wer was sehen und bearbeiten darf. QR-Codes am Material helfen beim Wiederfinden — wo eure Abteilung es einrichtet. Das ist Version 1: bewusst ein Start, der im Alltag funktionieren soll — mit eurem Feedback für die nächsten Schritte.',
            'featuresTitle' => 'So arbeitet ihr mit eMatChef',
            'features' => [
                'title' => 'So arbeitet ihr mit eMatChef',
                'items' => [
                    ['icon' => 'mdi-clipboard-check-outline', 'title' => 'Materialwart im Überblick', 'text' => 'Alle Aktivitäten und Buchungen der Abteilung auf einen Blick — packen, ausgeben, Retour: du weisst, was gerade wo ist.'],
                    ['icon' => 'mdi-account-group-outline', 'title' => 'Mitleiter planen selbst', 'text' => 'Lager, Samstage und Events anlegen, Material buchen und einreichen — ohne Listen-Chaos per Chat oder Zettel.'],
                    ['icon' => 'mdi-book-sync-outline', 'title' => 'Aktivitäten & Buchungen', 'text' => 'Vom Entwurf über Einreichen und Packen bis zur Retour: der Bestand bleibt mit dem echten Einsatz verbunden.'],
                    ['icon' => 'mdi-warehouse', 'title' => 'Alles an einem Ort', 'text' => 'Material, Mengen, Lagerorte und Bewegungen — strukturiert statt in verstreuten Tabellen.'],
                    ['icon' => 'mdi-qrcode-scan', 'title' => 'QR am Material', 'text' => 'Scan am Regal oder unterwegs: Infos, Seriennummer — und optional Kontakt zum Materialwart.'],
                    ['icon' => 'mdi-laptop', 'title' => 'Im Browser', 'text' => 'Keine App installieren: anmelden und mit deiner Abteilung in einer gemeinsamen Oberfläche arbeiten.'],
                ],
            ],
            'intro' => [
                'title' => 'Für Materialwart und Team',
                'paragraph1' => 'eMatChef ist die gemeinsame Stelle für Material und Anlässe in deiner Abteilung. Als Materialwart pflegst du Bestände, Lagerorte und Buchungen — und siehst jederzeit, was für welches Lager, welchen Samstag oder welches Event reserviert ist. Deine Mitleiter legen Aktivitäten an, tragen Material ein und reichen ein. Du prüfst, packst und behältst den Überblick bis zur Retour.',
                'paragraph2' => 'Alles läuft im Browser, ohne Installation. Rollen legen fest, wer was sehen und bearbeiten darf. QR-Codes am Material helfen beim Wiederfinden — wo eure Abteilung es einrichtet. Das ist Version 1: bewusst ein Start, der im Alltag funktionieren soll — mit eurem Feedback für die nächsten Schritte.',
            ],
            'ctaTitleSrOnly' => 'Loslegen',
            'ctaText' => 'Bereit zum Ausprobieren? Melde dich an — oder lies zuerst die häufigen Fragen. Wir freuen uns über dein Feedback zur ersten Version.',
            'cta' => [
                'titleSrOnly' => 'Loslegen',
                'text' => 'Bereit zum Ausprobieren? Melde dich an — oder lies zuerst die häufigen Fragen. Wir freuen uns über dein Feedback zur ersten Version.',
            ],
        ];
    }

    /** @return PageContent */
    private function landingLocaleEn(): array
    {
        return [
            'kicker' => 'Inventory management',
            'heroTitle' => 'Materials under control, team in view',
            'heroSubtitle' => 'eMatChef helps with storage, lending and overview – for rentals and teams that plan ahead.',
            'primaryCta' => 'Log in',
            'secondaryCta' => 'FAQ',
            'secondaryCtaPath' => '/faq',
            'introTitle' => 'Organise digitally with ease',
            'introParagraph1' => 'Looking for a clear digital solution for materials, storage and lending?',
            'introParagraph2' => 'Runs in the browser with role-based access per organisation.',
            'featuresTitle' => 'What eMatChef offers',
            'features' => [
                'title' => 'What eMatChef offers',
                'items' => [
                    ['icon' => 'mdi-warehouse', 'title' => 'All in one place', 'text' => 'Materials, locations, quantities and movements.'],
                ],
            ],
            'intro' => [
                'title' => 'Organise digitally with ease',
                'paragraph1' => 'Looking for a clear digital solution for materials, storage and lending?',
                'paragraph2' => 'Runs in the browser with role-based access per organisation.',
            ],
            'ctaTitleSrOnly' => 'Get started',
            'ctaText' => 'Ready? Log in and work with your department.',
            'cta' => [
                'titleSrOnly' => 'Get started',
                'text' => 'Ready? Log in and work with your department.',
            ],
        ];
    }

    /** @return PageContent */
    private function landingLocaleFr(): array
    {
        return [
            'kicker' => 'Gestion du matériel',
            'heroTitle' => 'Matériel sous contrôle, équipe en vue',
            'heroSubtitle' => 'eMatChef aide pour le stockage, les prêts et la vue d’ensemble.',
            'primaryCta' => 'Connexion',
            'secondaryCta' => 'FAQ',
            'secondaryCtaPath' => '/faq',
            'introTitle' => 'Organiser simplement en numérique',
            'introParagraph1' => 'Vous cherchez une solution claire pour le matériel et les prêts ?',
            'introParagraph2' => 'Fonctionne dans le navigateur avec des droits par organisation.',
            'featuresTitle' => 'Ce que eMatChef propose',
            'features' => [
                'title' => 'Ce que eMatChef propose',
                'items' => [
                    ['icon' => 'mdi-warehouse', 'title' => 'Tout au même endroit', 'text' => 'Matériel, emplacements et mouvements.'],
                ],
            ],
            'intro' => [
                'title' => 'Organiser simplement en numérique',
                'paragraph1' => 'Vous cherchez une solution claire pour le matériel et les prêts ?',
                'paragraph2' => 'Fonctionne dans le navigateur avec des droits par organisation.',
            ],
            'ctaTitleSrOnly' => 'Commencer',
            'ctaText' => 'Prêt ? Connectez-vous et travaillez avec votre département.',
            'cta' => [
                'titleSrOnly' => 'Commencer',
                'text' => 'Prêt ? Connectez-vous et travaillez avec votre département.',
            ],
        ];
    }

    /** @return PageContent */
    private function blog(): array
    {
        $de = [
            'title' => 'Blog',
            'introHtml' => '<p>Hier erscheinen Neuigkeiten und Tipps rund um eMatChef.</p>',
            'posts' => [],
        ];
        $en = [
            'title' => 'Blog',
            'introHtml' => '<p>News and tips about eMatChef appear here.</p>',
            'posts' => [],
        ];
        $fr = [
            'title' => 'Blog',
            'introHtml' => '<p>Vous trouverez ici des actualités et des conseils sur eMatChef.</p>',
            'posts' => [],
        ];

        return [
            // Legacy fallback shape kept at top-level (DE), plus explicit per-locale content.
            ...$de,
            'locales' => [
                'de' => $de,
                'en' => $en,
                'fr' => $fr,
            ],
        ];
    }

    /** @return PageContent */
    private function faq(): array
    {
        $de = [
            'title' => 'Häufige Fragen',
            'items' => [
                [
                    'q' => 'Was ist eMatChef in einem Satz?',
                    'aHtml' => '<p>eMatChef ist deine zentrale Stelle für <strong>Material, Lager, Ausleihen und Aktivitäten</strong> (z. B. Events oder Einsätze): Du siehst, was wo liegt, was wohin gehört, und behältst den Überblick – ohne Tabellenchaos und ohne Zettelwirtschaft.</p>',
                ],
                [
                    'q' => 'Für wen lohnt sich das?',
                    'aHtml' => '<p>Für Teams und Organisationen, die Material teilen oder vermieten: Abteilungen, Lagerverantwortliche, Ehrenamt – überall, wo <strong>Bestand, Bewegungen und Verantwortung</strong> klar sein sollen – auch wenn es um konkrete Termine und Einsätze geht.</p>',
                ],
                [
                    'q' => 'Was kann ich konkret damit machen?',
                    'aHtml' => '<p>Unter anderem: Material und Mengen erfassen, Lagerorte zuordnen, Buchungen und Verschiebungen nachvollziehen, <strong>QR-Codes</strong> nutzen und – wo vorgesehen – <strong>öffentliche Infos</strong> bereitstellen (z. B. für Fundstücke oder Serienhinweise). Du kannst <strong>Aktivitäten</strong> anlegen (z. B. für ein Event), Material dazu <strong>zuordnen</strong> und beim Start <strong>ausbuchen</strong> sowie bei der Rückgabe wieder <strong>einbuchen</strong> – alles nachvollziehbar im System. Alles läuft <strong>rollenbasiert</strong>: Du siehst nur, was für deine Rolle vorgesehen ist.</p>',
                ],
                [
                    'q' => 'Wie hängen Aktivitäten, Events und Material zusammen?',
                    'aHtml' => '<p>In eMatChef planst du <strong>Aktivitäten</strong> – das können Events, Auftritte, Lager oder andere Einsätze sein. Dazu ordnest du <strong>konkretes Material</strong> zu (was wird mitgenommen, in welcher Menge). Beim Herausgeben <strong>buchst du aus</strong>, bei der Rückgabe <strong>buchst du wieder ein</strong> – so bleibt der Bestand mit dem realen Einsatz verknüpft und du weißt jederzeit, was gerade wo ist.</p>',
                ],
                [
                    'q' => 'Muss ich etwas installieren?',
                    'aHtml' => '<p>Nein. eMatChef läuft im <strong>Browser</strong> – einfach anmelden und loslegen.</p>',
                ],
                [
                    'q' => 'Wo melde ich mich an?',
                    'aHtml' => '<p>Oben über <strong>Login</strong> gelangst du zur Anmeldung bei deiner eMatChef-Instanz. Zugangsdaten und Einladungen kommen von deiner Organisation.</p>',
                ],
                [
                    'q' => 'Was hat es mit den QR-Codes auf sich?',
                    'aHtml' => '<p>QR verbindet physisches Material mit der digitalen Übersicht: Gescannt landet man auf den dafür vorgesehenen <strong>öffentlichen oder geschützten Seiten</strong> – je nachdem, wie eure Organisation es einrichtet. Praktisch, wenn etwas unterwegs ist oder gefunden wird.</p>',
                ],
            ],
        ];
        $en = [
            'title' => 'Frequently asked questions',
            'items' => [
                [
                    'q' => 'What is eMatChef in one sentence?',
                    'aHtml' => '<p>eMatChef is your central place for inventory, storage, lending and activities.</p>',
                ],
            ],
        ];
        $fr = [
            'title' => 'Questions fréquentes',
            'items' => [
                [
                    'q' => 'Qu’est-ce que eMatChef en une phrase ?',
                    'aHtml' => '<p>eMatChef est votre espace central pour le matériel, le stockage, les prêts et les activités.</p>',
                ],
            ],
        ];

        return [
            ...$de,
            'locales' => [
                'de' => $de,
                'en' => $en,
                'fr' => $fr,
            ],
        ];
    }

    /** @return PageContent */
    private function tos(): array
    {
        $de = [
            'title' => 'Nutzungsbedingungen & Datenschutz',
            'sections' => [
                [
                    'id' => 'nutzung',
                    'heading' => 'Nutzung',
                    'bodyHtml' => '<p>Bitte ergänzen Sie hier die Nutzungsbedingungen Ihrer Organisation.</p>',
                ],
                [
                    'id' => 'datenschutz',
                    'heading' => 'Datenschutz',
                    'bodyHtml' => '<p>Bitte ergänzen Sie hier Ihre Datenschutzhinweise (Zweck, Rechtsgrundlagen, Speicherdauer, Kontakt).</p>',
                ],
            ],
        ];
        $en = [
            'title' => 'Terms of use & privacy',
            'sections' => [
                [
                    'id' => 'usage',
                    'heading' => 'Usage',
                    'bodyHtml' => '<p>Please add your organization’s terms of use here.</p>',
                ],
                [
                    'id' => 'privacy',
                    'heading' => 'Privacy',
                    'bodyHtml' => '<p>Please add your privacy policy here (purpose, legal basis, retention, contact).</p>',
                ],
            ],
        ];
        $fr = [
            'title' => "Conditions d'utilisation et confidentialité",
            'sections' => [
                [
                    'id' => 'utilisation',
                    'heading' => 'Utilisation',
                    'bodyHtml' => '<p>Veuillez ajouter ici les conditions d’utilisation de votre organisation.</p>',
                ],
                [
                    'id' => 'confidentialite',
                    'heading' => 'Confidentialité',
                    'bodyHtml' => '<p>Veuillez ajouter ici votre politique de confidentialité (finalité, base légale, durée de conservation, contact).</p>',
                ],
            ],
        ];

        return [
            ...$de,
            'locales' => [
                'de' => $de,
                'en' => $en,
                'fr' => $fr,
            ],
        ];
    }

    /** @return PageContent */
    private function impressum(): array
    {
        $de = [
            'title' => 'Impressum',
            'sections' => [
                [
                    'heading' => 'Angaben gemäß TMG / Schweiz: Anbieterkennzeichnung',
                    'bodyHtml' => '<p><strong>[Firma / Verein]</strong><br />[Strasse Nr.]<br />[PLZ Ort]<br />[Land]</p><p><strong>Kontakt</strong><br />Telefon: [optional]<br />E-Mail: [E-Mail]</p>',
                ],
                [
                    'heading' => 'Vertretungsberechtigt',
                    'bodyHtml' => '<p>[Name der vertretungsberechtigten Person / Geschäftsführung]</p>',
                ],
                [
                    'heading' => 'Haftung für Inhalte',
                    'bodyHtml' => '<p>Die Inhalte dieser Seiten wurden mit Sorgfalt erstellt. Für die Richtigkeit, Vollständigkeit und Aktualität der Inhalte können wir jedoch keine Gewähr übernehmen.</p>',
                ],
            ],
        ];
        $en = [
            'title' => 'Imprint',
            'sections' => [
                [
                    'heading' => 'Provider information',
                    'bodyHtml' => '<p><strong>[Company / Association]</strong><br />[Street no.]<br />[ZIP City]<br />[Country]</p><p><strong>Contact</strong><br />Phone: [optional]<br />Email: [Email]</p>',
                ],
                [
                    'heading' => 'Authorized representative',
                    'bodyHtml' => '<p>[Name of authorized representative / management]</p>',
                ],
                [
                    'heading' => 'Liability for content',
                    'bodyHtml' => '<p>The contents of these pages were created with due care. However, we cannot guarantee the accuracy, completeness, or timeliness of the content.</p>',
                ],
            ],
        ];
        $fr = [
            'title' => 'Mentions légales',
            'sections' => [
                [
                    'heading' => "Informations de l'éditeur",
                    'bodyHtml' => '<p><strong>[Entreprise / Association]</strong><br />[Rue n°]<br />[NPA Ville]<br />[Pays]</p><p><strong>Contact</strong><br />Téléphone : [optionnel]<br />E-mail : [E-mail]</p>',
                ],
                [
                    'heading' => 'Représentant habilité',
                    'bodyHtml' => '<p>[Nom de la personne habilitée / direction]</p>',
                ],
                [
                    'heading' => 'Responsabilité du contenu',
                    'bodyHtml' => '<p>Le contenu de ces pages a été créé avec soin. Toutefois, nous ne garantissons pas l’exactitude, l’exhaustivité et l’actualité des contenus.</p>',
                ],
            ],
        ];

        return [
            ...$de,
            'locales' => [
                'de' => $de,
                'en' => $en,
                'fr' => $fr,
            ],
        ];
    }
}
