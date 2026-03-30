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
        return [
            'title' => 'Blog',
            'introHtml' => '<p>Hier erscheinen Neuigkeiten und Tipps rund um eMatChef.</p>',
            'posts' => [],
        ];
    }

    /** @return PageContent */
    private function faq(): array
    {
        return [
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
    }

    /** @return PageContent */
    private function tos(): array
    {
        return [
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
    }

    /** @return PageContent */
    private function impressum(): array
    {
        return [
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
    }
}
