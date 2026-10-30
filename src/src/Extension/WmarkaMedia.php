<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  Fields.WmarkaMedia
 *
 * @copyright   (C) 2026 Beibit Sakhanov
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wmarka\Plugin\Fields\WmarkaMedia\Extension;

use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\Component\Fields\Administrator\Plugin\FieldsPlugin;
use Joomla\Event\SubscriberInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Fields - Wmarka Media.
 *
 * Один плагин — три типа поля (каждый файл в tmpl/ задаёт отдельный тип, так устроена и Joomla):
 *   wmarkavideo   — универсальное видео (YouTube, Vimeo, VK, Rutube, Facebook, Instagram, TikTok, файл; Telegram — ссылкой);
 *   wmarkagallery — фотогалерея (повторяемые строки: фото, подпись, источник кадра);
 *   wmarkalinks   — список ссылок на соцсети (по одной в строке).
 *
 * Форма ввода строится здесь, вывод значения — в tmpl/*.php, параметры поля — в params/*.xml.
 */
final class WmarkaMedia extends FieldsPlugin implements SubscriberInterface
{
    /**
     * Превращает поле в элемент формы редактирования материала.
     *
     * @param   \stdClass    $field   Поле
     * @param   \DOMElement  $parent  Родитель
     * @param   Form         $form    Форма
     *
     * @return  \DOMElement|null
     */
    public function onCustomFieldsPrepareDom($field, \DOMElement $parent, Form $form)
    {
        $node = parent::onCustomFieldsPrepareDom($field, $parent, $form);

        if (!$node) {
            return $node;
        }

        switch ($field->type) {
            case 'wmarkavideo':
                $node->setAttribute('type', 'text');
                $node->setAttribute('size', '60');

                if (!$node->getAttribute('hint')) {
                    $node->setAttribute('hint', Text::_('PLG_FIELDS_WMARKAMEDIA_VIDEO_HINT'));
                }

                break;

            case 'wmarkalinks':
                $node->setAttribute('type', 'textarea');
                $node->setAttribute('rows', '4');
                $node->setAttribute('cols', '60');

                if (!$node->getAttribute('hint')) {
                    $node->setAttribute('hint', Text::_('PLG_FIELDS_WMARKAMEDIA_LINKS_HINT'));
                }

                break;

            case 'wmarkagallery':
                $this->buildGallery($node, $field);

                break;
        }

        return $node;
    }

    /**
     * Подформа галереи: повторяемые строки «фото, подпись, источник». Так же устроен штатный плагин subform,
     * но имена подполей фиксированы (image, caption, credit), а не номера полей, поэтому данные читаются без таблицы полей.
     *
     * @param   \DOMElement  $node   Узел поля
     * @param   \stdClass    $field  Поле
     *
     * @return  void
     */
    private function buildGallery(\DOMElement $node, $field): void
    {
        $params = clone $this->params;
        $params->merge($field->fieldparams);

        $node->setAttribute('type', 'subform');
        $node->setAttribute('fieldname', 'row');
        $node->setAttribute('multiple', 'true');
        $node->setAttribute('layout', 'joomla.form.field.subform.repeatable-table');

        $max = (int) $params->get('max_items', 0);

        if ($max > 0) {
            $node->setAttribute('max', (string) $max);
        }

        if ($field->required) {
            $node->setAttribute('min', '1');
        }

        $form = $node->appendChild(new \DOMElement('form'));
        $form->setAttribute('hidden', 'true');
        $form->setAttribute('name', $field->name . '_modal');
        $form->setAttribute('repeat', 'true');

        $subfields = [
            ['image', 'accessiblemedia', 'PLG_FIELDS_WMARKAMEDIA_GALLERY_IMAGE'],
            ['caption', 'text', 'PLG_FIELDS_WMARKAMEDIA_GALLERY_CAPTION'],
            ['credit', 'text', 'PLG_FIELDS_WMARKAMEDIA_GALLERY_CREDIT'],
        ];

        foreach ($subfields as [$name, $type, $label]) {
            $sub = $form->appendChild(new \DOMElement('field'));
            $sub->setAttribute('name', $name);
            $sub->setAttribute('type', $type);
            $sub->setAttribute('label', $label);

            if ($type === 'text') {
                $sub->setAttribute('size', '30');
            }
        }
    }
}
