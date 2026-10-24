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
 * Список ссылок: одна в строке, название сервиса определяется по адресу.
 */
final class LinksHelper
{
    /** узел адреса → [название, цвет акцента] */
    private const HOSTS = [
        't.me'            => ['Telegram', '#229ED9'],
        'telegram.me'     => ['Telegram', '#229ED9'],
        'tiktok.com'      => ['TikTok', '#111111'],
        'youtube.com'     => ['YouTube', '#FF0000'],
        'youtu.be'        => ['YouTube', '#FF0000'],
        'instagram.com'   => ['Instagram', '#E4405F'],
        'facebook.com'    => ['Facebook', '#1877F2'],
        'fb.com'          => ['Facebook', '#1877F2'],
        'fb.watch'        => ['Facebook', '#1877F2'],
        'twitter.com'     => ['X', '#111111'],
        'x.com'           => ['X', '#111111'],
        'vk.com'          => ['VK', '#0077FF'],
        'vkvideo.ru'      => ['VK', '#0077FF'],
        'ok.ru'           => ['Одноклассники', '#EE8208'],
        'wa.me'           => ['WhatsApp', '#25D366'],
        'whatsapp.com'    => ['WhatsApp', '#25D366'],
        'api.whatsapp.com' => ['WhatsApp', '#25D366'],
        'linkedin.com'    => ['LinkedIn', '#0A66C2'],
        'threads.net'     => ['Threads', '#111111'],
        'rutube.ru'       => ['Rutube', '#14191F'],
        'dzen.ru'         => ['Дзен', '#111111'],
        'news.google.com' => ['Google Новости', '#4285F4'],
    ];

    /**
     * Разбирает строки вида «ссылка», «ссылка | заголовок» и «ссылка заголовок» (редакторы вклеивают заголовок прямо за ссылкой).
     *
     * @return  array<int, array{url:string, name:string, title:string, color:string}>
     */
    public static function parse(string $raw): array
    {
        $out = [];

        foreach (preg_split('~\R~u', $raw) ?: [] as $line) {
            $line  = trim(strip_tags($line));
            $title = '';

            if ($line === '') {
                continue;
            }

            if (str_contains($line, '|')) {
                [$line, $title] = array_map('trim', explode('|', $line, 2));
            }

            // адрес — это только допустимые в нём знаки; всё, что идёт после (кириллица, пробел), — заголовок
            if (!preg_match('~^(https?://[A-Za-z0-9\-._\~:/?#\[\]@!$&\'()*+,;=%]+)(.*)$~u', $line, $m)) {
                continue;
            }

            $url  = $m[1];
            $rest = trim($m[2]);

            if ($title === '' && $rest !== '') {
                $title = $rest;
            }

            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                continue;
            }

            $host = strtolower((string) parse_url($url, PHP_URL_HOST));
            $host = (string) preg_replace('~^(?:www|m|mobile|vm|vt)\.~', '', $host);
            $hit  = self::HOSTS[$host] ?? null;

            if ($hit === null) {
                foreach (self::HOSTS as $known => $info) {
                    if (str_ends_with($host, '.' . $known)) {
                        $hit = $info;

                        break;
                    }
                }
            }

            $out[] = ['url' => $url, 'name' => $hit[0] ?? $host, 'title' => $title, 'color' => $hit[1] ?? '#5b6670'];
        }

        return $out;
    }
}
