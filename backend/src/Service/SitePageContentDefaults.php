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
        return [
            'heroTitle' => 'Material im Griff, Team im Blick',
            'heroSubtitle' => 'eMatChef unterstützt dich bei Lager, Ausleihe und Übersicht – für Vermietungen und Teams, die mitdenken.',
            'primaryCta' => 'Login',
            'secondaryCta' => 'Fragen & Antworten',
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
