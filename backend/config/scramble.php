<?php

use Dedoc\Scramble\Http\Middleware\RestrictedDocsAccess;

return [

  /*
  |--------------------------------------------------------------------------
  | Scramble Routes
  |--------------------------------------------------------------------------
  |
  | Scramble docs are served from Docusaurus (docs-site). Default Laravel UI
  | routes are disabled in AppServiceProvider via Scramble::ignoreDefaultRoutes().
  |
  */

  'api_path' => 'api/v1',

  'api_domain' => null,

  'export_path' => base_path('../docs-site/openapi/openapi.json'),

  'cache' => [
    'key' => 'scramble.openapi',
    'store' => 'file',
  ],

  'info' => [
    'version' => env('API_VERSION', '1.0.0'),
    'description' => 'WebinoERM modular ERP/CRM REST API. Regenerate via `composer export-openapi`.',
  ],

  'ui' => [
    'title' => 'Webino ERP API',
  ],

  'renderer' => 'elements',

  'renderers' => [
    'elements' => [
      'view' => 'scramble::docs',
      'theme' => 'light',
      'hideTryIt' => false,
      'hideSchemas' => false,
      'logo' => '',
      'tryItCredentialsPolicy' => 'include',
      'layout' => 'responsive',
      'router' => 'hash',
    ],
    'scalar' => [
      'view' => 'scramble::scalar',
      'cdn' => 'https://cdn.jsdelivr.net/npm/@scalar/api-reference',
      'theme' => 'laravel',
      'proxyUrl' => 'https://proxy.scalar.com',
      'darkMode' => false,
      'showDeveloperTools' => 'never',
      'agent' => ['disabled' => true],
      'credentials' => 'include',
    ],
  ],

  'servers' => [
    'Default' => '/api',
  ],

  'enum_cases_description_strategy' => 'description',

  'enum_cases_names_strategy' => false,

  'flatten_deep_query_parameters' => true,

  'middleware' => [
    'web',
    RestrictedDocsAccess::class,
  ],

  'extensions' => [],

  'security_strategy' => null,
];
