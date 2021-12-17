<?php

namespace Framelix\Calendar\PageBlocks;

use Framelix\Calendar\Storable\Entry;
use Framelix\Framelix\Date;
use Framelix\Framelix\DateTime;
use Framelix\Framelix\Form\Field\Color;
use Framelix\Framelix\Form\Field\Select;
use Framelix\Framelix\Form\Field\Text;
use Framelix\Framelix\Form\Field\Textarea;
use Framelix\Framelix\Form\Field\Toggle;
use Framelix\Framelix\Form\Form;
use Framelix\Framelix\Html\HtmlAttributes;
use Framelix\Framelix\Html\Toast;
use Framelix\Framelix\Lang;
use Framelix\Framelix\Network\JsCall;
use Framelix\Framelix\Network\Request;
use Framelix\Framelix\Url;
use Framelix\Framelix\Utils\ColorUtils;
use Framelix\Framelix\View\Api;
use Framelix\Myself\LayoutUtils;
use Framelix\Myself\PageBlocks\BlockBase;
use Framelix\Myself\Storable\PageBlock;

/**
 * Calendar
 */
class Calendar extends BlockBase
{
    /**
     * The current date
     * @var Date|null
     */
    private ?Date $date;

    /**
     * On js call
     * @param JsCall $jsCall
     */
    public static function onJsCall(JsCall $jsCall): void
    {
        $pageBlock = PageBlock::getById(Request::getGet('pageBlockId'));
        if (!$pageBlock) {
            return;
        }
        $settings = $pageBlock->pageBlockSettings;
        $date = Date::create(Request::getGet('date'));
        switch ($jsCall->action) {
            case 'save':
                if ($settings['global'] ?? null) {
                    $entry = Entry::getByConditionOne('date = {0}', [$date]);
                    if (!$entry) {
                        $entry = new Entry();
                        $entry->date = $date;
                    }
                    $entry->color = Request::getPost('color');
                    $entry->info = Request::getPost('info');
                    $entry->internalInfo = Request::getPost('internalInfo');
                    $entry->store();
                } else {
                    $settings['entries'][$date->getDbValue()] = [
                        'color' => Request::getPost('color'),
                        'info' => Request::getPost('info'),
                        'internalInfo' => Request::getPost('internalInfo'),
                    ];
                    $pageBlock->pageBlockSettings = $settings;
                    $pageBlock->store();
                }
                Toast::success('__saved__');
                Url::getBrowserUrl()->redirect();
            case 'edit':
                ?>
                <h2><?= $date->dateTime->getDayOfMonth() ?>. <?= $date->dateTime->getMonthNameAndYear() ?></h2>
                <?php
                $form = new Form();
                $form->id = "editentry";
                $form->submitUrl = Api::getSignedCallPhpMethodUrlString(
                    __CLASS__,
                    'save',
                    ['date' => Request::getGet('date'), 'pageBlockId' => $pageBlock]
                );
                if ($settings['global'] ?? null) {
                    $object = Entry::getByConditionOne('date = {0}', [$date]);
                    $entry = [
                        'color' => $object->color ?? null,
                        'info' => $object->info ?? null,
                        'internalInfo' => $object->internalInfo ?? null,
                    ];
                } else {
                    $entry = $settings['entries'][$date->getDbValue()] ?? null;
                }

                $field = new Color();
                $field->name = "color";
                $field->label = '__calendar_pageblocks_calendar_cellcolor_day__';
                $field->defaultValue = $entry['color'] ?? null;
                $form->addField($field);

                $field = new Text();
                $field->name = "info";
                $field->label = '__calendar_pageblocks_calendar_cellinfo__';
                $field->defaultValue = $entry['info'] ?? null;
                $form->addField($field);

                $field = new Textarea();
                $field->name = "internalInfo";
                $field->label = '__calendar_pageblocks_calendar_internalinfo__';
                $field->defaultValue = $entry['internalInfo'] ?? null;
                $form->addField($field);

                $form->addSubmitButton('save', '__save__', 'save');
                $form->show();
                break;
        }
    }

