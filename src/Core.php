<?php

namespace Craftly;

use Illuminate\Support\Str;
use Symfony\Component\Yaml\Yaml;

/**
 * Craftly Core
 * ----
 * Contains core functionality for Craftly
 */
class Core
{
    public static function setup()
    {
        app()->get('/__craftly_api/app/ping', [
            'namespace' => 'Craftly',
            'sitemap' => false,
            'lingo.no_locale_prefix' => true,
            'CraftlyController@ping'
        ]);

        app()->get('/__craftly_api/app/config', [
            'namespace' => 'Craftly',
            'sitemap' => false,
            'lingo.no_locale_prefix' => true,
            'CraftlyController@getConfig'
        ]);

        app()->get('/__craftly_api/app/info', [
            'namespace' => 'Craftly',
            'sitemap' => false,
            'lingo.no_locale_prefix' => true,
            'CraftlyController@getApp'
        ]);

        app()->get('/__craftly_api/app/redirects', [
            'namespace' => 'Craftly',
            'sitemap' => false,
            'lingo.no_locale_prefix' => true,
            'CraftlyController@getRedirects'
        ]);

        app()->get('/__craftly_api/app/theme', [
            'namespace' => 'Craftly',
            'sitemap' => false,
            'lingo.no_locale_prefix' => true,
            'CraftlyController@getTheme'
        ]);

        app()->get('/__craftly_api/app/logs', [
            'namespace' => 'Craftly',
            'sitemap' => false,
            'lingo.no_locale_prefix' => true,
            'CraftlyController@getAppLogs'
        ]);

        app()->get('/__craftly_api/app/pages', [
            'namespace' => 'Craftly',
            'sitemap' => false,
            'lingo.no_locale_prefix' => true,
            'CraftlyController@getPages'
        ]);

        app()->get('/__craftly_api/app/media', [
            'namespace' => 'Craftly',
            'sitemap' => false,
            'lingo.no_locale_prefix' => true,
            'CraftlyController@getMedia'
        ]);

        app()->post('/__craftly_api/app/media', [
            'namespace' => 'Craftly',
            'sitemap' => false,
            'lingo.no_locale_prefix' => true,
            'CraftlyController@uploadMedia'
        ]);

        app()->get('/__craftly_api/app/models', [
            'namespace' => 'Craftly',
            'sitemap' => false,
            'lingo.no_locale_prefix' => true,
            'CraftlyController@getModels'
        ]);

        app()->get('/__craftly_api/app/models/show', [
            'namespace' => 'Craftly',
            'sitemap' => false,
            'lingo.no_locale_prefix' => true,
            'CraftlyController@getModelItems'
        ]);

        app()->post('/__craftly_api/app/pages/sync', [
            'namespace' => 'Craftly',
            'sitemap' => false,
            'lingo.no_locale_prefix' => true,
            'CraftlyController@syncPages'
        ]);

        app()->get('/__craftly_api/app/pages/{page}', [
            'namespace' => 'Craftly',
            'sitemap' => false,
            'lingo.no_locale_prefix' => true,
            'CraftlyController@getPage'
        ]);

        app()->post('/__craftly_api/app/pages/{page}', [
            'namespace' => 'Craftly',
            'sitemap' => false,
            'lingo.no_locale_prefix' => true,
            'CraftlyController@createPage'
        ]);

        app()->put('/__craftly_api/app/pages/{page}', [
            'namespace' => 'Craftly',
            'sitemap' => false,
            'lingo.no_locale_prefix' => true,
            'CraftlyController@updatePage'
        ]);

        app()->get('/__craftly_api/app/langs', [
            'namespace' => 'Craftly',
            'sitemap' => false,
            'lingo.no_locale_prefix' => true,
            'CraftlyController@getLangs'
        ]);

        app()->get('/__craftly_api/app/langs/{lang}', [
            'namespace' => 'Craftly',
            'lingo.no_locale_prefix' => true,
            'CraftlyController@getLang'
        ]);

        app()->post('/__craftly_api/app/langs/{lang}', [
            'namespace' => 'Craftly',
            'lingo.no_locale_prefix' => true,
            'CraftlyController@createLang'
        ]);

        app()->delete('/__craftly_api/app/langs/{lang}', [
            'namespace' => 'Craftly',
            'lingo.no_locale_prefix' => true,
            'CraftlyController@deleteLang'
        ]);

        app()->put('/__craftly_api/app/langs/{lang}', [
            'namespace' => 'Craftly',
            'lingo.no_locale_prefix' => true,
            'CraftlyController@updateLang'
        ]);

        app()->post('/__craftly_api/app/langs/{lang}/toggle', [
            'namespace' => 'Craftly',
            'lingo.no_locale_prefix' => true,
            'CraftlyController@toggleLang'
        ]);

        static::buildRoutes();
    }

