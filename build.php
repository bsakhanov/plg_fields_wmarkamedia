#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Сборка установочного пакета плагина Wmarka Media и запись о нём в updates.xml.
 *
 *   php build.php              собрать dist/plg_fields_wmarkamedia-<версия>.zip и добавить запись в updates.xml
 *   php build.php --check      только проверить: манифест, версию, языковые файлы, синтаксис PHP, updates.xml
 *   php build.php --checksum   записать в updates.xml контрольную сумму sha256 собранного архива
 *
 * Версия берётся из src/wmarkamedia.xml. На одной и той же машине архив собирается воспроизводимо: файлы по алфавиту,
 * время файлов фиксировано (первое число месяца из <creationDate>).
 *
 * Контрольную сумму (--checksum) записывайте, только если к релизу прикладывается именно этот, собранный у вас архив:
 * байты архива, собранного в другом месте (например, в GitHub Actions), могут отличаться, и Joomla отвергнет такое обновление.
 */

const OWNER = 'bsakhanov';
const REPO  = 'plg_fields_wmarkamedia';

$root         = __DIR__;
$src          = $root . '/src';
$dist         = $root . '/dist';
$updatesFile  = $root . '/updates.xml';
$args         = array_slice($argv, 1);
$checkOnly    = in_array('--check', $args, true);
$withChecksum = in_array('--checksum', $args, true);

function fail(string $message): never
{
    fwrite(STDERR, "ОШИБКА: {$message}\n");
    exit(1);
}

$manifest = @simplexml_load_file($src . '/wmarkamedia.xml') ?: fail('манифест src/wmarkamedia.xml не читается как XML');
$version  = (string) $manifest->version;

if (!preg_match('~^\d+\.\d+\.\d+$~', $version)) {
    fail("версия «{$version}» должна быть вида 1.2.3");
}

// ---------------------------------------------------------------- список файлов
$files = [];

foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS)) as $file) {
    if (!$file->isFile()) {
        continue;
    }

    $rel = str_replace('\\', '/', substr($file->getPathname(), strlen($src) + 1));

    if (preg_match('~(^|/)(\.DS_Store|Thumbs\.db)$|\.(orig|bak|tmp)$~', $rel)) {
        continue;
    }

    $files[$rel] = $file->getPathname();
}

ksort($files, SORT_STRING);

// ---------------------------------------------------------------- проверки
$problems = [];

foreach ($manifest->languages->language ?? [] as $language) {
    if (!isset($files[(string) $language])) {
        $problems[] = 'в манифесте указан языковой файл ' . (string) $language . ', его нет';
    }
}

foreach (['script.php', 'LICENSE.txt'] as $required) {
    if (!isset($files[$required])) {
        $problems[] = "нет файла {$required}";
    }
}

foreach ($manifest->files->folder ?? [] as $folder) {
    if (!is_dir($src . '/' . (string) $folder)) {
        $problems[] = 'в манифесте указана папка ' . (string) $folder . ', её нет';
    }
}

$expectedUpdateUrl = 'https://raw.githubusercontent.com/' . OWNER . '/' . REPO . '/main/updates.xml';

if ((string) ($manifest->updateservers->server ?? '') !== $expectedUpdateUrl) {
    $problems[] = "адрес сервера обновлений в манифесте должен быть {$expectedUpdateUrl}";
}

foreach ($files as $rel => $abs) {
    if (str_ends_with($rel, '.php')) {
        $out = [];
        exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($abs) . ' 2>&1', $out, $code);

        if ($code !== 0) {
            $problems[] = "синтаксис {$rel}: " . implode(' ', $out);
        }
    }
}

if (!is_file($updatesFile) || !@simplexml_load_file($updatesFile)) {
    $problems[] = 'updates.xml отсутствует или не читается как XML';
}

if ($problems) {
    fwrite(STDERR, "Проверка не пройдена:\n - " . implode("\n - ", $problems) . "\n");
    exit(1);
}

echo "Проверка пройдена: версия {$version}, файлов в src: " . count($files) . "\n";

if ($checkOnly) {
    exit(0);
}

// ---------------------------------------------------------------- архив
$zipName = "plg_fields_wmarkamedia-{$version}.zip";
$zipPath = "{$dist}/{$zipName}";
$stamp   = strtotime(((string) $manifest->creationDate) . '-01 00:00:00 UTC') ?: 1767225600;

if (!is_dir($dist) && !mkdir($dist, 0755, true) && !is_dir($dist)) {
    fail('не удалось создать папку dist');
}

@unlink($zipPath);
$zip = new ZipArchive();

if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fail('не удалось создать архив');
}

foreach ($files as $rel => $abs) {
    $zip->addFile($abs, $rel);
    $zip->setMtimeName($rel, $stamp);
    $zip->setCompressionName($rel, ZipArchive::CM_DEFLATE, 9);
}

$zip->close();
$sha256 = hash_file('sha256', $zipPath);

// ---------------------------------------------------------------- updates.xml
$tag      = 'v' . $version;
$base     = 'https://github.com/' . OWNER . '/' . REPO;
$checksum = $withChecksum ? "\n\t<sha256>{$sha256}</sha256>" : '';
$entryXml = <<<XML
<update>
	<name>Fields - Wmarka Media</name>
	<description>Video, photo gallery and link list custom fields for Joomla articles: one plugin, three field types.</description>
	<element>wmarkamedia</element>
	<type>plugin</type>
	<folder>fields</folder>
	<client>site</client>
	<version>{$version}</version>
	<infourl title="Wmarka Media {$version}">{$base}/releases/tag/{$tag}</infourl>
	<downloads>
		<downloadurl type="full" format="zip">{$base}/releases/download/{$tag}/{$zipName}</downloadurl>
	</downloads>{$checksum}
	<tags>
		<tag>stable</tag>
	</tags>
	<maintainer>Beibit Sakhanov</maintainer>
	<maintainerurl>https://webmarka.kz</maintainerurl>
	<targetplatform name="joomla" version="((5\.[2-9])|(6\.[0-9]+))"/>
	<php_minimum>8.1.0</php_minimum>
</update>
XML;

$doc = new DOMDocument('1.0', 'UTF-8');
$doc->preserveWhiteSpace = false;
$doc->formatOutput       = true;
$doc->load($updatesFile);

$entryDoc = new DOMDocument('1.0', 'UTF-8');
$entryDoc->loadXML($entryXml);
$new = $doc->importNode($entryDoc->documentElement, true);

$replaced = false;

foreach ($doc->getElementsByTagName('update') as $existing) {
    if ($existing->getElementsByTagName('version')->item(0)?->textContent === $version) {
        $existing->parentNode->replaceChild($new, $existing);
        $replaced = true;

        break;
    }
}

if (!$replaced) {
    $first = $doc->documentElement->firstChild;
    $first ? $doc->documentElement->insertBefore($new, $first) : $doc->documentElement->appendChild($new);
}

$doc->save($updatesFile);

printf("Собрано: dist/%s, %d КБ, файлов %d\nsha256: %s\nupdates.xml: запись для %s %s%s\n", $zipName, (int) round(filesize($zipPath) / 1024), count($files), $sha256, $version, $replaced ? 'заменена' : 'добавлена', $withChecksum ? ' (с контрольной суммой)' : ' (без контрольной суммы)');
