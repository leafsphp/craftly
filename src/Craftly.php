<?php

namespace Craftly;

use Symfony\Component\Yaml\Yaml;

/**
 * Craftly Controller
 * ---
 * Everything related to craftly internals
 */
class CraftlyController
{
    public function index()
    {
        return response()->json([
            'message' => 'Craftly API is working'
        ]);
    }

    public function getConfig()
    {
        return response()->json([
            'logo' => _env('APP_LOGO'),
            'name' => _env('APP_NAME'),
            'version' => _env('APP_VERSION', '1.0.0'),
            'phpVersion' => PHP_VERSION,
            'leafVersion' => _env('LEAF_VERSION', 'latest'),
            'languages' => lingo()->getAvailableLocalesWithNames(),
        ]);
    }

    public function getApp()
    {
        $data = Core::getApp();

        return response()->json([
            'logo' => _env('APP_LOGO'),
            'name' => _env('APP_NAME'),
            'version' => _env('APP_VERSION', '1.0.0'),
            'languages' => lingo()->getAvailableLocalesWithNames(),
            'theme' => $data['theme'],
            'pages' => $data['pages'],
            'colors' => $data['colors'],
            'activities' => $data['log'],
        ]);
    }

    public function show(array $data)
    {
        $pageData = Core::page($data);
        $pageData['routeParams'] = $data['params'];
        $pageData['preview'] = !!request()->params('__preview', false);

        Core::render($pageData);
    }

    public function getPage(string $page)
    {
        return response()->json(
            Core::getPageFromName($page)
        );
    }

