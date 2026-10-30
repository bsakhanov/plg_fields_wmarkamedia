<?php

declare(strict_types=1);

/**
 * Проверки без Joomla и без PHPUnit: php tests/run.php
 * Помощники плагина (разбор ссылок, галерея, пути картинок), файлы языков, манифест.
 * Код возврата 0 — всё в порядке, 1 — есть ошибки.
 */

namespace Joomla\CMS\Uri {
    /** Заглушка класса Joomla: нужна только для адресов картинок */
    final class Uri
    {
        public static function root($pathOnly = false)
        {
            return $pathOnly ? '' : 'https://example.org/';
        }
    }
}

namespace Joomla\CMS {
    /** Заглушка класса Joomla: приложение подменяется в проверках подключения ресурсов */
    final class Factory
    {
        public static $app;

        public static function getApplication()
        {
            return self::$app;
        }
    }
}

namespace Joomla\CMS\Document {
    /** Заглушка документа: запоминает, какие ресурсы подключили */
    class HtmlDocument
    {
        public array $calls = [];

        public function getWebAssetManager()
        {
            return $this;
        }

        public function registerAndUseStyle(string $name, string $uri = ''): static
        {
            $this->calls[] = 'style:' . $name;

            return $this;
        }

        public function registerAndUseScript(string $name, string $uri = '', array $options = [], array $attributes = []): static
        {
            $this->calls[] = 'script:' . $name;

            return $this;
        }
    }
}

namespace {
    use Joomla\CMS\Document\HtmlDocument;
    use Joomla\CMS\Factory;
    use Wmarka\Plugin\Fields\WmarkaMedia\Helper\Assets;
    use Wmarka\Plugin\Fields\WmarkaMedia\Helper\GalleryHelper;
    use Wmarka\Plugin\Fields\WmarkaMedia\Helper\ImageHelper;
    use Wmarka\Plugin\Fields\WmarkaMedia\Helper\LinksHelper;
    use Wmarka\Plugin\Fields\WmarkaMedia\Helper\VideoHelper;

    define('_JEXEC', 1);

    $src = dirname(__DIR__) . '/src';
    $tmp = sys_get_temp_dir() . '/wmarkamedia-tests-' . getmypid();
    mkdir($tmp . '/images', 0777, true);
    define('JPATH_ROOT', $tmp);

    foreach (['VideoRef', 'VideoHelper', 'LinksHelper', 'GalleryHelper', 'ImageHelper', 'Assets'] as $class) {
        require $src . '/src/Helper/' . $class . '.php';
    }

    $total  = 0;
    $failed = [];

    function check(bool $condition, string $name): void
    {
        global $total, $failed;
        $total++;

        if (!$condition) {
            $failed[] = $name;
        }
    }

    // ---------------------------------------------------------------- видео
    $video = [
        // вход                                                                    сервис      вертикальное  вид
        ['Q-D5ZLBz1ow?si=H_QXgFzjh2zPADCJ',                                        'youtube',   false, 'iframe'],
        ['AXu3RQQmHOQ',                                                            'youtube',   false, 'iframe'],
        ['JdLo_Fx_zQo?feature=share',                                              'youtube',   false, 'iframe'],
        ['https://youtu.be/W3Hx8ZMz7no?si=f3atzPZTEGyKs85V&t=1m30s',               'youtube',   false, 'iframe'],
        ['https://www.youtube.com/live/ayzJK16AqcM?si=KHB9nZBQ5uu9HZsw',           'youtube',   false, 'iframe'],
        ['https://www.youtube.com/shorts/abcdefghijk',                             'youtube',   true,  'iframe'],
        ['https://www.youtube.com/watch?v=dQw4w9WgXcQ&feature=share',              'youtube',   false, 'iframe'],
        ['https://vm.tiktok.com/ZMDJuMNCu/',                                       'tiktok',    true,  'link'],
        ['https://www.tiktok.com/@user/video/7000000000000000000?lang=ru',         'tiktok',    true,  'iframe'],
        ['https://www.instagram.com/reel/CxYz123_AB/?utm_source=ig',               'instagram', true,  'iframe'],
        ['https://t.me/example_channel/12345',                                     'telegram',  false, 'link'],
        ['https://vimeo.com/76979871',                                             'vimeo',     false, 'iframe'],
        ['https://rutube.ru/video/0123456789abcdef0123456789abcdef/',              'rutube',    false, 'iframe'],
        ['https://vk.com/video-22822305_456239018',                                'vk',        false, 'iframe'],
        ['https://www.facebook.com/example/videos/123456789/',                     'facebook',  false, 'iframe'],
        ['https://example.com/clip.mp4',                                           'file',      false, 'file'],
        ['https://example.com/page',                                               'link',      false, 'link'],
        ['<iframe width="560" src="https://www.youtube.com/embed/dQw4w9WgXcQ?si=abc"></iframe>', 'youtube', false, 'iframe'],
        ['<blockquote class="tiktok-embed" cite="https://www.tiktok.com/@u/video/7000000000000000001">x</blockquote>', 'tiktok', true, 'iframe'],
    ];

