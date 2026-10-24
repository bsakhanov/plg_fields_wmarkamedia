<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  Fields.WmarkaMedia
 *
 * @copyright   (C) 2026 Beibit Sakhanov
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wmarka\Plugin\Fields\WmarkaMedia\Helper;

use Joomla\CMS\Uri\Uri;

\defined('_JEXEC') or die;

/**
 * Приводит значение поля-галереи к списку кадров. Читает и новый вид строк (image, caption, credit),
 * и старый, который оставило штатное поле «Подформа» (ключи вида field14, field15 и значение медиа-поля {"imagefile":…}),
 * поэтому старые материалы выводятся без переноса данных.
 */
final class GalleryHelper
{
    /**
     * @param   mixed  $value  JSON-строка или уже разобранный массив
     * @param   array  $opt    thumbs (bool), thumbWidth, fullWidth, quality — уменьшенные копии (ImageHelper)
     *
     * @return  array<int, array{thumb:string, full:string, tw:int, th:int, src:string, abs:string, alt:string, caption:string, credit:string, w:int, h:int}>
     */
    public static function rows($value, array $opt = []): array
    {
        $thumbs = !empty($opt['thumbs']);

        if (\is_array($value) && isset($value[0]) && \is_string($value[0])) {
            $value = $value[0];
        }

        if (\is_string($value)) {
            $value = json_decode($value, true);
        }

        if (!\is_array($value)) {
            return [];
        }

        $out    = [];
        $fsPath = [];

        foreach ($value as $row) {
            if (\is_string($row)) {
                $row = json_decode($row, true);
            }

            if (!\is_array($row)) {
                continue;
            }

            $img   = null;
            $texts = [];

            foreach ($row as $key => $val) {
                if (\is_string($val) && $val !== '' && $val[0] === '{') {
                    $decoded = json_decode($val, true);

                    if (\is_array($decoded)) {
                        $val = $decoded;
                    }
                }

                if (\is_array($val) && isset($val['imagefile'])) {
                    $img = $val;

                    continue;
                }

                if (\is_string($val) && preg_match('~\.(?:jpe?g|png|webp|gif|avif)(?:[#?]|$)~i', $val)) {
                    $img = ['imagefile' => $val, 'alt_text' => ''];

                    continue;
                }

                if (\is_string($val) && trim($val) !== '' && $key !== 'caption' && $key !== 'credit') {
                    $texts[] = trim($val);
                }
            }

            if ($img === null) {
                continue;
            }

            $file = (string) $img['imagefile'];
            $w    = $h = 0;

            if (preg_match('~#joomlaImage://[^?]*\?width=(\d+)&height=(\d+)~', $file, $m)) {
                $w = (int) $m[1];
                $h = (int) $m[2];
            }

            $path = (string) preg_replace('~#.*$~', '', $file);

            if (preg_match('~^https?://~i', $path)) {
                $src = $abs = $path;
            } elseif (preg_match('~^[a-z][a-z0-9+.-]*:~i', $path)) {
                continue; // чужие схемы (javascript:, data:) не выводим
            } else {
                $rel = ltrim(str_replace(' ', '%20', $path), '/');
                $src = Uri::root(true) . '/' . $rel;
                $abs = rtrim(Uri::root(), '/') . '/' . $rel;
            }

            $caption = isset($row['caption']) && \is_string($row['caption']) ? trim($row['caption']) : ($texts[0] ?? '');
            $credit  = isset($row['credit']) && \is_string($row['credit']) ? trim($row['credit']) : ($texts[1] ?? '');

            $fsPath[count($out)] = preg_match('~^https?://~i', $path) ? null : rawurldecode($path);

            $out[] = [
                'thumb'   => $src,
                'full'    => $src,
                'tw'      => $w,
                'th'      => $h,
                'src'     => $src,
                'abs'     => $abs,
                'alt'     => trim((string) ($img['alt_text'] ?? '')) ?: $caption,
                'caption' => $caption,
                'credit'  => $credit,
                'w'       => $w,
                'h'       => $h,
            ];
        }

        if ($thumbs && $out) {
            $quality = (int) ($opt['quality'] ?? 82);
            $visible = (int) ($opt['visible'] ?? 0);
            $visible = $visible > 0 ? min($visible, \count($out)) : \count($out);

            // сначала миниатюры тех снимков, что видны на странице: от них зависит первый показ
            foreach ($out as $i => $r) {
                if ($i >= $visible || $fsPath[$i] === null) {
                    continue;
                }

                if ($t = ImageHelper::resized($fsPath[$i], (int) ($opt['thumbWidth'] ?? 640), $quality, 'thumb')) {
                    $out[$i]['thumb'] = $t['url'] ?? $r['src'];
                    $out[$i]['tw']    = $t['w'];
                    $out[$i]['th']    = $t['h'];
                }
            }

            // затем крупные кадры для окна просмотра; пока копии нет, берётся оригинал
            foreach ($out as $i => $r) {
                if ($fsPath[$i] !== null && ($f = ImageHelper::resized($fsPath[$i], (int) ($opt['fullWidth'] ?? 1920), $quality, 'full')) && $f['url']) {
                    $out[$i]['full'] = $f['url'];
                }
            }
        }

        return $out;
    }
}
