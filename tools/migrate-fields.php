#!/usr/bin/env php
<?php
/**
 * Перенос пользовательских полей на плагин Wmarka Media (plg_fields_wmarkamedia).
 *
 * Что делает:
 *   --video                 поля типов «youtube» (Joomill) и «verticalvideo» → тип «wmarkavideo».
 *                           Значения не меняются: плагин читает «голые» идентификаторы, идентификаторы с «?si=…», ссылки и коды встраивания.
 *   --links=имя1,имя2       поля-текстовые области со ссылками (например, «social») → тип «wmarkalinks»; значения не меняются.
 *   --galleries=13,19       поля-подформы с фотографиями (номера полей) → тип «wmarkagallery»; значения переписываются из вида
 *                           {"row0":{"field15":{…},"field14":"подпись"}} в вид {"row0":{"image":{…},"caption":"…","credit":""}}.
 *   --trash-subfields       отправить в корзину вспомогательные поля старых подформ (те, что «только для подформ»).
 *   --purge-empty           удалить пустые значения галерей («[]» или пустая строка).
 *
 * По умолчанию — пробный прогон: ничего не пишется, печатается, что будет сделано. Запись — только с --apply.
 * Перед записью в текущую папку кладётся копия затрагиваемых строк (migrate-fields-backup-ГГГГММДД-ЧЧММСС.json);
 * вернуть всё назад: php migrate-fields.php --rollback=файл.json
 *
 * Запуск: скрипт лежит в корне сайта Joomla (рядом с configuration.php) или указывается --base=/путь/к/сайту.
 * Перед --apply — резервная копия базы. Проверен на тестовой Joomla 6.2.0 с воспроизведённой структурой полей: пробный прогон, запись, страница, форма в админке, откат.
 *
 * @copyright   (C) 2026 Beibit Sakhanov
 * @license     GNU General Public License version 2 or later
 */

const _JEXEC = 1;

if (PHP_SAPI !== 'cli') {
    exit("Скрипт запускается только из командной строки.\n");
}

$opts = getopt('', ['apply', 'video', 'links:', 'galleries:', 'trash-subfields', 'purge-empty', 'rollback:', 'base:', 'help']);

if (isset($opts['help']) || !$opts) {
    echo "Использование: php migrate-fields.php [--base=/путь] [--video] [--links=social] [--galleries=13,19] [--trash-subfields] [--purge-empty] [--apply]\n"
        . "              php migrate-fields.php --rollback=migrate-fields-backup-….json\n";
    exit(0);
}

$base = rtrim((string) ($opts['base'] ?? getcwd()), '/');

if (!is_file($base . '/configuration.php') || !is_file($base . '/includes/defines.php')) {
    exit("В {$base} не найдена Joomla (нужны configuration.php и includes/defines.php). Укажите --base=/путь/к/сайту\n");
}

define('JPATH_BASE', $base);
require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;

$db    = Factory::getContainer()->get(DatabaseInterface::class);
$apply = isset($opts['apply']);

/** Только имена, без экранирования пользовательского ввода: строки берутся из базы */
function rows(DatabaseInterface $db, string $table, string $where = '1'): array
{
    return $db->setQuery('SELECT * FROM ' . $db->quoteName($table) . ' WHERE ' . $where)->loadAssocList() ?: [];
}

// ------------------------------------------------------------------ откат
if (isset($opts['rollback'])) {
    $file = (string) $opts['rollback'];
    $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;

    if (!is_array($data)) {
        exit("Файл копии не найден или повреждён: {$file}\n");
    }

    $db->transactionStart();

    foreach ($data['fields'] ?? [] as $f) {
        $o = (object) ['id' => (int) $f['id'], 'type' => $f['type'], 'params' => $f['params'], 'fieldparams' => $f['fieldparams'], 'state' => (int) $f['state']];
        $db->updateObject('#__fields', $o, 'id');
    }

    foreach ($data['values_changed'] ?? [] as $v) {
        $db->setQuery(
            'UPDATE ' . $db->quoteName('#__fields_values') . ' SET ' . $db->quoteName('value') . ' = ' . $db->quote($v['old'])
            . ' WHERE ' . $db->quoteName('field_id') . ' = ' . (int) $v['field_id'] . ' AND ' . $db->quoteName('item_id') . ' = ' . $db->quote((string) $v['item_id'])
            . ' AND ' . $db->quoteName('value') . ' = ' . $db->quote($v['new'])
        )->execute();
    }

    foreach ($data['values_deleted'] ?? [] as $v) {
        $row = (object) ['field_id' => (int) $v['field_id'], 'item_id' => (string) $v['item_id'], 'value' => $v['value']];
        $db->insertObject('#__fields_values', $row);
    }

    $db->transactionCommit();
    echo 'Откат выполнен: полей ' . count($data['fields'] ?? []) . ', значений изменено ' . count($data['values_changed'] ?? []) . ', удалено ' . count($data['values_deleted'] ?? []) . ".\n";
    exit(0);
}

