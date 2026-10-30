<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  Fields.WmarkaMedia
 *
 * @copyright   (C) 2026 Beibit Sakhanov
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wmarka\Plugin\Fields\WmarkaMedia\Helper;

use Joomla\CMS\Image\Image;
use Joomla\CMS\Uri\Uri;

\defined('_JEXEC') or die;

/**
 * Уменьшенные копии фотографий средствами самой Joomla (библиотека GD, класс Image) — без сторонних библиотек.
 * Копии лежат в media/plg_fields_wmarkamedia/cache, имя содержит размер и отпечаток файла (путь, время изменения,
 * параметры), поэтому подмена снимка даёт новую копию. Если копию сделать нельзя, возвращается null, и вызывающий
 * берёт оригинал.
 */
final class ImageHelper
{
    /**
     * Сколько новых копий можно создать за один запрос. Миниатюры нужны странице сразу — им бюджет больше; крупные кадры нужны
     * только окну просмотра — им меньше, чтобы первый читатель не ждал: недостающие копии появятся при следующих показах страницы.
     *
     * @var array<string, int>
     */
    private static array $budget = ['thumb' => 8, 'full' => 2];

    /** Оригиналы меньше этого веса (байт) и не шире нужного более чем на 15 % копий не требуют */
    private const SMALL_FILE = 150000;

    /** Предел площади исходника (пикселей): больший не раскрываем в памяти, берём оригинал */
    private const MAX_PIXELS = 36000000;

    /**
     * @param   string  $relative  путь внутри сайта, например images/2026/10/foto.jpg
     * @param   int     $maxWidth  наибольшая ширина копии
     * @param   int     $quality   качество 1–100
     * @param   string  $kind      thumb или full: от этого зависит бюджет новых копий
     *
     * @return  array{url:?string, w:int, h:int}|null  url = null — оригинал уже не шире нужного; null — копию не получить
     */
    public static function resized(string $relative, int $maxWidth, int $quality = 82, string $kind = 'thumb'): ?array
    {
        $maxWidth = max(100, min(4000, $maxWidth));

        // нулевой байт (например, из «%00» в пути) заставил бы realpath() бросить ValueError и оборвал бы вывод страницы
        if (str_contains($relative, "\0")) {
            return null;
        }

        $real     = realpath(JPATH_ROOT . '/' . ltrim($relative, '/'));
        $root     = realpath(JPATH_ROOT . '/images');

        // только файлы внутри images/
        if ($real === false || $root === false || !str_starts_with($real, $root . DIRECTORY_SEPARATOR) || !is_file($real)) {
            return null;
        }

        $info = @getimagesize($real);

        if (!$info || $info[0] < 1 || !\in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            return null;
        }

        [$w, $h] = $info;

        // оригинал и так не тяжелее копии: не тратим на него время
        if ($w <= (int) ($maxWidth * 1.15) || filesize($real) < self::SMALL_FILE) {
            return ['url' => null, 'w' => $w, 'h' => $h];
        }

        if ($w * $h > self::MAX_PIXELS || !\extension_loaded('gd') || !self::fitsInMemory($w, $h)) {
            return null;
        }

        $webp   = \function_exists('imagewebp');
        $ext    = $webp ? 'webp' : 'jpg';
        $newH   = (int) round($h * $maxWidth / $w);
        $key    = substr(sha1($real . '|' . filemtime($real) . '|' . $maxWidth . '|' . $quality . '|' . $ext), 0, 10);
        $name   = trim((string) preg_replace('~[^A-Za-z0-9._-]+~', '-', pathinfo($real, PATHINFO_FILENAME)), '-') . '-' . $maxWidth . '-' . $key . '.' . $ext;
        $dir    = JPATH_ROOT . '/media/plg_fields_wmarkamedia/cache';
        $target = $dir . '/' . $name;

        if (!is_file($target)) {
            if ((self::$budget[$kind] ?? 0) <= 0) {
                return null;
            }

            self::$budget[$kind]--;

            try {
                if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
                    return null;
                }

                $tmp   = $target . '.' . getmypid() . '.tmp';
                $image = new Image($real);
                $image->resize($maxWidth, $newH, false, Image::SCALE_INSIDE);
                $image->toFile($tmp, $webp ? IMAGETYPE_WEBP : IMAGETYPE_JPEG, ['quality' => max(1, min(100, $quality))]);
                $image->destroy();

                if (!@rename($tmp, $target)) {
                    @unlink($tmp);

                    return null;
                }
            } catch (\Throwable $e) {
                return null;
            }
        }

        return ['url' => Uri::root(true) . '/media/plg_fields_wmarkamedia/cache/' . $name, 'w' => $maxWidth, 'h' => $newH];
    }

    /** Хватит ли памяти, чтобы раскрыть снимок: GD держит около 5 байт на пиксель, и нехватка памяти — фатальная ошибка, которую не перехватить */
    private static function fitsInMemory(int $w, int $h): bool
    {
        $limit = \ini_get('memory_limit');

        if ($limit === false || $limit === '' || (int) $limit === -1) {
            return true;
        }

        $bytes = (int) $limit;

        switch (strtolower(substr($limit, -1))) {
            case 'g':
                $bytes *= 1024;
                // no break
            case 'm':
                $bytes *= 1024;
                // no break
            case 'k':
                $bytes *= 1024;
        }

        return $w * $h * 5 + 8 * 1024 * 1024 < $bytes - memory_get_usage();
    }
}
