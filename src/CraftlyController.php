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

    public function getModels()
    {
        $modelFiles = glob(AppPaths('models') . '/*.php');

        return response()->json([
            $modelFiles
        ]);
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
