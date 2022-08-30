<?php
/**
* @file
* @brief    showplus slideshow module for Joomla
* @author   Levente Hunyadi
* @version  2.0.0
* @remarks  Copyright (C) 2011-2017 Levente Hunyadi
* @remarks  Licensed under GNU/GPLv3, see https://www.gnu.org/licenses/gpl-3.0.html
* @see      https://hunyadi.info.hu/projects/showplus
*/

/*
* showplus slideshow module for Joomla
* Copyright 2011-2017 Levente Hunyadi
*
* showplus is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* showplus is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with showplus.  If not, see <https://www.gnu.org/licenses/>.
*/

// Check to ensure this file is within the rest of the framework
defined('JPATH_BASE') or die();

jimport('joomla.form.formfield');

/**
* Renders a control that shows whether labels are defined for a gallery.
* This class implements a user-defined control in the administration back-end.
*/
class JFormFieldLabels extends JFormField {
    protected $type = 'Labels';

    public function getInput() {
        $class = ( isset($this->element['class']) ? (string)$this->element['class'] : 'inputbox' );
        $ctrlid = str_replace(array('[',']'), '', $this->name);

        // add script declaration to header to hide control on folder change
        $folderctrl = $this->form->getField('folder','params');
        $labelsctrl = $this->form->getField('labels','params');
        $document = JFactory::getDocument();
        $document->addScriptDeclaration(
            "document.addEventListener('DOMContentLoaded', function() {".
                "var label = document.getElementById('{$ctrlid}');".
                "[].forEach.call(document.querySelectorAll('input[name=\"{$folderctrl->name}\"], input[name=\"{$labelsctrl->name}\"]'), function (ctrl) {".
                    "ctrl.addEventListener('change', function () {".
                        "label.style.setProperty('display','none');".
                    "});".
                "});".
            "});"
        );

        // test whether labels file exists
        $labelsfile = $this->getLabelsFilename();
        
        // add control to configuration page
        if ($labelsfile !== false) {
            // green tick icon
            $imagedata = 'iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAAAXNSR0IArs4c6QAAAAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAAN1wAADdcBQiibeAAAAAd0SU1FB9sEAQomLuuOjNQAAAFcSURBVDjLldHNS9RRFMbxj7+ZkdIpahWm+LKZcN0+chcItnIpbioJyRBB27oIkSh05cuIoItaDLRxQHAE/4HaBKKCkJIILUTBF3IG/bUQh8bGmfGs7uV5nu8959wqN62PEh7qFmhyajl6o3DSc/XmxdwV4reNysNT2iw6lhFacm7eQOXh9+os2JYRygh98eFSCsqGO0UkzLqlERxI++pd5YB2g+55BrJ+WfVSytmlXFUyPOaRhG9i4kKhLR1eSf9rKd1BvTExcXDo89VwIeCpwi+d1pVvPWffpsFib1wARrV6Y9msF6DfbQ8M5137xg3ZLQaImvBao1HV7qj1WFJWoEGNFnBqx45P100ZdW5dRDWIiGs2V+DYM2LI4XWAQK8V294Ki6gnNqXNlNrzxQ56TNkz+Z96LCklWx4AKwac+JG/5xz5eWWcIhXJn77LeWJN4L4/NhxI6bNYDvAXg4tbY1zInY8AAAAASUVORK5CYII=';
        } else {
            // red cross icon
            $imagedata = 'iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAAAXNSR0IArs4c6QAAAAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAAN1wAADdcBQiibeAAAAAd0SU1FB9sEAQonCCCYOGgAAAEiSURBVDjLldG9SlxRFIbhZxxQgilSjlgkVoo3YJ06BCxyAam0UAJ23oiCpbmBENCkCjYpxMI+IJZRidHGs7aDP9tmBs7snDPBD1az13q/vX6oKTMVfAkOEm+1qGKm4l1mYiQRzAZ5EPfBh+KDTsVaUAW5YldZEPytmVS3zMM1rxL7tVxO7PzTXvC5XhQc9lkMfhXwZtt87wuDHNwV8HbbfmReBKnBZAh/z3SH9ROlQYeU+dnif/7Axw4PrQYDk8uW7lZecmGcKlaDx4b2j/1PwXoLnIM/mclx8MYYeBifntP2RXBavF0nXpfwcgN81mfxhl5wVJxxb8Qg8a2Af/dZqG1+OrHXOkbiTXA1SJ4k5hrO1w22gh+ZTtMCl4KvN/Q8U0+UpuyJtWxJ0gAAAABJRU5ErkJggg==';
        }
        return "<span class=\"{$class}\" id=\"{$ctrlid}\" style=\"display:inline-block; border:0; padding:0; width:16px; height:16px; background-image: url('data:image/png;base64,{$imagedata}');\"></span>";
    }

    /**
    * Returns the language-specific labels filename.
    * @return File system path to the language file to use, or false if no labels file exists.
    */
    private function getLabelsFilename() {
        // get value of parameters "folder" and "labels"
        $folder = $this->form->getValue('folder','params');
        $labels = $this->form->getValue('labels','params');
        $labels_multilingual = (bool) $this->form->getValue('labels_multilingual','params');

        if ($labels_multilingual) {  // check for language-specific labels file
            $lang = JFactory::getLanguage();
            $labelsfile = JPATH_ROOT.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $folder).DIRECTORY_SEPARATOR.$labels.'.'.$lang->getTag().'.txt';
            if (is_file($labelsfile)) {
                return $labelsfile;
            }
        }
        // default to language-neutral labels file
        $labelsfile = JPATH_ROOT.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $folder).DIRECTORY_SEPARATOR.$labels.'.txt';  // filesystem path to labels file
        if (is_file($labelsfile)) {
            return $labelsfile;
        }
        return false;
    }
}
