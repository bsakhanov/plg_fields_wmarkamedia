<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  Fields.WmarkaMedia
 *
 * @copyright   (C) 2026 Beibit Sakhanov
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wmarka\Plugin\Fields\WmarkaMedia\Helper;

\defined('_JEXEC') or die;

/**
 * Разобранная ссылка на видео. Все адреса строятся из проверенных частей (идентификаторов), а не из текста пользователя.
 */
final class VideoRef
{
    /**
     * @param   string       $provider   Короткое имя сервиса: youtube, vimeo, rutube, vk, facebook, instagram, tiktok, telegram, file, link
     * @param   string       $label      Название для людей: YouTube, Vimeo…
     * @param   string       $id         Идентификатор ролика (для файла и ссылки — пустая строка)
     * @param   string       $page       Страница ролика на сервисе — для ссылки «Открыть в …» и для noscript
     * @param   string|null  $embed      Адрес встраивания без автозапуска (null — встроить нельзя)
     * @param   string|null  $embedPlay  Адрес встраивания с автозапуском: его открывает нажатие на постер
     * @param   bool         $vertical   Вертикальный ролик (shorts, reels, TikTok)
     * @param   string       $ratio      Соотношение сторон для CSS: 16/9, 9/16, 4/5
     * @param   string|null  $poster     Адрес постера, если сервис его отдаёт без запроса к API
     * @param   string       $kind       iframe — встраивание; file — файл в теге video; link — только ссылка
     */
    public function __construct(
        public readonly string $provider,
        public readonly string $label,
        public readonly string $id,
        public readonly string $page,
        public readonly ?string $embed,
        public readonly ?string $embedPlay,
        public readonly bool $vertical,
        public readonly string $ratio,
        public readonly ?string $poster,
        public readonly string $kind
    ) {
    }
}
