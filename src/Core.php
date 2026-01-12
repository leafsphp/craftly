<?php

namespace Craftly;

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
        app()->get('/__craftly_api/app/config', [
            'namespace' => 'Craftly',
            'lingo.no_locale_prefix' => true,
            'CraftlyController@getConfig'
        ]);

        app()->get('/__craftly_api/app/info', [
            'namespace' => 'Craftly',
            'lingo.no_locale_prefix' => true,
            'CraftlyController@getApp'
        ]);

        app()->get('/__craftly_api/app/pages', [
            'namespace' => 'Craftly',
            'lingo.no_locale_prefix' => true,
            'CraftlyController@getPages'
        ]);

        app()->get('/__craftly_api/app/media', [
            'namespace' => 'Craftly',
            'lingo.no_locale_prefix' => true,
            'CraftlyController@getMedia'
        ]);

        app()->get('/__craftly_api/app/models', [
            'namespace' => 'Craftly',
            'lingo.no_locale_prefix' => true,
            'CraftlyController@getModels'
        ]);

        app()->get('/__craftly_api/app/pages/{page}', [
            'namespace' => 'Craftly',
            'lingo.no_locale_prefix' => true,
            'CraftlyController@getPage'
        ]);

        app()->post('/__craftly_api/app/pages/{page}', [
            'namespace' => 'Craftly',
            'lingo.no_locale_prefix' => true,
            'CraftlyController@createPage'
        ]);

        app()->put('/__craftly_api/app/pages/{page}', [
            'namespace' => 'Craftly',
            'lingo.no_locale_prefix' => true,
            'CraftlyController@updatePage'
        ]);

        app()->get('/__craftly_api/app/langs', [
            'namespace' => 'Craftly',
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

        app()->put('/__craftly_api/app/langs/{lang}', [
            'namespace' => 'Craftly',
            'lingo.no_locale_prefix' => true,
            'CraftlyController@updateLang'
        ]);

        static::buildRoutes();
    }

    public static function buildRoutes()
    {
        $routeRegistry = path(ViewsPath('__craftly', false))->join('routes.yml');

        if (storage()->exists($routeRegistry)) {
            $routes = Yaml::parseFile($routeRegistry);

            foreach ($routes as $route) {
                if ($route['status'] === 'archived') {
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
        $siteLog = [];
        $sitePages = [];
        $siteTheme = null;
        $siteColors = null;

        $sitePagesDirectory = path(ViewsPath('__craftly', false))->join('pages');
        $siteThemeFile = path(ViewsPath('__craftly', false))->join('ui', 'theme.yml');
        $colorsFile = path(ViewsPath('__craftly', false))->join('ui', 'colors.yml');
        $logFile = path(ViewsPath('__craftly', false))->join('ui', 'log.yml');

        if (storage()->exists($siteThemeFile)) {
            $siteTheme = Yaml::parseFile($siteThemeFile);
        }

        if (storage()->exists($colorsFile)) {
            $siteColors = Yaml::parseFile($colorsFile);
        }

        if (storage()->exists($logFile)) {
            $siteLog = Yaml::parseFile($logFile);
        }

        if (storage()->exists($sitePagesDirectory)) {
            $pageFiles = array_filter(glob($sitePagesDirectory . '/*.yml'), 'is_file');

            foreach ($pageFiles as $file) {
                $pageData = Yaml::parseFile($file);

                if ($pageData && $pageData['status'] !== 'archived') {
                    $sitePages[] = $pageData;
                }
            }
        }

        return [
            'log' => $siteLog,
            'theme' => $siteTheme,
            'pages' => $sitePages,
            'colors' => $siteColors,
        ];
    }

    public static function createPage(array $pageData)
    {
        $sitePagesDirectory = path(ViewsPath('__craftly', false))->join('pages');
        $routeRegistry = path(ViewsPath('__craftly', false))->join('routes.yml');
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

        storage()->createFile($pageFile, Yaml::dump($page));
        storage()->writeFile($routeRegistry, Yaml::dump($routes));

        return true;
    }

    public static function page(array $data)
    {
        $pageFile = path(ViewsPath('__craftly', false))->join('pages', $data['route']['src']);

        if (!storage()->exists($pageFile)) {
            throw new \ErrorException('Page file not found: ' . $pageFile);
        }

        return Yaml::parseFile($pageFile);
    }

    public static function getPageFromName(string $page)
    {
        $pageFile = path(ViewsPath('__craftly', false))->join('pages', "$page.yml");

        if (!storage()->exists($pageFile)) {
            throw new \ErrorException('Page file not found: ' . $pageFile);
        }

        return Yaml::parseFile($pageFile);
    }

    public static function render(array $data)
    {
        $pageData = \array_merge($data['variables'], [
            '__craftly' => $data
        ]);

        return response()->render('__craftly.page', $pageData);
    }
}
