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
        <h1>Datenschutz</h1>
        <p class="legal-hint">
          Platzhalter – bitte durch eine vollständige Datenschutzerklärung ersetzen (Verantwortliche Stelle,
          Zwecke, Rechtsgrundlagen, Speicherdauer, Betroffenenrechte, Hosting, ggf. Drittlandtransfer).
        </p>

        <h2>Verantwortliche Stelle</h2>
        <p>
          <strong>[Firma / Verein]</strong><br />
          [Strasse Nr., PLZ Ort]<br />
          E-Mail: [E-Mail]
        </p>

        <h2>Erhebung und Verarbeitung personenbezogener Daten</h2>
        <p>
          Beim Besuch öffentlicher Seiten (z. B. Material-Informationen über einen QR-Code) können technisch
          bedingt Server-Logdaten verarbeitet werden. Weitere Daten werden nur erhoben, wenn du sie aktiv
          eingibst (z. B. Kontaktformular).
        </p>

        <h2>Deine Rechte</h2>
        <p>
          Du hast je nach anwendbarem Recht unter anderem Recht auf Auskunft, Berichtigung, Löschung,
          Einschränkung der Verarbeitung, Datenübertragbarkeit und Widerspruch. Wende dich dazu an die oben
          genannte verantwortliche Stelle.
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
