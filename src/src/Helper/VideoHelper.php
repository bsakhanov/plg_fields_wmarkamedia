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
 * Разбор ссылок на видео. Можно пользоваться и вне плагина — в макетах модулей:
 *
 *   use Wmarka\Plugin\Fields\WmarkaMedia\Helper\VideoHelper;
 *   $ref = VideoHelper::parse($value);   // VideoRef или null
 *
 * Принимает: ссылку, «голый» идентификатор YouTube (в том числе с хвостом «?si=…»), код встраивания (iframe, blockquote TikTok),
 * адрес файла .mp4/.webm.
 */
final class VideoHelper
{
    /**
     * @param   string  $input          ссылка, код встраивания, «голый» идентификатор YouTube или адрес файла
     * @param   bool    $nocookie       YouTube через youtube-nocookie.com
     * @param   bool    $telegramEmbed  встраивать посты Telegram; по умолчанию нет: встроенный пост выводится целиком вместе с чужим
     *                                  каналом, поэтому для t.me возвращается только ссылка (kind = link)
     */
    public static function parse(string $input, bool $nocookie = true, bool $telegramEmbed = false): ?VideoRef
    {
        $url = self::normalize($input);

        if ($url === '') {
            return null;
        }

        return self::matchYoutube($url, $nocookie)
            ?? self::matchVimeo($url)
            ?? self::matchRutube($url)
            ?? self::matchVk($url)
            ?? self::matchFacebook($url)
            ?? self::matchInstagram($url)
            ?? self::matchTiktok($url)
            ?? self::matchTelegram($url, $telegramEmbed)
            ?? self::matchFile($url)
            ?? self::matchLink($url);
    }

    /** Достаёт адрес из кода встраивания и очищает строку. */
    private static function normalize(string $input): string
    {
        $input = trim($input);

        if ($input === '') {
            return '';
        }

        if (stripos($input, '<iframe') !== false && preg_match('~\ssrc\s*=\s*(["\'])(.*?)\1~is', $input, $m)) {
            $input = html_entity_decode($m[2], ENT_QUOTES, 'UTF-8');
        } elseif (stripos($input, '<blockquote') !== false && preg_match('~\scite\s*=\s*(["\'])(.*?)\1~is', $input, $m)) {
            $input = html_entity_decode($m[2], ENT_QUOTES, 'UTF-8');
        } else {
            $input = strip_tags($input);
        }

        $input = trim($input);

        if (str_starts_with($input, '//')) {
            $input = 'https:' . $input;
        }

        return $input;
    }

    private static function matchYoutube(string $url, bool $nocookie): ?VideoRef
    {
        $id       = null;
        $vertical = false;

        if (preg_match('~(?:youtu\.be/|youtube(?:-nocookie)?\.com/(?:watch\?(?:[^#\s]*&)?v=|embed/|shorts/|live/|v/))([A-Za-z0-9_-]{11})(?![A-Za-z0-9_-])~i', $url, $m)) {
            $id       = $m[1];
            $vertical = stripos($url, '/shorts/') !== false;
        } elseif (preg_match('~^([A-Za-z0-9_-]{11})(?:[?&#].*)?$~', $url, $m)) {
            // значение старого поля: «голый» идентификатор, иногда с хвостом ?si=… или ?feature=share
            $id = $m[1];
        }

        if ($id === null) {
            return null;
        }

        $start = 0;

        if (preg_match('~[?&#](?:t|start)=([0-9hms]+)~i', $url, $t)) {
            $v = strtolower($t[1]);

            if (ctype_digit($v)) {
                $start = (int) $v;
            } elseif (preg_match('~^(?:(\d+)h)?(?:(\d+)m)?(?:(\d+)s)?$~', $v, $p)) {
                $start = ((int) ($p[1] ?? 0)) * 3600 + ((int) ($p[2] ?? 0)) * 60 + (int) ($p[3] ?? 0);
            }
        }

        $host  = $nocookie ? 'www.youtube-nocookie.com' : 'www.youtube.com';
        $query = 'rel=0&playsinline=1' . ($start > 0 ? '&start=' . $start : '');
        $embed = 'https://' . $host . '/embed/' . $id . '?' . $query;

        return new VideoRef(
            'youtube',
            'YouTube',
            $id,
            'https://www.youtube.com/' . ($vertical ? 'shorts/' : 'watch?v=') . $id,
            $embed,
            $embed . '&autoplay=1',
            $vertical,
            $vertical ? '9/16' : '16/9',
            'https://i.ytimg.com/vi/' . $id . '/hqdefault.jpg',
            'iframe'
        );
    }