    foreach ($video as [$input, $provider, $vertical, $kind]) {
        $ref = VideoHelper::parse($input);
        check($ref !== null && $ref->provider === $provider && $ref->vertical === $vertical && $ref->kind === $kind, 'видео: ' . substr($input, 0, 60));
    }

    foreach (['', '   ', 'не ссылка', 'javascript:alert(1)'] as $junk) {
        check(VideoHelper::parse($junk) === null, 'видео: мусор отвергается «' . $junk . '»');
    }

    $yt = VideoHelper::parse('https://youtu.be/W3Hx8ZMz7no?t=1m30s');
    check($yt !== null && str_contains((string) $yt->embed, 'start=90') && str_contains((string) $yt->embed, 'youtube-nocookie.com'), 'видео: время старта и nocookie');
    check($yt !== null && str_ends_with((string) $yt->embedPlay, '&autoplay=1'), 'видео: адрес с автозапуском');
    $yt2 = VideoHelper::parse('W3Hx8ZMz7no', false);
    check($yt2 !== null && str_contains((string) $yt2->embed, 'www.youtube.com/embed/'), 'видео: без nocookie по настройке');

    $tgLink = VideoHelper::parse('https://t.me/example_channel/12345');
    check($tgLink !== null && $tgLink->kind === 'link' && $tgLink->embed === null && $tgLink->page === 'https://t.me/example_channel/12345', 'видео: Telegram по умолчанию — только ссылка');
    $tgEmbed = VideoHelper::parse('https://t.me/example_channel/12345', true, true);
    check($tgEmbed !== null && $tgEmbed->kind === 'iframe' && str_contains((string) $tgEmbed->embed, 'embed=1') && $tgEmbed->ratio === '4/5', 'видео: Telegram встраивается по настройке');

    // ---------------------------------------------------------------- ссылки
    $links = LinksHelper::parse("https://t.me/example | Обсудить в Telegram\njavascript:alert(1)\nне ссылка\nhttps://vm.tiktok.com/ZMDFkCHXP/Когда в Астане исчезнут запашки?\n\nhttps://example.org/x");
    check(count($links) === 3, 'ссылки: три настоящие строки из шести');
    check($links[0]['name'] === 'Telegram' && $links[0]['title'] === 'Обсудить в Telegram', 'ссылки: заголовок после «|»');
    check($links[1]['url'] === 'https://vm.tiktok.com/ZMDFkCHXP/' && $links[1]['title'] === 'Когда в Астане исчезнут запашки?', 'ссылки: заголовок, приклеенный к адресу');
    check($links[2]['name'] === 'example.org', 'ссылки: неизвестный сервис называется по узлу');

    // ---------------------------------------------------------------- галерея
    $new = json_encode(['row0' => ['image' => ['imagefile' => 'images/a b.jpg#joomlaImage://local-images/a b.jpg?width=800&height=600', 'alt_text' => 'Альт'], 'caption' => 'Подпись', 'credit' => 'Фото: автор'], 'row1' => ['image' => ['imagefile' => 'javascript:alert(1)', 'alt_text' => '']]], JSON_UNESCAPED_UNICODE);
    $rows = GalleryHelper::rows($new);
    check(count($rows) === 1, 'галерея: небезопасная схема отброшена');
    check($rows[0]['src'] === '/images/a%20b.jpg' && $rows[0]['w'] === 800 && $rows[0]['h'] === 600, 'галерея: адрес и размеры из значения поля');
    check($rows[0]['alt'] === 'Альт' && $rows[0]['caption'] === 'Подпись' && $rows[0]['credit'] === 'Фото: автор', 'галерея: alt, подпись, источник');

    $old = json_encode(['row0' => ['field15' => ['imagefile' => 'images/x.jpg', 'alt_text' => ''], 'field14' => 'Старая подпись'], 'row1' => ['field15' => '{"imagefile":"images/y.jpg","alt_text":""}', 'field14' => 'Вторая']]);
    $rows = GalleryHelper::rows($old);
    check(count($rows) === 2 && $rows[0]['caption'] === 'Старая подпись' && $rows[1]['caption'] === 'Вторая', 'галерея: старый вид строк (field14, field15)');
    check(GalleryHelper::rows('') === [] && GalleryHelper::rows('не json') === [] && GalleryHelper::rows('[]') === [], 'галерея: пустое и повреждённое значение');

    // ---------------------------------------------------------------- пути картинок
    file_put_contents($tmp . '/secret.txt', 'секрет');
    file_put_contents($tmp . '/images/note.txt', 'не картинка');