    public static function buildRoutes()
    {
        $routeRegistry = path(StoragePath('craftly', false))->join('routes.yml');

        if (storage()->exists($routeRegistry)) {
            $routes = Yaml::parseFile($routeRegistry);

            foreach ($routes as $route) {
                if ($route['status'] === 'archived') {
                    continue;
                }

                if ($route['type'] === 'redirect') {
                    app()->match(
                        implode('|', $route['options']['methods'] ?? []),
                        $route['path'],
                        [
                            'lingo.no_locale_prefix' => true,
                            function () use ($route) {
                                return response()->redirect($route['options']['to'], 303);
                            }
                        ]
                    );

                    continue;
                }

                $handler = [
                    'namespace' => 'Craftly',
                    function (...$params) use ($route) {
                        (new CraftlyController)->show([
                            'params' => $params,
                            'route' => $route,
                        ]);
                    }
                ];

                if (isset($route['langs']) && $route['langs'] === false) {
                    $handler['lingo.no_locale_prefix'] = true;
                } elseif (isset($route['langs']) && \is_array($route['langs'])) {
                    $handler['lingo.routes'] = $route['langs'];
                }

                app()->get($route['path'], $handler);
            }
        }
    }

    public static function getApp()
    {
        $sitePages = [];
        $siteTheme = null;

        $siteThemeFile = path(ViewsPath('theme.yml', false))->normalize();
        $templatesDirectory = path(ViewsPath('layouts', false))->normalize();
        $sitePagesDirectory = path(StoragePath('craftly', false))->join('pages');
        $dataSourcesDirectory = path(dirname(ViewsPath('', false)))->join('content');

        if (storage()->exists($siteThemeFile)) {
            $siteTheme = Yaml::parseFile($siteThemeFile);

            if (storage()->exists($templatesDirectory)) {
                $templates = glob("$templatesDirectory/*.blade.php");

                foreach ($templates as $template) {
                    $name = basename($template, '.blade.php');

                    if ($name !== 'app') {
                        $siteTheme['templates'][] = $name;
                    }
                }
            } else {
                $siteTheme['templates'] = [];
            }

            if (storage()->exists($dataSourcesDirectory)) {
                $dataSources = glob("$dataSourcesDirectory/*.php");

                foreach ($dataSources as $dataSource) {
                    $name = basename($dataSource, '.php');

                    if ($name !== 'Content') {
                        $siteTheme['dataSources'][] = $name;
                    }
                }
            } else {
                $siteTheme['dataSources'] = [];
            }
        }

        if (storage()->exists($sitePagesDirectory)) {
            $pageFiles = array_filter(glob("$sitePagesDirectory/*.yml"), 'is_file');

            foreach ($pageFiles as $file) {
                $pageData = Yaml::parseFile($file);

                if ($pageData && $pageData['status'] !== 'archived') {
                    $sitePages[] = $pageData;
                }
            }
        }

        return [
            'theme' => $siteTheme,
            'pages' => $sitePages,
        ];
    }

