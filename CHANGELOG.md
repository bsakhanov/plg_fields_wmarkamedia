# История изменений / Changelog

Формат — [Keep a Changelog](https://keepachangelog.com/ru/1.1.0/), версии — [SemVer](https://semver.org/lang/ru/).

## [1.2.0] — 2026-10-30

### Исправлено / Fixed
- Падение в консоли (`cli/joomla.php`: установка пакета, импорт, задачи планировщика): при подготовке поля «Видео» `Assets::load()` вызывал `getDocument()`, которого у консольного приложения нет (`Call to undefined method ConsoleApplication::getDocument()`). Теперь в консоли ресурсы страницы не подключаются. / Crash in the console (`cli/joomla.php`: package install, import, scheduled tasks): preparing a video field called `getDocument()`, which the console application does not have. The console now skips page assets.

### Изменено / Changed
- Пост Telegram по умолчанию показывается ссылкой «Смотреть на Telegram»: встроенный пост выводится целиком вместе с чужим каналом. Встраивание включается настройкой «Посты Telegram» (в плагине и в поле). / A Telegram post is a link by default (an embedded post shows the whole post with its channel); embedding is the "Telegram posts" setting.
- Казахский интерфейс: «кішірейтілген сурет» вместо «нобай».

### Добавлено / Added
- Постер для сервисов, которые его не отдают (TikTok, Instagram, Reels): изображение материала (копия 720 px, если её можно сделать). / Poster for services that do not provide one: the article image (a 720 px copy when possible).
- Настройка постера «Только изображение материала»: до нажатия нет ни одного запроса к чужому серверу, в том числе к `i.ytimg.com`. / Poster setting "Article image only": no request to any third-party server before the click.

## [1.1.3] — 2026-10-28

### Исправлено / Fixed
- Путь к фото с нулевым байтом (`%00`) вызывал `ValueError` в `realpath()` и обрывал вывод страницы; теперь такой путь отклоняется. / A photo path containing a null byte no longer aborts page rendering.
- Перед уменьшением снимка проверяется доступная память: при нехватке берётся оригинал вместо фатальной ошибки. / Available memory is checked before resizing an image; the original is used instead of a fatal error.


## [1.1.2] — 2026-10-25

### Исправлено / Fixed
- Постер YouTube обрезался снизу в шаблонах с правилом вида `img { height: auto }`: у постера 4:3 высота перебивала рамку 16:9. Селектор постера усилен (`.wm-video .wm-video__btn .wm-video__poster`), добавлено `max-width:none`. / The YouTube poster was cropped at the bottom in templates with an `img { height: auto }` rule; the poster selector is now more specific.

## [1.1.1] — 2026-10-24

### Добавлено / Added
- Сервер обновлений (`updates.xml`): Joomla сообщает о новых версиях в админке. / Update server: Joomla announces new versions in the administrator.
- Репозиторий: сборочный скрипт `build.php`, проверки `tests/run.php` (67 штук), CI на GitHub Actions, шаблоны Issues и Pull Request, README на русском, английском и казахском.

### Изменено / Changed
- Казахский интерфейс: термин «видео» вместо «бейне».

## [1.1.0] — 2026-10-24

### Добавлено / Added
- Фотогалерея: уменьшенные копии снимков (GD, класс `Image` Joomla; кэш в `media/plg_fields_wmarkamedia/cache`), настройки «Размер картинок», «Ширина снимка в сетке» (по умолчанию 640 px), «Ширина снимка в окне просмотра» (1920 px).
- Фотогалерея: режим «Показывать первые N снимков», на последней плитке — «+K»; остальные снимки листаются в окне просмотра.

### Изменено / Changed
- Первый кадр галереи грузится сразу и с высоким приоритетом (`loading="eager"`, `fetchpriority="high"`), остальные — отложенно.
- Крупные кадры и миниатюры создаются порциями (до 8 и до 2 за запрос), чтобы первый читатель не ждал.

## [1.0.0] — 2026-10-23

### Добавлено / Added
- Один плагин, три типа поля: видео (YouTube, Vimeo, VK, Rutube, Facebook, Instagram, TikTok, Telegram, файл), фотогалерея, список ссылок.
- Плеер по нажатию (фасад), окно просмотра фото на `<dialog>`, микроразметка `VideoObject` и `ImageGallery`.
- Языки: en-GB, ru-RU, kk-KZ.
- Скрипт переноса старых полей `tools/migrate-fields.php` (пробный прогон по умолчанию, копия строк и откат).
