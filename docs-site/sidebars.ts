import type { SidebarsConfig } from '@docusaurus/plugin-content-docs';

const sidebars: SidebarsConfig = {
  docsSidebar: [
    {
      type: 'category',
      label: 'Guides',
      collapsed: false,
      items: ['guides/getting-started', 'guides/authentication', 'guides/sms-production', 'guides/testing', 'guides/observability', 'guides/backup-recovery'],
    },
    {
      type: 'category',
      label: 'Architecture',
      collapsed: false,
      items: ['architecture/overview'],
    },
    {
      type: 'category',
      label: 'API',
      collapsed: false,
      items: [
        'api/README',
        {
          type: 'link',
          label: 'API Explorer (Redoc)',
          href: '/api/explorer/',
        },
        {
          type: 'category',
          label: 'Modules',
          collapsed: false,
          items: [
            'api/modules/core',
            'api/modules/crm',
            'api/modules/hrm',
            'api/modules/finance',
            'api/modules/projects',
            'api/modules/scm',
            'api/modules/mfg',
            'api/modules/sales',
            'api/modules/docs',
            'api/modules/marketplace',
            'api/modules/integrations',
          ],
        },
        {
          type: 'category',
          label: 'Legacy',
          collapsed: true,
          items: [
            'api/legacy/webinocrm-v1',
            'api/legacy/webinocrm-hosting',
          ],
        },
      ],
    },
  ],
};

export default sidebars;
