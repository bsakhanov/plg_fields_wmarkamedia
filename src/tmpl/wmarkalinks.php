<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  Fields.WmarkaMedia
 *
 * Тип поля «Список ссылок»: по одной ссылке в строке; название сервиса определяется по адресу.
 * Макет можно переопределить: templates/<шаблон>/html/plg_fields_wmarkamedia/wmarkalinks.php
 *
 * @copyright   (C) 2026 Beibit Sakhanov
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Wmarka\Plugin\Fields\WmarkaMedia\Helper\Assets;
use Wmarka\Plugin\Fields\WmarkaMedia\Helper\LinksHelper;

$raw   = \is_array($field->value) ? implode("\n", $field->value) : (string) $field->value;
$links = LinksHelper::parse($raw);

if (!$links) {
    return;
}

$this->loadLanguage();
Assets::load();

$e       = static fn ($s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$title   = trim((string) $fieldParams->get('block_title', ''));
$style   = $fieldParams->get('style', 'list') === 'buttons' ? 'buttons' : 'list';
$newTab  = (bool) $fieldParams->get('new_tab', 1);
$extra   = trim((string) $fieldParams->get('css_class', ''));
$rel     = $newTab ? 'noopener noreferrer nofollow' : 'nofollow';

echo '<div class="wm-links wm-links--' . $style . ($extra !== '' ? ' ' . $e($extra) : '') . '">';

if ($title !== '') {
    echo '<p class="wm-links__title">' . $e($title) . '</p>';
}

echo '<ul class="wm-links__list">';

foreach ($links as $l) {
    echo '<li><a class="wm-links__a" style="--wm-accent:' . $e($l['color']) . '" href="' . $e($l['url']) . '"' . ($newTab ? ' target="_blank"' : '') . ' rel="' . $rel . '">'
        . '<span class="wm-links__dot" aria-hidden="true"></span><span class="wm-links__name">' . $e($l['title'] !== '' ? $l['title'] : Text::sprintf('PLG_FIELDS_WMARKAMEDIA_OPEN_IN', $l['name'])) . '</span>'
        . ($l['title'] !== '' ? '<span class="wm-links__src">' . $e($l['name']) . '</span>' : '') . '</a></li>';
}

echo '</ul></div>';
