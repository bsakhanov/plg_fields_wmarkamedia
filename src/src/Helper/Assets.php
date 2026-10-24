<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  Fields.WmarkaMedia
 *
 * @copyright   (C) 2026 Beibit Sakhanov
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wmarka\Plugin\Fields\WmarkaMedia\Helper;

use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Factory;

\defined('_JEXEC') or die;

/**
 * Подключает один небольшой скрипт и одну таблицу стилей (media/plg_fields_wmarkamedia). Скрипт не обращается к чужим сайтам:
 * плеер создаётся только после нажатия.
 */
final class Assets
{
    private static bool $loaded = false;

    public static function load(): void
    {
        if (self::$loaded) {
            return;
        }

        $document = Factory::getApplication()->getDocument();

        if (!$document instanceof HtmlDocument) {
            return;
        }

        $wa = $document->getWebAssetManager();
        $wa->registerAndUseStyle('plg_fields_wmarkamedia', 'plg_fields_wmarkamedia/wmarkamedia.css');
        $wa->registerAndUseScript('plg_fields_wmarkamedia', 'plg_fields_wmarkamedia/wmarkamedia.js', [], ['defer' => true]);

        self::$loaded = true;
    }
}