    public static function createPage(array $pageData)
    {
        $sitePagesDirectory = path(StoragePath('craftly', false))->join('pages');
        $routeRegistry = path(StoragePath('craftly', false))->join('routes.yml');
        $pageFile = path($sitePagesDirectory)->join("{$pageData['name']}.yml");

        if (storage()->exists($pageFile)) {
            return response()->json([
                'message' => 'Page already exists.'
            ], 409);
        }

        $routes = Yaml::parseFile($routeRegistry);
        $page = [
            'name' => $pageData['name'],
            'title' => $pageData['title'],
            'status' => 'draft',
            'seo' => [
                'image' => null,
                'title' => $pageData['title'],
                'description' => '',
                'og' => [
                    'image' => null,
                    'title' => $pageData['title'],
                    'description' => ''
                ],
                'twitter' => [
                    'image' => null,
                    'title' => $pageData['title'],
                    'description' => ''
                ]
            ],
            'head' => [],
            'variables' => [],
            'blocks' => [],
            'routes' => [
                'default' => $pageData['route'],
                'langs' => []
            ],
            'createdAt' => tick()->format(),
            'modifiedAt' => tick()->format()
        ];

        $routes[] = [
            'page' => $pageData['name'],
            'path' => $pageData['route'],
            'status' => 'draft',
            'src' => "{$pageData['name']}.yml"
        ];

        storage()->writeFile($routeRegistry, Yaml::dump($routes));
        storage()->createFile($pageFile, Yaml::dump($page), [
            'recursive' => true
        ]);

        return true;
    }

    public static function syncPages()
    {
        $payload = json_decode(file_get_contents('php://input'), true);
        $data = $payload['data'] ?? [];

        $sitePagesDirectory = path(StoragePath('craftly', false))->join('pages');
        $routeRegistry = path(StoragePath('craftly', false))->join('routes.yml');

        if (!empty($data)) {
            storage()->delete($routeRegistry);
            storage()->delete($sitePagesDirectory);

            storage()->createFolder($sitePagesDirectory, [
                'recursive' => true
            ]);
            storage()->createFile($routeRegistry, '', [
                'recursive' => true
            ]);
        }

        foreach ($data as $page) {
            $pageFile = path($sitePagesDirectory)->join("{$page['name']}.yml");
            $pageData = [
                'page' => $page['name'],
                'path' => $page['path'],
                'status' => $page['status'],
                'type' => $page['type'],
                'src' => "{$page['name']}.yml",
                'options' => [
                    'from' => $page['config']['from'] ?? null,
                    'to' => $page['config']['to'] ?? null,
                    'methods' => $page['config']['methods'] ?? []
                ],
            ];

            if (isset($page['config']['use_route_localization']) && ($page['config']['use_route_localization'] === '0' || $page['config']['use_route_localization'] == false)) {
                $pageData['langs'] = false;
            } elseif (isset($page['config']['use_localized_routes']) && ($page['config']['use_localized_routes'] === '1' || $page['config']['use_localized_routes'] == true)) {
                $pageData['langs'] = $page['lang_routes'];
            }

            $routes[] = $pageData;

            storage()->createFile($pageFile, Yaml::dump($page, 2, 4, Yaml::DUMP_COMPACT_NESTED_MAPPING));
            storage()->writeFile($routeRegistry, Yaml::dump($routes));
        }

        return true;
    }

    public static function page(array $data)
    {
        $pageFile = path(StoragePath('craftly', false))->join('pages', $data['route']['src']);

        if (!storage()->exists($pageFile)) {
            throw new \ErrorException("Page file not found: $pageFile");
        }

        return Yaml::parseFile($pageFile);
    }

    public static function getPageFromName(string $page)
    {
        $pageFile = path(StoragePath('craftly', false))->join('pages', "$page.yml");

        if (!storage()->exists($pageFile)) {
            throw new \ErrorException("Page file not found: $pageFile");
        }

        return Yaml::parseFile($pageFile);
    }

    public static function render(array $data)
    {
        $dataFile = 'App\Content\\' . Str::pascal(($data['config']['data_source'] ?? 'default'));

        if (!class_exists($dataFile)) {
            $dataFile = \App\Content\DefaultContent::class;
        }

        $pageData = (new $dataFile($data))->getData();
        $pageToRender = 'layouts.' . strtolower($data['config']['template'] ?? 'default');

        return response()->render($pageToRender, $pageData);
    }
}
