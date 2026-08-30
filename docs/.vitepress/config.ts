import { defineConfig } from 'vitepress'

const advancedSection = {
  text: 'Advanced',
  collapsed: false,
  items: [
    { text: 'Authorization', link: '/advanced/authorization' },
    { text: 'Plugins', link: '/advanced/plugins' },
    { text: 'Custom Nodes', link: '/advanced/custom-nodes' },
    { text: 'Execution Engine', link: '/advanced/execution-engine' },
    { text: 'Security', link: '/advanced/security' },
    { text: 'Testing', link: '/advanced/testing' },
  ],
}

const referenceSection = {
  text: 'Reference',
  collapsed: false,
  items: [
    { text: 'PHP API', link: '/api/php-api' },
    { text: 'REST Endpoints', link: '/api/rest-endpoints' },
    { text: 'Configuration', link: '/configuration' },
    { text: 'Events', link: '/events' },
    { text: 'Artisan Commands', link: '/commands' },
    { text: 'Database Schema', link: '/database' },
  ],
}

export default defineConfig({
  title: 'Aitumalow',
  description: 'Turn your Laravel app into a programmable automation platform — design workflows visually, expose bounded MCP tools, and keep your core code clean.',

  head: [
    ['link', { rel: 'icon', type: 'image/svg+xml', href: '/logo.svg' }],
  ],

  themeConfig: {
    nav: [
      { text: 'Guide', link: '/getting-started/installation' },
      { text: 'Nodes', link: '/nodes/if-condition' },
      { text: 'Editor', link: '/ui-editor' },
      { text: 'Examples', link: '/examples/mailbox-quote-agent' },
      { text: 'API', link: '/api/php-api' },
    ],

    sidebar: {
      '/': [
        {
          text: 'Getting Started',
          items: [
            { text: 'Why Use This?', link: '/getting-started/why-use-this' },
            { text: 'Installation', link: '/getting-started/installation' },
            { text: 'Quick Start', link: '/getting-started/quick-start' },
            { text: 'Core Concepts', link: '/getting-started/concepts' },
          ],
        },
        {
          text: 'Triggers',
          collapsed: false,
          items: [
            { text: 'Manual', link: '/triggers/manual' },
            { text: 'Schedule', link: '/triggers/schedule' },
          ],
        },
        {
          text: 'Condition Nodes',
          collapsed: false,
          items: [
            { text: 'IF Condition', link: '/nodes/if-condition' },
            { text: 'Switch', link: '/nodes/switch' },
          ],
        },
        {
          text: 'Transformer Nodes',
          collapsed: false,
          items: [
            { text: 'Set Fields', link: '/nodes/set-fields' },
            { text: 'Parse Data', link: '/nodes/parse-data' },
          ],
        },
        {
          text: 'Control Nodes',
          collapsed: false,
          items: [
            { text: 'Loop', link: '/nodes/loop' },
            { text: 'Merge', link: '/nodes/merge' },
            { text: 'Delay', link: '/nodes/delay' },
            { text: 'Wait / Resume', link: '/nodes/wait-resume' },
          ],
        },
        {
          text: 'Utility Nodes',
          collapsed: false,
          items: [
            { text: 'Filter', link: '/nodes/filter' },
            { text: 'Aggregate', link: '/nodes/aggregate' },
          ],
        },
        {
          text: 'Expressions',
          items: [
            { text: 'Expression Engine', link: '/expressions/' },
          ],
        },
        {
          text: 'Integrations',
          collapsed: false,
          items: [
            { text: 'Visual Editor', link: '/ui-editor' },
            { text: 'MCP Server', link: '/mcp' },
          ],
        },
        advancedSection,
        referenceSection,
      ],
      '/examples/': [
        {
          text: 'Examples',
          items: [
            { text: 'Scheduled Report', link: '/examples/scheduled-report' },
            { text: 'Mailbox Quote Agent', link: '/examples/mailbox-quote-agent' },
          ],
        },
      ],
    },

    search: {
      provider: 'local',
    },

    footer: {
      message: 'Released under the MIT License.',
    },
  },
})
