import { defineConfig } from 'vitepress'

const deSidebar = [
  {
    text: 'Erste Schritte',
    items: [
      { text: 'Was ist eMatChef?', link: '/' },
      { text: 'Touren und Hilfe', link: '/hilfe/tours-und-hilfe' },
    ],
  },
  {
    text: 'Anleitungen',
    items: [
      { text: 'Aktivität anlegen', link: '/hilfe/aktivitaet-anlegen' },
      { text: 'Externe Ausleihe', link: '/hilfe/externe-ausleihe' },
    ],
  },
]

const enSidebar = [
  {
    text: 'Getting started',
    items: [
      { text: 'What is eMatChef?', link: '/' },
      { text: 'Tours and help', link: '/help/tours-and-help' },
    ],
  },
  {
    text: 'Guides',
    items: [
      { text: 'Create an activity', link: '/help/create-activity' },
      { text: 'External loan', link: '/help/external-loan' },
    ],
  },
]

const frSidebar = [
  {
    text: 'Premiers pas',
    items: [
      { text: 'Qu’est-ce qu’eMatChef ?', link: '/' },
      { text: 'Visites et aide', link: '/aide/visites-et-aide' },
    ],
  },
  {
    text: 'Guides',
    items: [
      { text: 'Créer une activité', link: '/aide/creer-une-activite' },
      { text: 'Prêt externe', link: '/aide/pret-externe' },
    ],
  },
]

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
    root: {
      label: 'Start',
      lang: 'de-CH',
      title: 'eMatChef Hilfe',
      themeConfig: {
        siteTitle: 'eMatChef Hilfe',
        nav: [
          { text: 'Deutsch', link: '/de/' },
          { text: 'English', link: '/en/' },
          { text: 'Français', link: '/fr/' },
        ],
        sidebar: false,
      },
    },
    de: {
      label: 'Deutsch',
      lang: 'de-CH',
      title: 'eMatChef Hilfe',
      description:
        'Öffentliche Hilfe zu eMatChef: Materialverwaltung für Vereine und Vermietungen.',
      themeConfig: {
        siteTitle: 'eMatChef Hilfe',
        nav: [
          { text: 'Hilfe', link: '/hilfe/aktivitaet-anlegen' },
          { text: 'App', link: 'https://app.ematchef.ch' },
          { text: 'Website', link: 'https://ematchef.ch' },
        ],
        sidebar: deSidebar,
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
      title: 'eMatChef Help',
      description: 'Public help for eMatChef: inventory for clubs and rental teams.',
      themeConfig: {
        siteTitle: 'eMatChef Help',
        nav: [
          { text: 'Help', link: '/help/create-activity' },
          { text: 'App', link: 'https://app.ematchef.ch' },
          { text: 'Website', link: 'https://ematchef.ch' },
        ],
        sidebar: enSidebar,
        outline: { label: 'On this page' },
        footer: {
          message: 'In-app tours stay in the app. These pages are the public help.',
        },
      },
    },
    fr: {
      label: 'Français',
      lang: 'fr',
      title: 'Aide eMatChef',
      description:
        'Aide publique pour eMatChef : matériel pour associations et locations.',
      themeConfig: {
        siteTitle: 'Aide eMatChef',
        nav: [
          { text: 'Aide', link: '/aide/creer-une-activite' },
          { text: 'App', link: 'https://app.ematchef.ch' },
          { text: 'Site', link: 'https://ematchef.ch' },
        ],
        sidebar: frSidebar,
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
  },
})