    public function createPage(string $page)
    {
        $pageData = request()->get([
            'title',
            'route'
        ]);

        $pageData['name'] = $page;

        try {
            Core::createPage($pageData);

            return response()->json([
                'message' => 'Page created successfully'
            ]);
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    public function getLangs()
    {
        $response = [];
        $translations = lingo()->getAvailableLocales();

        foreach ($translations as $translation) {

            $response[] = [
                'code' => $translation,
                'name' => lingo()->getLocaleName($translation),
                'data' => lingo()->getLocaleData($translation),
                'updated_at' => date('Y-m-d H:i:s', filemtime(path(lingo()->config('locales.path'))->join("$translation.yml")))
            ];
        }

        return response()->json(
            $response
        );
    }

    public function createLang(string $lang)
    {
        $langFile = path(lingo()->config('locales.path'))->join("$lang.yml");

        if (storage()->exists($langFile)) {
            return response()->json([
                'error' => 'Language already exists'
            ], 400);
        }

        storage()->createFile($langFile, Yaml::dump([]));

        return response()->json([
            'message' => 'Language created successfully'
        ]);
    }

    public function updateLang(string $lang)
    {
        $data = request()->body(false);
        $langFile = path(lingo()->config('locales.path'))->join("$lang.yml");

        if (!storage()->exists($langFile)) {
            return response()->json([
                'error' => 'Language does not exist'
            ], 404);
        }

        storage()->writeFile($langFile, Yaml::dump($data));

        return $this->getLang($lang);
    }

    public function getLang(string $lang)
    {
        $translations = lingo()->getAvailableLocales();
        $currentTranslation = [
            'code' => $lang,
            'name' => lingo()->getLocaleName($lang),
            'data' => lingo()->getLocaleData($lang),
        ];

        $allKeys = [];
        $extraKeys = [];

        foreach ($translations as $translation) {
            $localeData = lingo()->getLocaleData($translation);
            $allKeys = array_merge($allKeys, array_keys($localeData));
        }

        $allKeys = array_unique($allKeys);
        $currentKeys = array_keys($currentTranslation['data']);
        $missingKeys = array_diff($allKeys, $currentKeys);


        foreach ($translations as $translation) {
            if ($translation === $lang) {
                continue;
            }

            $otherKeys = array_keys(lingo()->getLocaleData($translation));
            $keysNotInOther = array_diff($currentKeys, $otherKeys);

            if (!empty($keysNotInOther)) {
                $extraKeys[$translation] = $keysNotInOther;
            }
        }

        $currentTranslation['compare'] = [
            'missingTranslationsCount' => count($missingKeys),
            'missingTranslations' => array_values($missingKeys),
            'extraKeysCount' => array_sum(array_map('count', $extraKeys)),
            'extraKeys' => $extraKeys,
            'totalKeys' => count($allKeys),
            'completeness' => count($allKeys) > 0
                ? round((count($currentKeys) / count($allKeys)) * 100, 2)
                : 100
        ];

        return response()->json(
            $currentTranslation
        );
    }

    public function getMedia()
    {
        $basePath = 'public/public';

        if (!is_dir($basePath)) {
            return response()->json([
                'data' => [],
                'stats' => [
                    'total' => 0,
                    'images' => 0,
                    'videos' => 0,
                    'documents' => 0,
                    'storageUsed' => '0 MB',
                    'storageLimit' => '0 GB',
                ]
            ]);
        }

        $imageExt = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp'];
        $videoExt = ['mp4', 'mov', 'avi', 'mkv', 'webm', 'flv', 'wmv', 'm4v'];
        $documentExt = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt', 'ppt', 'pptx'];

        $id = 1;
        $media = [];
        $files = array_filter(glob("$basePath/**/**/*", GLOB_BRACE), 'is_file');

        foreach ($files as $file) {
            $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

            if (in_array($extension, $imageExt)) {
                $type = 'image';
            } elseif (in_array($extension, $videoExt)) {
                $type = 'video';
            } elseif (in_array($extension, $documentExt)) {
                $type = 'document';
            } else {
                continue;
            }

            $relativePath = ltrim(str_replace($basePath, '', $file), '/\\');
            $normalizedPath = str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);
            $publicUrl = '/public/' . ltrim($normalizedPath, '/');

            $media[] = [
                'id' => $id++,
                'name' => basename($file),
                'type' => $type,
                'size' => round(filesize($file) / (1024 * 1024), 2) . ' MB',
                'url' => $publicUrl,
                'thumbnail' => $type === 'image' ? $publicUrl : null,
                'uploaded_at' => date('c', filemtime($file)),
            ];
        }

        $stats = [
            'total' => count($media),
            'images' => count(array_filter($media, fn ($item) => $item['type'] === 'image')),
            'videos' => count(array_filter($media, fn ($item) => $item['type'] === 'video')),
            'documents' => count(array_filter($media, fn ($item) => $item['type'] === 'document')),
            'storageUsed' => round(\Leaf\FS\Directory::size($basePath, 'mb'), 2) . ' MB',
            'storageLimit' => disk_total_space($basePath) / (1024 * 1024 * 1024) . ' GB',
        ];

        return response()->json([
            'data' => $media,
            'stats' => $stats,
        ]);
    }

    public function getAvailableModels()
    {
        $modelsPath = app()->config('app.path') . '/models';
        $models = [];

        if (!is_dir($modelsPath)) {
            return response()->json(['data' => []]);
        }

        $files = scandir($modelsPath);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..')
                continue;

            if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                $className = 'App\\Models\\' . pathinfo($file, PATHINFO_FILENAME);

                if (class_exists($className)) {
                    try {
                        $reflection = new \ReflectionClass($className);
                        $instance = new $className;

                        // Get fillable fields or all public properties
                        $fields = property_exists($instance, 'fillable')
                            ? $instance->fillable
                            : array_keys(get_object_vars($instance));

                        $models[] = [
                            'name' => $className,
                            'label' => pathinfo($file, PATHINFO_FILENAME),
                            'fields' => array_merge($fields, ['created_at', 'updated_at']),
                            'table' => property_exists($instance, 'table')
                                ? $instance->table
                                : strtolower(pathinfo($file, PATHINFO_FILENAME)) . 's',
                        ];
                    } catch (\Exception $e) {
                        // Skip models that can't be instantiated
                        continue;
                    }
                }
            }
        }

        return response()->json(['data' => $models]);
    }
}

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
