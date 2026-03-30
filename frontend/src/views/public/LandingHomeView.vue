<template>
  <div class="plt-page">
    <section class="plt-hero" aria-labelledby="landing-title">
      <div class="plt-hero__inner">
        <p class="plt-kicker">Materialverwaltung</p>
        <h1 id="landing-title">{{ heroTitle }}</h1>
        <p class="plt-lead">{{ heroSubtitle }}</p>
        <div class="plt-hero__actions">
          <AppLoginLink class="btn btn-primary plt-btn-lg">{{ primaryCta }}</AppLoginLink>
          <RouterLink to="/faq" class="btn btn-outline plt-btn-lg">{{ secondaryCta }}</RouterLink>
        </div>
      </div>
    </section>

    <section class="plt-section plt-section--alt" aria-labelledby="section-intro">
      <div class="plt-container">
        <h2 id="section-intro">Einfach digital organisieren</h2>
        <div class="plt-prose">
          <p>
            Suchst du eine klare, digitale Lösung für Material, Lager und Ausleihen? eMatChef richtet sich an
            Teams und Vermietungen: Du behältst Übersicht über Bestände, Orte und Bewegungen – ohne
            Tabellenchaos und ohne Zettelwirtschaft.
          </p>
          <p>
            Die Anwendung läuft im Browser, ist rollenbasiert aufgebaut und lässt sich pro Organisation
            anpassen. Öffentliche QR-Infos helfen dabei, Material schnell wiederzufinden.
          </p>
        </div>
      </div>
    </section>

    <section class="plt-section" aria-labelledby="section-features">
      <div class="plt-container">
        <h2 id="section-features">Was eMatChef kann</h2>
        <div class="plt-features">
          <article v-for="(f, i) in features" :key="i" class="plt-feature-card">
            <div class="plt-feature-card__icon" aria-hidden="true">{{ f.icon }}</div>
            <h3>{{ f.title }}</h3>
            <p>{{ f.text }}</p>
          </article>
        </div>
      </div>
    </section>

    <section class="plt-cta" aria-labelledby="section-cta">
      <div class="plt-container">
        <h2 id="section-cta" class="sr-only">Loslegen</h2>
        <p>Bereit? Melde dich an und arbeite mit deiner Abteilung in einer gemeinsamen Oberfläche.</p>
        <AppLoginLink class="btn btn-primary plt-btn-lg">{{ primaryCta }}</AppLoginLink>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useSiteContentStore } from '@/stores/siteContent'
import AppLoginLink from '@/components/public/AppLoginLink.vue'

const site = useSiteContentStore()

onMounted(() => {
  void site.ensureLoaded()
})

const heroTitle = computed(() =>
  String(site.getContent('landing').heroTitle ?? 'Material im Griff, Team im Blick')
)
const heroSubtitle = computed(() =>
  String(
    site.getContent('landing').heroSubtitle ??
      'eMatChef unterstützt dich bei Lager, Ausleihe und Übersicht – für Vermietungen und Teams, die mitdenken.'
  )
)
const primaryCta = computed(() => String(site.getContent('landing').primaryCta ?? 'Login'))
const secondaryCta = computed(() => String(site.getContent('landing').secondaryCta ?? 'Fragen & Antworten'))

const features = [
  { icon: '⊙', title: 'Alles an einem Ort', text: 'Material, Lagerorte, Mengen und Bewegungen – strukturiert und nachvollziehbar.' },
  { icon: '⌗', title: 'QR & öffentliche Infos', text: 'Seriennummern und Hinweise für alle sichtbar, wo es sinnvoll ist.' },
  { icon: '◎', title: 'Rollen & Abteilungen', text: 'Wer darf was sehen und bearbeiten? Rechte pro Team und Organisation.' },
  { icon: '⇄', title: 'Ausleihe & Bestand', text: 'Buchungen und Verschiebungen dokumentieren, ohne Doppelungen zu riskieren.' },
  { icon: '◇', title: 'Vorlagen & Einstellungen', text: 'Organisationsspezifische Vorlagen und Konfiguration – nicht „One size fits all“.' },
  { icon: '○', title: 'Im Browser', text: 'Keine Installation nötig: einfach anmelden und loslegen.' },
] as const
</script>

<style scoped>
.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}
</style>