    if (extension_loaded('gd')) {
        $im = imagecreatetruecolor(200, 100);
        imagepng($im, $tmp . '/images/small.png');
        imagedestroy($im);
        $r = ImageHelper::resized('images/small.png', 640);
        check($r !== null && $r['url'] === null && $r['w'] === 200 && $r['h'] === 100, 'картинки: лёгкий оригинал не пережимается');
    }

    foreach (['../secret.txt', 'images/../secret.txt', 'images/../../secret.txt', '/etc/passwd', 'images/нет-такого.jpg', 'images/note.txt', 'secret.txt', "images/a\0b.jpg"] as $bad) {
        check(ImageHelper::resized($bad, 640) === null, 'картинки: отказ для «' . $bad . '»');
    }

    // ---------------------------------------------------------------- манифест и языки
    $manifest = simplexml_load_file($src . '/wmarkamedia.xml');
    check($manifest !== false, 'манифест: корректный XML');
    check((bool) preg_match('~^\d+\.\d+\.\d+$~', (string) $manifest->version), 'манифест: версия вида 1.2.3');
    check((string) $manifest['group'] === 'fields' && (string) $manifest['type'] === 'plugin', 'манифест: плагин группы fields');
    check((bool) preg_match('~^https://raw\.githubusercontent\.com/[^/]+/[^/]+/main/updates\.xml$~', (string) $manifest->updateservers->server), 'манифест: адрес сервера обновлений');

    foreach (glob($src . '/params/*.xml') as $xml) {
        check(@simplexml_load_file($xml) !== false, 'XML: ' . basename($xml));
    }

    foreach (['tmpl', 'params', 'language', 'media', 'services', 'src'] as $dir) {
        check(is_dir($src . '/' . $dir), 'папка src/' . $dir);
    }

    $keys = [];

    foreach (['en-GB', 'ru-RU', 'kk-KZ'] as $tag) {
        foreach (['plg_fields_wmarkamedia.ini', 'plg_fields_wmarkamedia.sys.ini'] as $name) {
            $parsed = parse_ini_file($src . '/language/' . $tag . '/' . $name, false, INI_SCANNER_RAW);
            check($parsed !== false, "язык {$tag}/{$name}: читается");
            $keys[$name][$tag] = array_keys($parsed ?: []);
        }
    }

    foreach ($keys as $name => $byTag) {
        check($byTag['en-GB'] === $byTag['ru-RU'] || !array_diff($byTag['en-GB'], $byTag['ru-RU']) && !array_diff($byTag['ru-RU'], $byTag['en-GB']), "язык {$name}: ключи ru-RU совпадают с en-GB");
        check(!array_diff($byTag['en-GB'], $byTag['kk-KZ']) && !array_diff($byTag['kk-KZ'], $byTag['en-GB']), "язык {$name}: ключи kk-KZ совпадают с en-GB");
    }

    $used = [];

    foreach (array_merge(glob($src . '/tmpl/*.php'), glob($src . '/src/Extension/*.php'), glob($src . '/params/*.xml'), [$src . '/wmarkamedia.xml']) as $file) {
        preg_match_all('/PLG_FIELDS_WMARKAMEDIA_[A-Z_]+/', (string) file_get_contents($file), $m);
        $used = array_merge($used, $m[0]);
    }

    $known = array_merge($keys['plg_fields_wmarkamedia.ini']['en-GB'], $keys['plg_fields_wmarkamedia.sys.ini']['en-GB']);
    check(!array_diff(array_unique($used), $known), 'язык: все ключи из кода определены (' . implode(', ', array_diff(array_unique($used), $known)) . ')');

    // ---------------------------------------------------------------- подключение ресурсов
    Factory::$app = new \stdClass();   // консоль: у приложения нет getDocument()
    $threw        = false;

    try {
        Assets::load();
    } catch (\Throwable $e) {
        $threw = true;
    }

    check(!$threw, 'ресурсы: в консоли без документа ошибки нет');

    $doc          = new HtmlDocument();
    Factory::$app = new class ($doc) {
        public function __construct(private HtmlDocument $doc)
        {
        }

        public function getDocument()
        {
            return $this->doc;
        }
    };
    Assets::load();
    Assets::load();
    check($doc->calls === ['style:plg_fields_wmarkamedia', 'script:plg_fields_wmarkamedia'], 'ресурсы: на странице подключаются один раз');

    // ---------------------------------------------------------------- итог
    @unlink($tmp . '/secret.txt');
    @unlink($tmp . '/images/note.txt');
    @unlink($tmp . '/images/small.png');
    @rmdir($tmp . '/images');
    @rmdir($tmp);

    if ($failed) {
        fwrite(STDERR, 'ПРОВАЛЕНО ' . count($failed) . ' из ' . $total . ":\n - " . implode("\n - ", $failed) . "\n");
        exit(1);
    }

    echo "Всё в порядке: {$total} проверок\n";
}
