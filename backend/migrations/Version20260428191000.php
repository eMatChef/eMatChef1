<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260428191000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Backfill site_page.content.locales for blog/faq/tos/impressum';
    }

    public function up(Schema $schema): void
    {
        // Blog
        $this->addSql(<<<'SQL'
UPDATE site_page
SET
  content = jsonb_set(
    content::jsonb,
    '{locales}',
    jsonb_build_object(
      'de', jsonb_build_object(
        'title', COALESCE(content::jsonb->>'title', 'Blog'),
        'introHtml', COALESCE(content::jsonb->>'introHtml', '<p></p>'),
        'posts', COALESCE(content::jsonb->'posts', '[]'::jsonb)
      ),
      'en', jsonb_build_object(
        'title', 'Blog',
        'introHtml', '<p>News and tips about eMatChef appear here.</p>',
        'posts', '[]'::jsonb
      ),
      'fr', jsonb_build_object(
        'title', 'Blog',
        'introHtml', '<p>Vous trouverez ici des actualités et des conseils sur eMatChef.</p>',
        'posts', '[]'::jsonb
      )
    ),
    true
  )::json,
  updated_at = NOW()
WHERE slug = 'blog'
  AND NOT jsonb_exists(content::jsonb, 'locales');
SQL);

        // FAQ
        $this->addSql(<<<'SQL'
UPDATE site_page
SET
  content = jsonb_set(
    content::jsonb,
    '{locales}',
    jsonb_build_object(
      'de', jsonb_build_object(
        'title', COALESCE(content::jsonb->>'title', 'Häufige Fragen'),
        'items', COALESCE(content::jsonb->'items', '[]'::jsonb)
      ),
      'en', jsonb_build_object(
        'title', 'Frequently asked questions',
        'items', jsonb_build_array(
          jsonb_build_object(
            'q', 'What is eMatChef in one sentence?',
            'aHtml', '<p>eMatChef is your central place for inventory, storage, lending and activities.</p>'
          )
        )
      ),
      'fr', jsonb_build_object(
        'title', 'Questions fréquentes',
        'items', jsonb_build_array(
          jsonb_build_object(
            'q', 'Qu’est-ce que eMatChef en une phrase ?',
            'aHtml', '<p>eMatChef est votre espace central pour le matériel, le stockage, les prêts et les activités.</p>'
          )
        )
      )
    ),
    true
  )::json,
  updated_at = NOW()
WHERE slug = 'faq'
  AND NOT jsonb_exists(content::jsonb, 'locales');
SQL);

        // Terms / Privacy
        $this->addSql(<<<'SQL'
UPDATE site_page
SET
  content = jsonb_set(
    content::jsonb,
    '{locales}',
    jsonb_build_object(
      'de', jsonb_build_object(
        'title', COALESCE(content::jsonb->>'title', 'Nutzungsbedingungen & Datenschutz'),
        'sections', COALESCE(content::jsonb->'sections', '[]'::jsonb)
      ),
      'en', jsonb_build_object(
        'title', 'Terms of use & privacy',
        'sections', jsonb_build_array(
          jsonb_build_object(
            'id', 'usage',
            'heading', 'Usage',
            'bodyHtml', '<p>Please add your organization’s terms of use here.</p>'
          ),
          jsonb_build_object(
            'id', 'privacy',
            'heading', 'Privacy',
            'bodyHtml', '<p>Please add your privacy policy here (purpose, legal basis, retention, contact).</p>'
          )
        )
      ),
      'fr', jsonb_build_object(
        'title', 'Conditions d''utilisation et confidentialité',
        'sections', jsonb_build_array(
          jsonb_build_object(
            'id', 'utilisation',
            'heading', 'Utilisation',
            'bodyHtml', '<p>Veuillez ajouter ici les conditions d’utilisation de votre organisation.</p>'
          ),
          jsonb_build_object(
            'id', 'confidentialite',
            'heading', 'Confidentialité',
            'bodyHtml', '<p>Veuillez ajouter ici votre politique de confidentialité (finalité, base légale, durée de conservation, contact).</p>'
          )
        )
      )
    ),
    true
  )::json,
  updated_at = NOW()
WHERE slug = 'tos'
  AND NOT jsonb_exists(content::jsonb, 'locales');
SQL);

        // Imprint
        $this->addSql(<<<'SQL'
UPDATE site_page
SET
  content = jsonb_set(
    content::jsonb,
    '{locales}',
    jsonb_build_object(
      'de', jsonb_build_object(
        'title', COALESCE(content::jsonb->>'title', 'Impressum'),
        'sections', COALESCE(content::jsonb->'sections', '[]'::jsonb)
      ),
      'en', jsonb_build_object(
        'title', 'Imprint',
        'sections', jsonb_build_array(
          jsonb_build_object(
            'heading', 'Provider information',
            'bodyHtml', '<p><strong>[Company / Association]</strong><br />[Street no.]<br />[ZIP City]<br />[Country]</p><p><strong>Contact</strong><br />Phone: [optional]<br />Email: [Email]</p>'
          ),
          jsonb_build_object(
            'heading', 'Authorized representative',
            'bodyHtml', '<p>[Name of authorized representative / management]</p>'
          ),
          jsonb_build_object(
            'heading', 'Liability for content',
            'bodyHtml', '<p>The contents of these pages were created with due care. However, we cannot guarantee the accuracy, completeness, or timeliness of the content.</p>'
          )
        )
      ),
      'fr', jsonb_build_object(
        'title', 'Mentions légales',
        'sections', jsonb_build_array(
          jsonb_build_object(
            'heading', 'Informations de l''éditeur',
            'bodyHtml', '<p><strong>[Entreprise / Association]</strong><br />[Rue n°]<br />[NPA Ville]<br />[Pays]</p><p><strong>Contact</strong><br />Téléphone : [optionnel]<br />E-mail : [E-mail]</p>'
          ),
          jsonb_build_object(
            'heading', 'Représentant habilité',
            'bodyHtml', '<p>[Nom de la personne habilitée / direction]</p>'
          ),
          jsonb_build_object(
            'heading', 'Responsabilité du contenu',
            'bodyHtml', '<p>Le contenu de ces pages a été créé avec soin. Toutefois, nous ne garantissons pas l’exactitude, l’exhaustivité et l’actualité des contenus.</p>'
          )
        )
      )
    ),
    true
  )::json,
  updated_at = NOW()
WHERE slug = 'impressum'
  AND NOT jsonb_exists(content::jsonb, 'locales');
SQL);
    }

    public function down(Schema $schema): void
    {
        // Reverse only the backfill field; keep all legacy top-level fields intact.
        $this->addSql("UPDATE site_page SET content = (content::jsonb - 'locales')::json WHERE slug IN ('blog','faq','tos','impressum') AND jsonb_exists(content::jsonb, 'locales')");
    }
}

