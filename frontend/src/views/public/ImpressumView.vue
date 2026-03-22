<template>
  <div class="public-layout">
    <header class="public-header public-header--minimal" role="banner">
      <RouterLink to="/" class="public-brand" title="eMatChef">
        <EmcLogoMark size="sm" />
        <span class="public-brand-text">eMatChef</span>
      </RouterLink>
    </header>

    <main class="public-page public-page--legal">
      <article class="public-card public-card--legal legal-article">
        <h1>Impressum</h1>
        <p class="legal-hint">
          Platzhalter – bitte durch die Angaben deines Unternehmens / Vereins ersetzen (Anbieter, Adresse,
          Kontakt, UID falls zutreffend).
        </p>

        <h2>Angaben gemäß § 5 TMG / Schweiz: Anbieterkennzeichnung</h2>
        <p>
          <strong>[Firma / Verein]</strong><br />
          [Strasse Nr.]<br />
          [PLZ Ort]<br />
          [Land]
        </p>
        <p>
          <strong>Kontakt</strong><br />
          Telefon: [optional]<br />
          E-Mail: [E-Mail]
        </p>

        <h2>Vertretungsberechtigt</h2>
        <p>[Name der vertretungsberechtigten Person / Geschäftsführung]</p>

        <h2>Haftung für Inhalte</h2>
        <p>
          Die Inhalte dieser Seiten wurden mit Sorgfalt erstellt. Für die Richtigkeit, Vollständigkeit und
          Aktualität der Inhalte können wir jedoch keine Gewähr übernehmen.
        </p>
      </article>
    </main>

    <PublicSiteFooter />
  </div>
</template>

<script setup lang="ts">
import EmcLogoMark from '../../components/brand/EmcLogoMark.vue'
import PublicSiteFooter from '../../components/public/PublicSiteFooter.vue'
</script>