    /**
     * Show content for this block
     * @return void
     */
    public function showContent(): void
    {
        $this->date = Date::create(Request::getGet('calendarDate') ?? "now");
        if (!$this->date) {
            $this->date = Date::create("now");
        }
        $this->date->dateTime->setDayOfMonth(1);
        $settings = $this->pageBlock->pageBlockSettings;
        $minDate = Date::create($settings['minDate'] ?? 'now - 10 years');
        $maxDate = Date::create($settings['maxDate'] ?? 'now + 10 years');
        if ($this->date->getSortableValue() < $minDate->getSortableValue()) {
            $this->date = $minDate;
        }
        if ($this->date->getSortableValue() > $maxDate->getSortableValue()) {
            $this->date = $maxDate;
        }
        $prevMonth = $this->date->clone();
        $prevMonth->dateTime->modify("-1 month");
        $nextMonth = $this->date->clone();
        $nextMonth->dateTime->modify("+1 month");
        $entries = [];
        if ($settings['global'] ?? null) {
            $objects = Entry::getByCondition(
                'date BETWEEN {0} AND {1}',
                [$this->date->dateTime->format("Y-m-01"), $this->date->dateTime->format("Y-m-t")]
            );
            foreach ($objects as $object) {
                $entries[$object->date->dateTime->getDayOfMonth()] = [
                    'color' => $object->color,
                    'info' => $object->info,
                ];
            }
        } else {
            $range = Date::rangeDays($this->date->dateTime->format("Y-m-01"), $this->date->dateTime->format("Y-m-t"));
            foreach ($range as $date) {
                if (isset($settings['entries'][$date->getDbValue()])) {
                    $entries[$date->dateTime->getDayOfMonth()] = $settings['entries'][$date->getDbValue()];
                }
            }
        }
        ?>
        <div class="calendar-pageblocks-calendar-month-select">
            <?
            if ($this->date->getSortableValue() >= $minDate->getSortableValue()) {
                ?>
                <a href="<?= Url::create()->setParameter(
                    'calendarDate',
                    $prevMonth
                ) ?>" class="framelix-button">«</a>
                <?
            }
            ?>
            <strong><?= $this->date->dateTime->getMonthNameAndYear() ?></strong>
            <?
            if ($this->date->getSortableValue() <= $maxDate->getSortableValue()) {
                ?>
                <a href="<?= Url::create()->setParameter(
                    'calendarDate',
                    $nextMonth
                ) ?>" class="framelix-button">»</a>
                <?
            }
            ?>
        </div>
        <div class="calendar-pageblocks-calendar-table">
            <table>
                <thead>
                <tr>
                    <?php
                    for ($i = 1; $i <= 7; $i++) {
                        echo '<th>' . Lang::get('__framelix_dayshort_' . $i . '__') . '</th>';
                    }
                    ?>
                </tr>
                </thead>
                <tbody>
                <?php
                $week = -1;
                while ($week <= 6) {
                    $week++;
                    $weekStart = $week > 0 ? DateTime::create(
                        $this->date->getDbValue() . " + $week weeks monday this week"
                    ) : DateTime::create($this->date->getDbValue() . " monday this week");
                    if ((int)$weekStart->format("Ym") > (int)$this->date->dateTime->format("Ym")) {
                        break;
                    }
                    echo '<tr>';
                    for ($i = 1; $i <= 7; $i++) {
                        $addDays = $i - 1;
                        $date = Date::create($weekStart->format("Y-m-d"));
                        if ($addDays > 0) {
                            $date->dateTime->modify("+ $addDays days");
                        }
                        $html = (int)$date->dateTime->format("d");
                        $attributes = new HtmlAttributes();
                        if ($date->dateTime->getMonth() !== $this->date->dateTime->getMonth()) {
                            $attributes->addClass('calendar-pageblocks-calendar-othermonth');
                        } else {
                            $entry = $entries[$date->dateTime->getDayOfMonth()] ?? null;
                            $color = $entry['color'] ?? $settings['cellColor'] ?? null;
                            if ($color) {
                                $attributes->setStyle('background-color', $color);
                                $attributes->setStyle('color', ColorUtils::invertColor($color, true));
                            }
                            $info = $entry['info'] ?? null;
                            if ($info) {
                                $attributes->set('title', $info);
                            }
                            if (LayoutUtils::isEditAllowed()) {
                                $attributes->set(
                                    'data-modal',
                                    Api::getSignedCallPhpMethodUrlString(
                                        __CLASS__,
                                        'edit',
                                        ['date' => $date, 'pageBlockId' => $this->pageBlock]
                                    )
                                );
                            }
                        }
                        echo '<td ' . $attributes . '>' . $html . '</td>';
                    }
                    echo '</tr>';
                }
                ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Get array of settings forms
     * If more then one form is returned, it will create tabs with forms
     * @return Form[]
     */
    public function getSettingsForms(): array
    {
        $forms = parent::getSettingsForms();

        $form = new Form();
        $form->id = "main";
        $forms[] = $form;

        $field = new Toggle();
        $field->name = 'pageBlockSettings[global]';
        $form->addField($field);

        $field = new Select();
        $field->name = 'pageBlockSettings[minDate]';
        $field->searchable = true;
        for ($i = 0; $i <= 120; $i++) {
            $date = Date::create("now");
            $date->dateTime->setDayOfMonth(1);
            if ($i) {
                $date->dateTime->modify("-$i month");
            }
            $field->addOption($date->getDbValue(), $date->dateTime->getMonthNameAndYear());
        }
        $form->addField($field);

        $field = new Select();
        $field->name = 'pageBlockSettings[maxDate]';
        $field->searchable = true;
        for ($i = 0; $i <= 120; $i++) {
            $date = Date::create("now");
            $date->dateTime->setDayOfMonth(1);
            if ($i) {
                $date->dateTime->modify("+$i month");
            }
            $field->addOption($date->getDbValue(), $date->dateTime->getMonthNameAndYear());
        }
        $form->addField($field);

        $field = new Color();
        $field->name = 'pageBlockSettings[cellColor]';
        $form->addField($field);

        return $forms;
    }
}