    private static function matchVimeo(string $url): ?VideoRef
    {
        if (!preg_match('~vimeo\.com/(?:video/|channels/[^/]+/|groups/[^/]+/videos/|album/\d+/video/)?(\d{6,})(?:/([a-f0-9]{6,}))?~i', $url, $m)) {
            return null;
        }

        $id   = $m[1];
        $hash = $m[2] ?? '';

        if ($hash === '' && preg_match('~[?&]h=([a-f0-9]+)~i', $url, $h)) {
            $hash = $h[1];
        }

        $embed = 'https://player.vimeo.com/video/' . $id . '?dnt=1' . ($hash !== '' ? '&h=' . $hash : '');

        return new VideoRef('vimeo', 'Vimeo', $id, 'https://vimeo.com/' . $id . ($hash !== '' ? '/' . $hash : ''), $embed, $embed . '&autoplay=1', false, '16/9', null, 'iframe');
    }

    private static function matchRutube(string $url): ?VideoRef
    {
        if (!preg_match('~rutube\.ru/(video|play/embed|shorts)/([a-f0-9]{32})~i', $url, $m)) {
            return null;
        }

        $id       = strtolower($m[2]);
        $vertical = strtolower($m[1]) === 'shorts';
        $embed    = 'https://rutube.ru/play/embed/' . $id;

        return new VideoRef('rutube', 'Rutube', $id, 'https://rutube.ru/video/' . $id . '/', $embed, $embed . '?autoStart=true', $vertical, $vertical ? '9/16' : '16/9', null, 'iframe');
    }

    private static function matchVk(string $url): ?VideoRef
    {
        $oid = $vid = null;

        if (preg_match('~(?:vk\.com|vkvideo\.ru)/(?:video|clip)(-?\d+)_(\d+)~i', $url, $m)) {
            [, $oid, $vid] = $m;
        } elseif (preg_match('~video_ext\.php\?(?:[^#]*&)?oid=(-?\d+)&(?:[^#]*&)?id=(\d+)~i', $url, $m)) {
            [, $oid, $vid] = $m;
        }

        if ($oid === null) {
            return null;
        }

        $hash     = preg_match('~[?&]hash=([a-f0-9]+)~i', $url, $h) ? '&hash=' . $h[1] : '';
        $vertical = (bool) preg_match('~/clip-?\d+_\d+~i', $url);
        $embed    = 'https://vk.com/video_ext.php?oid=' . $oid . '&id=' . $vid . '&hd=2' . $hash;

        return new VideoRef('vk', 'VK', $oid . '_' . $vid, 'https://vk.com/video' . $oid . '_' . $vid, $embed, $embed . '&autoplay=1', $vertical, $vertical ? '9/16' : '16/9', null, 'iframe');
    }

    private static function matchFacebook(string $url): ?VideoRef
    {
        if (!preg_match('~^https?://(?:[a-z0-9-]+\.)?(?:facebook\.com|fb\.watch|fb\.com)/~i', $url)) {
            return null;
        }

        if (!preg_match('~/videos?/|/reel/|/watch|fb\.watch/|/share/[vr]/~i', $url)) {
            return null;
        }

        $vertical = (bool) preg_match('~/reel/|/share/r/~i', $url);
        $embed    = 'https://www.facebook.com/plugins/video.php?show_text=false&t=0&href=' . rawurlencode($url);

        return new VideoRef('facebook', 'Facebook', '', $url, $embed, $embed . '&autoplay=true', $vertical, $vertical ? '9/16' : '16/9', null, 'iframe');
    }

