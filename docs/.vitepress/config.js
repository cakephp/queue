import baseConfig from '@cakephp/docs-skeleton/config'
import { createRequire } from 'module'

const require = createRequire(import.meta.url)
const tocEn = require('./toc_en.json')

const versions = {
  text: '2.x',
  items: [
    { text: '2.x (current)', link: 'https://book.cakephp.org/queue/2/', target: '_self' },
    { text: '1.x', link: 'https://book.cakephp.org/queue/1/en/', target: '_self' },
  ],
}

export default {
  extends: baseConfig,
  srcDir: '.',
  title: 'Queue',
  description: 'CakePHP Queue Documentation',
  base: '/queue/2/',
  rewrites: {
    'en/:slug*': ':slug*',
  },
  sitemap: {
    hostname: 'https://book.cakephp.org/queue/2/',
  },
  themeConfig: {
    siteTitle: false,
    pluginName: 'Queue',
    socialLinks: [
      { icon: 'github', link: 'https://github.com/cakephp/queue' },
    ],
    editLink: {
      pattern: 'https://github.com/cakephp/queue/edit/2.x/docs/:path',
      text: 'Edit this page on GitHub',
    },
    sidebar: tocEn,
    nav: [
      { text: 'CakePHP', link: 'https://cakephp.org' },
      { text: 'API', link: 'https://api.cakephp.org/queue/' },
      { ...versions },
    ],
  },
  locales: {
    root: {
      label: 'English',
      lang: 'en',
      themeConfig: {
        sidebar: tocEn,
      },
    },
  },
}
