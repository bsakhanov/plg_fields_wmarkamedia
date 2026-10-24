<?php

/**
 * Пример переопределения макета поля «Видео».
 *
 * Положите файл в templates/<ваш шаблон>/html/plg_fields_wmarkamedia/wmarkavideo.php — Joomla возьмёт его вместо
 * макета плагина (так же переопределяются wmarkagallery.php и wmarkalinks.php).
 *
 * Здесь: никакого плеера-фасада — сразу iframe с отложенной загрузкой, подпись из названия материала.
 * Переменные те же, что в макете плагина: $field (поле), $fieldParams (настройки), $item (материал).
 */

\defined('_JEXEC') or die;

use Wmarka\Plugin\Fields\WmarkaMedia\Helper\VideoHelper;

$ref = VideoHelper::parse(\is_array($field->value) ? (string) reset($field->value) : (string) $field->value);

if ($ref === null || $ref->kind !== 'iframe') {
    return;
}

$e = static fn ($s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
?>
<figure class="my-video my-video--<?php echo $e($ref->provider); ?>">
    <iframe src="<?php echo $e($ref->embed); ?>" title="<?php echo $e($item->title ?? $ref->label); ?>" loading="lazy" allowfullscreen
            style="width:100%;aspect-ratio:<?php echo $e($ref->ratio); ?>;border:0"></iframe>
    <figcaption><?php echo $e($item->title ?? ''); ?> · <a href="<?php echo $e($ref->page); ?>" rel="noopener noreferrer"><?php echo $e($ref->label); ?></a></figcaption>
</figure>