    private static function matchInstagram(string $url): ?VideoRef
    {
        if (!preg_match('~instagram\.com/(?:[^/?#]+/)?(reels?|p|tv)/([A-Za-z0-9_-]+)~i', $url, $m)) {
            return null;
        }

        $type     = strtolower($m[1]) === 'reels' ? 'reel' : strtolower($m[1]);
        $code     = $m[2];
        $vertical = $type === 'reel' || $type === 'tv';
        $embed    = 'https://www.instagram.com/' . $type . '/' . $code . '/embed/';

        return new VideoRef('instagram', 'Instagram', $code, 'https://www.instagram.com/' . $type . '/' . $code . '/', $embed, $embed, $vertical, $vertical ? '9/16' : '4/5', null, 'iframe');
    }

    private static function matchTiktok(string $url): ?VideoRef
    {
        if (!preg_match('~(?:^|[/.])tiktok\.com/~i', $url)) {
            return null;
        }

        if (preg_match('~tiktok\.com/(?:@[^/?#]+/video|embed/v2|v)/(\d{10,})~i', $url, $m)) {
            $id    = $m[1];
            $embed = 'https://www.tiktok.com/embed/v2/' . $id;
            $page  = preg_match('~^https?://[^\s"<>]+$~i', $url) && str_contains($url, '/@') ? strtok($url, '?') : $embed;

            return new VideoRef('tiktok', 'TikTok', $id, (string) $page, $embed, $embed, true, '9/16', null, 'iframe');
        }

        // короткие ссылки (vm.tiktok.com/…, tiktok.com/t/…) без запроса к сервису в номер ролика не превратить — остаётся ссылка
        return preg_match('~^https?://[^\s"<>]+$~i', $url) ? new VideoRef('tiktok', 'TikTok', '', $url, null, null, true, '9/16', null, 'link') : null;
    }

    private static function matchTelegram(string $url, bool $embedPost): ?VideoRef
    {
        if (!preg_match('~(?:^|[/.])(?:t|telegram)\.me/([A-Za-z0-9_]{4,})/(\d+)~i', $url, $m)) {
            return null;
        }

        $page = 'https://t.me/' . $m[1] . '/' . $m[2];

        // встроенный пост Telegram показывает весь чужой пост вместе с каналом, поэтому по умолчанию — только ссылка
        if (!$embedPost) {
            return new VideoRef('telegram', 'Telegram', $m[1] . '/' . $m[2], $page, null, null, false, '4/5', null, 'link');
        }

        $embed = $page . '?embed=1&mode=tme';

        return new VideoRef('telegram', 'Telegram', $m[1] . '/' . $m[2], $page, $embed, $embed, false, '4/5', null, 'iframe');
    }

    private static function matchFile(string $url): ?VideoRef
    {
        if (!preg_match('~^(?:https?://[^\s"<>]+|/[^\s"<>]+)\.(?:mp4|webm|ogv|mov|m4v)(?:\?[^\s"<>]*)?$~i', $url)) {
            return null;
        }

        return new VideoRef('file', 'Video', '', $url, $url, $url, false, '16/9', null, 'file');
    }

    private static function matchLink(string $url): ?VideoRef
    {
        if (!preg_match('~^https?://[^\s"<>]+$~i', $url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        $host = (string) parse_url($url, PHP_URL_HOST);

        return new VideoRef('link', preg_replace('~^www\.~i', '', $host) ?: 'link', '', $url, null, null, false, '16/9', null, 'link');
    }
}
