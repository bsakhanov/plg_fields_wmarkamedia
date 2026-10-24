<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  Fields.WmarkaMedia
 *
 * Тип поля «Видео»: YouTube, Vimeo, VK, Rutube, Facebook, Instagram, TikTok, Telegram, файл .mp4/.webm.
 * Макет можно переопределить: templates/<шаблон>/html/plg_fields_wmarkamedia/wmarkavideo.php
 *
 * @copyright   (C) 2026 Beibit Sakhanov
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Wmarka\Plugin\Fields\WmarkaMedia\Helper\Assets;
use Wmarka\Plugin\Fields\WmarkaMedia\Helper\VideoHelper;

$raw = \is_array($field->value) ? (string) reset($field->value) : (string) $field->value;

if (trim($raw) === '') {
    return;
}

$ref = VideoHelper::parse($raw, (bool) $fieldParams->get('nocookie', 1));

if ($ref === null) {
    return;
}

$this->loadLanguage();
Assets::load();

$e       = static fn ($s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$title   = trim((string) ($item->title ?? '')) ?: $ref->label;
$mode    = (string) $fieldParams->get('mode', 'facade');
$showLnk = (bool) $fieldParams->get('show_link', 1);
$extra   = trim((string) $fieldParams->get('css_class', ''));

// соотношение сторон: по сервису и ссылке или заданное в настройках поля
$ratio    = $ref->ratio;
$vertical = $ref->vertical;
$wanted   = (string) $fieldParams->get('ratio', 'auto');

if ($wanted !== 'auto' && preg_match('~^(\d+):(\d+)$~', $wanted, $rm) && (int) $rm[2] > 0) {
    $ratio    = $rm[1] . '/' . $rm[2];
    $vertical = (int) $rm[1] < (int) $rm[2];
}

$vmax  = max(200, min(720, (int) $fieldParams->get('vertical_max_width', 360)));
$style = '--wm-ratio:' . $e($ratio) . ';' . ($vertical ? '--wm-max:' . $vmax . 'px;' : '');
$class = 'wm-video wm-video--' . $e($ref->provider) . ($vertical ? ' wm-video--vertical' : '') . ($extra !== '' ? ' ' . $e($extra) : '');

// только ссылка: короткие адреса TikTok и неизвестные сайты
if ($ref->kind === 'link') {
    echo '<p class="wm-video-link"><a href="' . $e($ref->page) . '" target="_blank" rel="noopener noreferrer">'
        . $e(Text::sprintf('PLG_FIELDS_WMARKAMEDIA_WATCH_ON', $ref->label)) . '</a></p>';

    return;
}

echo '<div class="' . $class . '" style="' . $style . '"';

if ($ref->kind === 'iframe' && $mode !== 'iframe') {
    // фасад: до нажатия страница не обращается к чужим серверам
    $poster = $fieldParams->get('poster', 'auto') === 'auto' ? $ref->poster : null;

    echo ' data-wm-video data-embed="' . $e($ref->embedPlay) . '" data-title="' . $e($title) . '">';
    echo '<button type="button" class="wm-video__btn" aria-label="' . $e(Text::sprintf('PLG_FIELDS_WMARKAMEDIA_WATCH', $title)) . '">';

    if ($poster) {
        echo '<img class="wm-video__poster" src="' . $e($poster) . '" alt="" loading="lazy" decoding="async" referrerpolicy="no-referrer">';
    }

    echo '<span class="wm-video__badge">' . $e($ref->label) . '</span><span class="wm-video__play" aria-hidden="true"></span></button>';
    echo '<noscript><a href="' . $e($ref->page) . '" target="_blank" rel="noopener noreferrer">' . $e(Text::sprintf('PLG_FIELDS_WMARKAMEDIA_WATCH_ON', $ref->label)) . '</a></noscript>';
} elseif ($ref->kind === 'iframe') {
    echo '><iframe src="' . $e($ref->embed) . '" title="' . $e($title) . '" loading="lazy" allowfullscreen'
        . ' allow="encrypted-media; picture-in-picture; fullscreen; clipboard-write" referrerpolicy="strict-origin-when-cross-origin"></iframe>';
} else {
    // файл
    echo '><video controls preload="metadata" playsinline src="' . $e($ref->embed) . '"></video>';
}

if ($showLnk && $ref->kind === 'iframe') {
    echo '<a class="wm-video__link" href="' . $e($ref->page) . '" target="_blank" rel="noopener noreferrer">' . $e(Text::sprintf('PLG_FIELDS_WMARKAMEDIA_OPEN_IN', $ref->label)) . '</a>';
}

echo '</div>';

// разметка VideoObject — только когда известна картинка (постер сервиса или обложка материала): без неё поисковики её отвергают
if ((bool) $fieldParams->get('jsonld', 1) && $ref->kind !== 'link' && !empty($item->title)) {
    $thumb = $ref->poster;

    if (!$thumb && !empty($item->images)) {
        $imgs  = json_decode((string) $item->images, true);
        $first = \is_array($imgs) ? ($imgs['image_fulltext'] ?? $imgs['image_intro'] ?? '') : '';
        $first = (string) preg_replace('~#.*$~', '', (string) $first);
        $thumb = $first !== '' ? (preg_match('~^https?://~i', $first) ? $first : rtrim(\Joomla\CMS\Uri\Uri::root(), '/') . '/' . ltrim($first, '/')) : null;
    }

    if ($thumb) {
        $date = (string) ($item->publish_up ?? $item->created ?? '');
        $desc = trim(strip_tags((string) ($item->metadesc ?? ''))) ?: $title;
        $ld   = [
            '@context'     => 'https://schema.org',
            '@type'        => 'VideoObject',
            'name'         => $title,
            'description'  => $desc,
            'thumbnailUrl' => [$thumb],
            'uploadDate'   => $date !== '' ? Factory::getDate($date)->toISO8601() : null,
            'embedUrl'     => $ref->kind === 'iframe' ? $ref->embed : null,
            'contentUrl'   => $ref->kind === 'file' ? $ref->embed : null,
        ];

        echo '<script type="application/ld+json">' . json_encode(array_filter($ld, static fn ($v) => $v !== null), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) . '</script>';
    }
}
