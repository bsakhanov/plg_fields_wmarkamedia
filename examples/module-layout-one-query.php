<?php

/**
 * Фрагмент макета модуля (например, ленты видео): значения поля «Видео» для всех материалов списка берутся ОДНИМ запросом,
 * а не вызовом FieldsHelper::getFields() на каждый материал (так часто делают макеты лент видео: по запросу на материал).
 *
 * $list — массив материалов модуля (объекты с полем id); $fieldName — имя поля «Видео» (например, 'youtube' или 'vertik').
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Wmarka\Plugin\Fields\WmarkaMedia\Helper\VideoHelper;

$fieldName = 'youtube';
$db        = Factory::getContainer()->get(DatabaseInterface::class);
$ids       = array_map(static fn ($i) => (string) $i->id, $list);

$query = $db->getQuery(true)
    ->select($db->quoteName(['v.item_id', 'v.value']))
    ->from($db->quoteName('#__fields_values', 'v'))
    ->join('INNER', $db->quoteName('#__fields', 'f') . ' ON ' . $db->quoteName('f.id') . ' = ' . $db->quoteName('v.field_id'))
    ->where($db->quoteName('f.name') . ' = :name')
    ->where($db->quoteName('f.context') . ' = ' . $db->quote('com_content.article'))
    ->whereIn($db->quoteName('v.item_id'), $ids, ParameterType::STRING)
    ->bind(':name', $fieldName);

$values = $db->setQuery($query)->loadAssocList('item_id', 'value');   // [номер материала => значение поля]

foreach ($list as $item) {
    $ref = isset($values[$item->id]) ? VideoHelper::parse($values[$item->id]) : null;   // VideoRef или null

    if ($ref === null) {
        continue;
    }

    // $ref->provider, ->label, ->id, ->page, ->embed, ->embedPlay, ->vertical, ->ratio, ->poster, ->kind
    echo $ref->label . ': ' . $ref->page . ($ref->vertical ? ' (вертикальное)' : '') . "\n";
}
