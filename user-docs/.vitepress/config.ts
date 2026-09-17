import { defineConfig } from 'vitepress'

export default defineConfig({
  title: 'eMatChef Hilfe',
  description:
    'Öffentliche Hilfe zu eMatChef: Materialverwaltung für Vereine und Vermietungen.',
  lang: 'de-CH',
  cleanUrls: true,
  lastUpdated: true,
  srcExclude: ['README.md'],
  sitemap: {
    hostname: 'https://docs.ematchef.ch',
  },
  head: [
    ['meta', { name: 'robots', content: 'index, follow' }],
    ['link', { rel: 'canonical', href: 'https://docs.ematchef.ch/' }],
  ],
  themeConfig: {
    siteTitle: 'eMatChef Hilfe',
    nav: [
      { text: 'Hilfe', link: '/hilfe/aktivitaet-anlegen' },
      { text: 'App', link: 'https://app.ematchef.ch' },
      { text: 'Website', link: 'https://ematchef.ch' },
    ],
    sidebar: [
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
    ],
    search: { provider: 'local' },
    outline: { label: 'Auf dieser Seite' },
    docFooter: { prev: 'Zurück', next: 'Weiter' },
    returnToTopLabel: 'Nach oben',
    sidebarMenuLabel: 'Menü',
    darkModeSwitchLabel: 'Darstellung',
    footer: {
      message: 'Touren bleiben in der App. Diese Seiten sind die öffentliche Hilfe.',
    },
  },
})
