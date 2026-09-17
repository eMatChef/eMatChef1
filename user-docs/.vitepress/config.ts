import { defineConfig } from 'vitepress'

export default defineConfig({
  title: 'eMatChef Hilfe',
  description:
    'Öffentliche Hilfe zu eMatChef: Materialverwaltung für Vereine und Vermietungen.',
  cleanUrls: true,
  lastUpdated: true,
  srcExclude: ['README.md'],
  sitemap: {
    hostname: 'https://docs.ematchef.ch',
  },
  head: [['meta', { name: 'robots', content: 'index, follow' }]],
  themeConfig: {
    search: { provider: 'local' },
    socialLinks: [],
  },
  locales: {
    de: {
      label: 'Deutsch',
      lang: 'de-CH',
      link: '/de/',
      title: 'eMatChef Hilfe',
      description:
        'Öffentliche Hilfe zu eMatChef: Materialverwaltung für Vereine und Vermietungen.',
      themeConfig: {
        siteTitle: 'eMatChef Hilfe',
        nav: [
          { text: 'Hilfe', link: '/de/hilfe/aktivitaet-anlegen' },
          { text: 'App', link: 'https://app.ematchef.ch' },
          { text: 'Website', link: 'https://ematchef.ch' },
        ],
        sidebar: [
          {
            text: 'Erste Schritte',
            items: [
              { text: 'Was ist eMatChef?', link: '/de/' },
              { text: 'Touren und Hilfe', link: '/de/hilfe/tours-und-hilfe' },
            ],
          },
          {
            text: 'Anleitungen',
            items: [
              { text: 'Aktivität anlegen', link: '/de/hilfe/aktivitaet-anlegen' },
              { text: 'Externe Ausleihe', link: '/de/hilfe/externe-ausleihe' },
            ],
          },
        ],
        outline: { label: 'Auf dieser Seite' },
        docFooter: { prev: 'Zurück', next: 'Weiter' },
        returnToTopLabel: 'Nach oben',
        sidebarMenuLabel: 'Menü',
        darkModeSwitchLabel: 'Darstellung',
        footer: {
          message: 'Touren bleiben in der App. Diese Seiten sind die öffentliche Hilfe.',
        },
      },
    },
    en: {
      label: 'English',
      lang: 'en',
      link: '/en/',
      title: 'eMatChef Help',
      description: 'Public help for eMatChef: inventory for clubs and rental teams.',
      themeConfig: {
        siteTitle: 'eMatChef Help',
        nav: [
          { text: 'Help', link: '/en/help/create-activity' },
          { text: 'App', link: 'https://app.ematchef.ch' },
          { text: 'Website', link: 'https://ematchef.ch' },
        ],
        sidebar: [
          {
            text: 'Getting started',
            items: [
              { text: 'What is eMatChef?', link: '/en/' },
              { text: 'Tours and help', link: '/en/help/tours-and-help' },
            ],
          },
          {
            text: 'Guides',
            items: [
              { text: 'Create an activity', link: '/en/help/create-activity' },
              { text: 'External loan', link: '/en/help/external-loan' },
            ],
          },
        ],
        outline: { label: 'On this page' },
        footer: {
          message: 'In-app tours stay in the app. These pages are the public help.',
        },
      },
    },
    fr: {
      label: 'Français',
      lang: 'fr',
      link: '/fr/',
      title: 'Aide eMatChef',
      description:
        'Aide publique pour eMatChef : matériel pour associations et locations.',
      themeConfig: {
        siteTitle: 'Aide eMatChef',
        nav: [
          { text: 'Aide', link: '/fr/aide/creer-une-activite' },
          { text: 'App', link: 'https://app.ematchef.ch' },
          { text: 'Site', link: 'https://ematchef.ch' },
        ],
        sidebar: [
          {
            text: 'Premiers pas',
            items: [
              { text: 'Qu’est-ce qu’eMatChef ?', link: '/fr/' },
              { text: 'Visites et aide', link: '/fr/aide/visites-et-aide' },
            ],
          },
          {
            text: 'Guides',
            items: [
              { text: 'Créer une activité', link: '/fr/aide/creer-une-activite' },
              { text: 'Prêt externe', link: '/fr/aide/pret-externe' },
            ],
          },
        ],
        outline: { label: 'Sur cette page' },
        docFooter: { prev: 'Précédent', next: 'Suivant' },
        returnToTopLabel: 'Haut de page',
        sidebarMenuLabel: 'Menu',
        darkModeSwitchLabel: 'Apparence',
        footer: {
          message: 'Les visites guidées restent dans l’app. Ces pages sont l’aide publique.',
        },
      },
    },
    it: {
      label: 'Italiano',
      lang: 'it',
      link: '/it/',
      title: 'Guida eMatChef',
      description:
        'Guida pubblica a eMatChef: materiale per associazioni e noleggi.',
      themeConfig: {
        siteTitle: 'Guida eMatChef',
        nav: [
          { text: 'Guida', link: '/it/aiuto/crea-attivita' },
          { text: 'App', link: 'https://app.ematchef.ch' },
          { text: 'Sito', link: 'https://ematchef.ch' },
        ],
        sidebar: [
          {
            text: 'Per iniziare',
            items: [
              { text: 'Che cos’è eMatChef?', link: '/it/' },
              { text: 'Tour e guida', link: '/it/aiuto/tour-e-guida' },
            ],
          },
          {
            text: 'Istruzioni',
            items: [
              { text: 'Creare un’attività', link: '/it/aiuto/crea-attivita' },
              { text: 'Prestito esterno', link: '/it/aiuto/prestito-esterno' },
            ],
          },
        ],
        outline: { label: 'In questa pagina' },
        docFooter: { prev: 'Indietro', next: 'Avanti' },
        returnToTopLabel: 'Torna su',
        sidebarMenuLabel: 'Menu',
        darkModeSwitchLabel: 'Aspetto',
        footer: {
          message: 'I tour restano nell’app. Queste pagine sono la guida pubblica.',
        },
      },
    },
  },
})
