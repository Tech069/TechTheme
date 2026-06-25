<?php

namespace Pterodactyl\Traits\Helpers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

trait ThemeLanguages
{
    /**
     * Get all available theme languages.
     */
    public function getThemeLanguages(): array
    {
        $cacheKey = 'theme:languages';

        return Cache::remember($cacheKey, 3600, function () {
            $languages = [];
            $langPath = resource_path('lang');

            if (!is_dir($langPath)) {
                return $this->getDefaultLanguages();
            }

            $directories = File::directories($langPath);

            foreach ($directories as $directory) {
                $locale = basename($directory);
                $jsonFiles = File::glob($directory . '/*.json');
                $phpFiles = File::glob($directory . '/*.php');

                if (!empty($jsonFiles) || !empty($phpFiles)) {
                    $languages[$locale] = [
                        'code' => $locale,
                        'name' => $this->getLanguageName($locale),
                        'file_count' => count($jsonFiles) + count($phpFiles),
                    ];
                }
            }

            return $languages;
        });
    }

    /**
     * Get a language name from its code.
     */
    protected function getLanguageName(string $code): string
    {
        $names = [
            'en' => 'English',
            'ar' => 'Arabic',
            'cs' => 'Czech',
            'da' => 'Danish',
            'de' => 'German',
            'es' => 'Spanish',
            'fa' => 'Persian',
            'fr' => 'French',
            'hu' => 'Hungarian',
            'id' => 'Indonesian',
            'it' => 'Italian',
            'ja' => 'Japanese',
            'ko' => 'Korean',
            'nl' => 'Dutch',
            'no' => 'Norwegian',
            'pl' => 'Polish',
            'pt' => 'Portuguese',
            'ru' => 'Russian',
            'sv' => 'Swedish',
            'tr' => 'Turkish',
            'uk' => 'Ukrainian',
            'vi' => 'Vietnamese',
            'zh' => 'Chinese',
        ];

        return $names[$code] ?? ucfirst($code);
    }

    /**
     * Get the default languages when no language files are found.
     */
    protected function getDefaultLanguages(): array
    {
        return [
            'en' => ['code' => 'en', 'name' => 'English', 'file_count' => 0],
        ];
    }

    /**
     * Check if a language code is valid.
     */
    public function isValidLanguage(string $code): bool
    {
        $languages = $this->getThemeLanguages();

        return isset($languages[$code]);
    }

    /**
     * Get the current user's language preference.
     */
    public function getUserLanguage($user): string
    {
        if ($user && isset($user->language)) {
            return $user->language;
        }

        return config('app.locale', 'en');
    }

    /**
     * Load language translations for a given locale.
     */
    public function loadLanguageTranslations(string $locale): array
    {
        $cacheKey = "theme:translations:{$locale}";

        return Cache::remember($cacheKey, 3600, function () use ($locale) {
            $translations = [];
            $langPath = resource_path("lang/{$locale}");

            if (!is_dir($langPath)) {
                return $translations;
            }

            $files = File::glob($langPath . '/*.php');

            foreach ($files as $file) {
                $namespace = pathinfo($file, PATHINFO_FILENAME);
                $translations[$namespace] = require $file;
            }

            $jsonFiles = File::glob($langPath . '/*.json');

            foreach ($jsonFiles as $file) {
                $namespace = pathinfo($file, PATHINFO_FILENAME);
                $translations[$namespace] = json_decode(File::get($file), true);
            }

            return $translations;
        });
    }
}