$backup = ['made' => date('c'), 'fields' => [], 'values_changed' => [], 'values_deleted' => []];
$report = [];

/** Убирает из параметров поля ключ layout, если он указывает на макет, которого в новом шаблоне нет */
function cleanParams(string $json): string
{
    $p = json_decode($json, true);

    if (!is_array($p)) {
        return $json;
    }

    if (isset($p['layout']) && in_array($p['layout'], ['verticalvideo', 'youtube', 'tiktok', 'sociallist', 'fotorama', 'fotorama2', 'gallety'], true)) {
        unset($p['layout']);
    }

    return json_encode($p, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function retype(array &$backup, array &$report, array $f, string $newType, bool $resetFieldParams, bool $apply, DatabaseInterface $db): void
{
    $backup['fields'][] = ['id' => $f['id'], 'type' => $f['type'], 'params' => $f['params'], 'fieldparams' => $f['fieldparams'], 'state' => $f['state']];
    $report[] = "поле #{$f['id']} «{$f['name']}»: тип {$f['type']} → {$newType}";

    if ($apply) {
        $o = (object) ['id' => (int) $f['id'], 'type' => $newType, 'params' => cleanParams((string) $f['params'])];

        if ($resetFieldParams) {
            $o->fieldparams = '{}';
        }

        $db->updateObject('#__fields', $o, 'id');
    }
}

$db->transactionStart();

// ------------------------------------------------------------------ видео
if (isset($opts['video'])) {
    foreach (rows($db, '#__fields', $db->quoteName('type') . ' IN (' . $db->quote('youtube') . ',' . $db->quote('verticalvideo') . ')') as $f) {
        retype($backup, $report, $f, 'wmarkavideo', true, $apply, $db);
    }
}

// ------------------------------------------------------------------ ссылки
if (isset($opts['links'])) {
    foreach (array_filter(array_map('trim', explode(',', (string) $opts['links']))) as $name) {
        foreach (rows($db, '#__fields', $db->quoteName('name') . ' = ' . $db->quote($name)) as $f) {
            retype($backup, $report, $f, 'wmarkalinks', false, $apply, $db);
        }
    }
}

// ------------------------------------------------------------------ галереи
if (isset($opts['galleries'])) {
    $ids = array_filter(array_map('intval', explode(',', (string) $opts['galleries'])));

    foreach ($ids as $fid) {
        $f = (rows($db, '#__fields', $db->quoteName('id') . ' = ' . $fid)[0] ?? null);

        if (!$f) {
            $report[] = "поле #{$fid}: не найдено";

            continue;
        }

        // вспомогательные поля старой подформы
        $sub = [];

        if ($f['type'] === 'subform') {
            $fp = json_decode((string) $f['fieldparams'], true);

            foreach (($fp['options'] ?? []) as $opt) {
                if (!empty($opt['customfield'])) {
                    $sub[] = (int) $opt['customfield'];
                }
            }
        }

        retype($backup, $report, $f, 'wmarkagallery', true, $apply, $db);

        $converted = $kept = $purged = 0;

        foreach (rows($db, '#__fields_values', $db->quoteName('field_id') . ' = ' . $fid) as $v) {
            $raw = trim((string) $v['value']);

            if ($raw === '' || $raw === '[]' || $raw === '{}') {
                if (isset($opts['purge-empty'])) {
                    $purged++;
                    $backup['values_deleted'][] = ['field_id' => $fid, 'item_id' => $v['item_id'], 'value' => $v['value']];

                    if ($apply) {
                        $db->setQuery('DELETE FROM ' . $db->quoteName('#__fields_values') . ' WHERE ' . $db->quoteName('field_id') . ' = ' . $fid
                            . ' AND ' . $db->quoteName('item_id') . ' = ' . $db->quote((string) $v['item_id']) . ' AND ' . $db->quoteName('value') . ' = ' . $db->quote((string) $v['value']))->execute();
                    }
                }

                continue;
            }

            $new = convertGallery($raw);

            if ($new === null) {
                $kept++;

                continue;
            }

            $converted++;

            if ($new !== $raw) {
                $backup['values_changed'][] = ['field_id' => $fid, 'item_id' => $v['item_id'], 'old' => $v['value'], 'new' => $new];

                if ($apply) {
                    $db->setQuery('UPDATE ' . $db->quoteName('#__fields_values') . ' SET ' . $db->quoteName('value') . ' = ' . $db->quote($new)
                        . ' WHERE ' . $db->quoteName('field_id') . ' = ' . $fid . ' AND ' . $db->quoteName('item_id') . ' = ' . $db->quote((string) $v['item_id'])
                        . ' AND ' . $db->quoteName('value') . ' = ' . $db->quote((string) $v['value']))->execute();
                }
            }
        }

        $report[] = "   значений галереи: переписано {$converted}, пустых " . (isset($opts['purge-empty']) ? "удалено {$purged}" : 'оставлено') . ", не распознано (не тронуто) {$kept}";

        if (isset($opts['trash-subfields'])) {
            foreach ($sub as $sid) {
                foreach (rows($db, '#__fields', $db->quoteName('id') . ' = ' . $sid . ' AND ' . $db->quoteName('only_use_in_subform') . ' = 1') as $sf) {
                    $backup['fields'][] = ['id' => $sf['id'], 'type' => $sf['type'], 'params' => $sf['params'], 'fieldparams' => $sf['fieldparams'], 'state' => $sf['state']];
                    $report[] = "   вспомогательное поле #{$sf['id']} «{$sf['name']}» — в корзину";

                    if ($apply) {
                        $trash = (object) ['id' => (int) $sf['id'], 'state' => -2];
                        $db->updateObject('#__fields', $trash, 'id');
                    }
                }
            }
        }
    }
}

/**
 * Старый вид строки: {"field15":{"imagefile":…,"alt_text":…},"field14":"подпись"} → новый: {"image":{…},"caption":"…","credit":"…"}.
 * Возвращает null, если значение не похоже на галерею.
 */
function convertGallery(string $json): ?string
{
    $rows = json_decode($json, true);

    if (!is_array($rows) || !$rows) {
        return null;
    }

    $out = [];
    $n   = 0;

    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $img   = null;
        $texts = [];

        foreach ($row as $key => $val) {
            if (is_string($val) && $val !== '' && $val[0] === '{' && is_array($d = json_decode($val, true))) {
                $val = $d;
            }

            if (is_array($val) && isset($val['imagefile'])) {
                $img = ['imagefile' => (string) $val['imagefile'], 'alt_text' => (string) ($val['alt_text'] ?? '')];
            } elseif (is_string($val) && trim($val) !== '' && !in_array($key, ['image', 'caption', 'credit'], true)) {
                $texts[] = trim($val);
            }
        }

        if ($img === null) {
            continue;
        }

        $out['row' . $n++] = [
            'image'   => $img,
            'caption' => isset($row['caption']) && is_string($row['caption']) ? $row['caption'] : ($texts[0] ?? ''),
            'credit'  => isset($row['credit']) && is_string($row['credit']) ? $row['credit'] : ($texts[1] ?? ''),
        ];
    }

    return $out ? json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
}

if ($apply) {
    $file = $base . '/migrate-fields-backup-' . date('Ymd-His') . '.json';
    file_put_contents($file, json_encode($backup, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    $db->transactionCommit();
    echo implode("\n", $report) . "\n\nЗАПИСАНО. Копия прежних строк: {$file}\nОткат: php migrate-fields.php --rollback={$file}\n";
} else {
    $db->transactionRollback();
    echo implode("\n", $report) . "\n\nПРОБНЫЙ ПРОГОН: ничего не записано. Для записи добавьте --apply (после резервной копии базы).\n";
}
