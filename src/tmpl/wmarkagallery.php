<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  Fields.WmarkaMedia
 *
 * Тип поля «Фотогалерея»: повторяемые строки «фото, подпись, источник кадра», вывод сеткой с окном просмотра.
 * Макет можно переопределить: templates/<шаблон>/html/plg_fields_wmarkamedia/wmarkagallery.php
 *
 * @copyright   (C) 2026 Beibit Sakhanov
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Wmarka\Plugin\Fields\WmarkaMedia\Helper\Assets;
use Wmarka\Plugin\Fields\WmarkaMedia\Helper\GalleryHelper;

$lightboxOn = (bool) $fieldParams->get('lightbox', 1);
$previewN   = $lightboxOn ? max(0, (int) $fieldParams->get('preview_count', 0)) : 0;
$rows       = GalleryHelper::rows($field->value, [
    'thumbs'     => (string) $fieldParams->get('thumbs', 'auto') !== 'none',
    'thumbWidth' => (int) $fieldParams->get('thumb_width', 640),
    'fullWidth'  => (int) $fieldParams->get('full_width', 1920),
    'visible'    => $previewN,
]);

if (!$rows) {
    return;
}

$this->loadLanguage();
Assets::load();

$e        = static fn ($s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$cols     = max(1, min(6, (int) $fieldParams->get('columns', 3)));
$lightbox = (bool) $fieldParams->get('lightbox', 1);
$showCap  = (bool) $fieldParams->get('show_caption', 1);
$showCred = (bool) $fieldParams->get('show_credit', 1);
$extra    = trim((string) $fieldParams->get('css_class', ''));
$total    = \count($rows);

echo '<div class="wm-gallery' . ($extra !== '' ? ' ' . $e($extra) : '') . '" style="--wm-cols:' . $cols . '"'
    . ($lightbox ? ' data-wm-gallery data-l-close="' . $e(Text::_('PLG_FIELDS_WMARKAMEDIA_CLOSE')) . '" data-l-prev="' . $e(Text::_('PLG_FIELDS_WMARKAMEDIA_PREV'))
        . '" data-l-next="' . $e(Text::_('PLG_FIELDS_WMARKAMEDIA_NEXT')) . '" data-l-of="' . $e(Text::_('PLG_FIELDS_WMARKAMEDIA_PHOTO_OF')) . '"' : '') . '>';

$preview = $lightbox ? max(0, (int) $fieldParams->get('preview_count', 0)) : 0;   // показывать первые N снимков, остальные — в окне просмотра

foreach ($rows as $i => $r) {
    $label = Text::sprintf('PLG_FIELDS_WMARKAMEDIA_PHOTO_OF', $i + 1, $total);
    $cap   = trim($r['caption'] . ($showCred && $r['credit'] !== '' ? ' · ' . $r['credit'] : ''));

    if ($preview > 0 && $i >= $preview) {
        // скрытый снимок: в страницу не попадает ни одной картинки, но листается в окне просмотра
        echo '<button type="button" class="wm-gallery__btn" hidden data-full="' . $e($r['full']) . '" data-caption="' . $e($cap) . '" data-alt="' . $e($r['alt']) . '" aria-label="' . $e($label) . '"></button>';

        continue;
    }

    $img = '<img src="' . $e($r['thumb']) . '" alt="' . $e($r['alt']) . '"'
        . ($r['tw'] > 0 && $r['th'] > 0 ? ' width="' . $r['tw'] . '" height="' . $r['th'] . '"' : '')
        . ($i === 0 ? ' loading="eager" fetchpriority="high"' : ' loading="lazy" decoding="async"') . '>';   // первый кадр — самый вероятный LCP: грузится сразу

    echo '<figure class="wm-gallery__item">';

    if ($lightbox) {
        $more = ($preview > 0 && $i === $preview - 1 && $total > $preview) ? '<span class="wm-gallery__more" aria-hidden="true">+' . ($total - $preview) . '</span>' : '';
        echo '<button type="button" class="wm-gallery__btn" data-full="' . $e($r['full']) . '" data-caption="' . $e($cap) . '" aria-label="' . $e($label) . '">' . $img . $more . '</button>';
    } else {
        echo $img;
    }

    if (($showCap && $r['caption'] !== '') || ($showCred && $r['credit'] !== '')) {
        echo '<figcaption>' . ($showCap && $r['caption'] !== '' ? '<span class="wm-gallery__cap">' . $e($r['caption']) . '</span>' : '')
            . ($showCred && $r['credit'] !== '' ? ' <span class="wm-gallery__credit">' . $e($r['credit']) . '</span>' : '') . '</figcaption>';
    }

    echo '</figure>';
}

echo '</div>';

if ((bool) $fieldParams->get('jsonld', 1) && !empty($item->title)) {
    $images = [];

    foreach ($rows as $r) {
        $images[] = array_filter([
            '@type'      => 'ImageObject',
            'contentUrl' => $r['abs'],
            'caption'    => $r['caption'] ?: null,
            'creditText' => $r['credit'] ?: null,
        ]);
    }

    echo '<script type="application/ld+json">' . json_encode(
        ['@context' => 'https://schema.org', '@type' => 'ImageGallery', 'name' => (string) $item->title, 'image' => $images],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG
    ) . '</script>';
}
