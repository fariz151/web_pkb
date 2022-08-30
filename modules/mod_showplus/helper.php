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

// no direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'utility.php';
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'exception.php';
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'params.php';

/**
* Client-side image data including image URL, image thumbnail URL and caption.
*/
class ShowPlusImageData {
    public $imageurl;
    public $thumburl;
    public $hyperlink;
    public $caption;

    public function __construct($imageurl, $thumburl, $hyperlink = null, $caption = null) {
        $this->imageurl = $imageurl;
        $this->thumburl = $thumburl;
        $this->hyperlink = $hyperlink;
        $this->caption = $caption;
    }
}

class ShowPlusImageLabel {
    public $imagefile;
    public $hyperlink;
    public $caption;

    public function __construct($imagefile, $hyperlink = null, $caption = null) {
        $this->imagefile = $imagefile;
        $this->hyperlink = $hyperlink;
        $this->caption = $caption;
    }
}

/**
* Animated slideshow.
*/
class ShowPlusSlideshow {
    /**
    * Status of debug mode as determined by first module loaded.
    * Inconsistent debug mode settings on the same page would case script conflicts between debug mode and release mode versions.
    */
    private static $debug = null;

    public function __construct(ShowPlusParameters $params = null) {
        if (is_null($params)) {
            $this->params = new ShowPlusParameters();
        } else {
            $this->params = $params;
        }
        if (is_null(self::$debug)) {  // first module loaded sets debug mode
            self::$debug = $this->params->debug;
        }
    }

    //
    // Image slideshow HTML generation
    //

    /**
    * Generates image slideshow with thumbnails, alternate text, and target activation on mouse click.
    */
    public function getSlideshowHtml() {
        $oblevel = ob_get_level();
        try {
            return $this->getImageSlideshowHtml();
        } catch (Exception $e) {  // local error
            for ($k = ob_get_level(); $k > $oblevel; $k--) {  // release output buffers
                ob_end_clean();
            }
            $app = JFactory::getApplication();
            $app->enqueueMessage($e->getMessage(), 'error');
            return $e->getMessage();
        }
    }

    private function getImageSlideshowHtml() {
        $path = JPATH_ROOT.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $this->params->folder);
        if (!file_exists($path)) {
            throw new ShowPlusImageFolderException($path);
        }

        // set slideshow identifier
        if ($this->params->id) {  // use user-supplied identifier
            $id = $this->params->id;
        } else {  // automatically generate identifier for slideshow
            $id = $this->params->folder;
            if ($this->params->moduleclass_sfx) {
                $id .= '_'.$this->params->moduleclass_sfx;
            }
            $id = 'showplus_'.preg_replace('/[^A-Za-z0-9_\-]/', '', str_replace('/', '_', $id));  // clear non-conformant special characters from name
        }
        $id = self::getUniqueId($id);

        // substitute proper left or right alignment depending on whether language is LTR or RTL
        $language = JFactory::getLanguage();
        $alignment = str_replace(array('before','after'), $language->isRTL() ? array('right','left') : array('left','right'), $this->params->alignment);

        // set image slideshow alignment on page (left, center or right)
        $classes = array('showplus-container');
        switch ($alignment) {
            case 'left': case 'left-clear': case 'left-float': $classes[] = 'showplus-left'; break;
            case 'center': $classes[] = 'showplus-center'; break;
            case 'right': case 'right-clear': case 'right-float': $classes[] = 'showplus-right'; break;
        }
        switch ($alignment) {
            case 'left': case 'left-float': case 'right': case 'right-float': $classes[] = 'showplus-float'; break;
            case 'left-clear': case 'right-clear': $classes[] = 'showplus-clear'; break;
        }

        // fetch image labels
        switch ($this->params->labels) {
            case 'filename':
                $labels = $this->getLabelsFromFilenames(); break;
            default:
                if (empty($this->params->labels_captions)) {
                    $labels = $this->getLabelsFromExternalFile();  // labels file may override default caption and hyperlink set in back-end
                } else {
                    $labels = $this->getLabelsFromString($this->params->labels_captions);
                }
        }
        $sort_order = $this->params->sort_order == SHOWPLUS_SORT_DESCENDING ? SHOWPLUS_DESCENDING : SHOWPLUS_ASCENDING;
        switch ($this->params->sort_criterion) {
            case SHOWPLUS_SORT_LABELS_OR_FILENAME:
                if (empty($labels)) {  // there is no labels file to use
                    $files = ShowPlusUtility::scandirsorted($path, SHOWPLUS_FILENAME, $sort_order);
                    $data = $this->getUnlabeledImageSlideshow($files, $id);
                } else {
                    $data = $this->getUserDefinedImageSlideshow($labels, $id);
                }
                break;
            case SHOWPLUS_SORT_LABELS_OR_MTIME:
                if (empty($labels)) {
                    $files = ShowPlusUtility::scandirsorted($path, SHOWPLUS_MTIME, $sort_order);
                    $data = $this->getUnlabeledImageSlideshow($files, $id);
                } else {
                    $data = $this->getUserDefinedImageSlideshow($labels, $id);
                }
                break;
            case SHOWPLUS_SORT_MTIME:
                $files = ShowPlusUtility::scandirsorted($path, SHOWPLUS_MTIME, $sort_order);
                $data = $this->getLabeledImageSlideshow($files, $labels, $id);
                break;
            case SHOWPLUS_SORT_RANDOM:
                $files = @scandir($path);
                // if (!empty($files)) { shuffle($files); }
                $data = $this->getLabeledImageSlideshow($files, $labels, $id);
                break;
            case SHOWPLUS_SORT_RANDOMLABELS:
                if (empty($labels)) {  // there is no labels file to use
                    $files = @scandir($path);
                    // if (!empty($files)) { shuffle($files); }
                    $data = $this->getUnlabeledImageSlideshow($files, $id);
                } else {
                    // shuffle($labels);
                    $data = $this->getUserDefinedImageSlideshow($labels, $id);
                }
                break;
            default:  // case SHOWPLUS_SORT_FILENAME:
                $files = ShowPlusUtility::scandirsorted($path, SHOWPLUS_FILENAME, $sort_order);
                $data = $this->getLabeledImageSlideshow($files, $labels, $id);
                break;
        }

        if (empty($data)) {
            $html = JText::_('SHOWPLUS_EMPTY');
        } else {
            // add navigation links below slideshow
            if ($this->params->links) {
                $navigationlinks = '<div class="showplus-navigation"><a class="showplus-previous" href="#">'.JText::_('SHOWPLUS_PREVIOUS').'</a> &#x25C1; | &#x25B7; <a class="showplus-next" href="#">'.JText::_('SHOWPLUS_NEXT').'</a></div>';
            } else {
                $navigationlinks = '';
            }

            // generate HTML for images (used only when no JavaScript is available)
            $imagehtml = array();
            $imagehtml[] = '<ul class="showplus-slideshow">';
            foreach ($data as $i => $imagedata) {
                $imagehtml[] = '<li id="'.$id.':'.($i + 1).'">';
                if ($imagedata->hyperlink) {
                    $imagehtml[] = '<a href="'.$imagedata->hyperlink.'">';
                }
                $imagehtml[] = '<img src="'.$imagedata->imageurl.'" alt="'.htmlspecialchars(strip_tags($imagedata->caption)).'" />';
                if ($imagedata->hyperlink) {
                    $imagehtml[] = '</a>';
                }
                $imagehtml[] = '</li>';
            }

            // generate HTML for default image (used only when no JavaScript is available)
            $imagedata = reset($data);
            $imagehtml[] = '<li class="showplus-default">';
            if ($imagedata->hyperlink) {
                $imagehtml[] = '<a href="'.$imagedata->hyperlink.'">';
            }
            $imagehtml[] = '<img src="'.$imagedata->imageurl.'" alt="'.htmlspecialchars(strip_tags($imagedata->caption)).'" />';
            if ($imagedata->hyperlink) {
                $imagehtml[] = '</a>';
            }
            $imagehtml[] = '</li>';
            $imagehtml[] = '</ul>';

            // generate HTML for navigation bar (used only when no JavaScript is available)
            $navigationpages = array();
            foreach ($data as $i => $imagedata) {
                $navigationpages[] = '<a href="#'.$id.':'.($i + 1).'">'.($i + 1).'</a>';  // link to image, show/hide using CSS pseudo-element ":target"
            }
            $navigationpager = '<p class="showplus-pager">'.implode(' | ', $navigationpages).'</p>';

            // produce HTML
            $html = '<div id="'.$id.'" class="'.implode(' ', $classes).'">'.implode('', $imagehtml).$navigationpager.$navigationlinks.'</div>';
        }

        $this->addHeadDeclarations($id, $data);

        return $html;
    }

    /**
    * Ensures that an identifier is unique across the page.
    * An identifier is specified by the user or generated from the relative image source path. Other extensions,
    * however, may duplicate article content on the page (e.g. show a short article extract as part of a blog layout),
    * making an identifier no longer unique. This function adds an ordinal to prevent conflicts when the same content
    * would occur multiple times on the page, causing scripts not to function properly.
    */
    private static function getUniqueId($id) {
        static $ids = array();

        if (in_array($id, $ids)) {  // look for identifier in script-lifetime container
            $counter = 1000;
            do {
                $counter++;
                $gid = $id.'_'.$counter;
            } while (in_array($gid, $ids));
            $id = $gid;
        }
        $ids[] = $id;
        return $id;
    }

    /**
    * Generates an image slideshow entirely defined with a list of filenames or a list of label objects.
    * @param list An array of filenames and/or label objects.
    */
    private function getUserDefinedImageSlideshow(array $list, $id) {
        $this->createThumbnailImages($list);

        $data = array();
        foreach ($list as $index => $listitem) {
            if (is_string($listitem)) {
                $data[] = $this->getImageData($id, $index, $listitem);
            } else {
                $data[] = $this->getImageData($id, $index, $listitem->imagefile, $listitem);
            }
        }
        return $data;
    }

    /**
    * Generates an image slideshow where some files have labels.
    */
    private function getLabeledImageSlideshow(array $files, array $labels, $id) {
        if (empty($files)) {
            return false;
        }
        $labelmap = array();
        foreach ($labels as $label) {  // enumerate images listed in labels.txt
            $labelmap[$label->imagefile] = $label;
        }
        $files = array_values(array_filter($files, array('ShowPlusUtility', 'is_image_file')));
        $this->createThumbnailImages($files);

        $data = array();
        foreach ($files as $index => $file) {
            $data[] = $this->getImageData($id, $index, $file, isset($labelmap[$file]) ? $labelmap[$file] : null);
        }
        return $data;
    }

    /**
    * Generates an image slideshow where files have no labels.
    */
    private function getUnlabeledImageSlideshow(array $files, $id) {
        return $this->getLabeledImageSlideshow($files, array(), $id);
    }

    /**
    * Returns HTML code for an image in a gallery list.
    */
    private function getImageData($id, $index, $imagefile, $label = null) {
        // get thumbnail image URL
        if ($this->params->thumb_cache) {
            $thumbbase = 'cache';
            $imagepath = JPATH_ROOT.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $this->params->folder.'/'.$imagefile);
            $thumbfile = md5('showplus:'.$this->params->thumb_width.'x'.$this->params->thumb_height.':'.$this->params->thumb_quality.':'.$imagepath).'.'.pathinfo($imagefile, PATHINFO_EXTENSION);
        } else {
            $thumbbase = $this->params->folder;
            $thumbfile = $imagefile;
        }
        $thumburl = JURI::base(true).'/'.$thumbbase.'/'.$this->params->thumb_folder.'/'.$thumbfile;

        // get original image URL
        $imageurl = JURI::base(true).'/'.$this->params->folder.'/'.$imagefile;

        // get image caption
        $imagecaption = $this->params->defcaption;
        $hyperlink = $this->params->deflink ? str_replace('{$index}', $index, $this->params->deflink) : false;
        if ($label) {
            if ($label instanceof ShowPlusImageLabel) {
                if ($label->caption) {
                    $imagecaption = $label->caption;
                }
                if ($label->hyperlink) {
                    $hyperlink = $label->hyperlink;
                }
            } elseif (is_string($label)) {
                $imagecaption =  $label;
            }
        }

        // return data
        return new ShowPlusImageData($imageurl, $thumburl, $hyperlink, $imagecaption);
    }

    private static function getResourceURL($relativeDirectory, $name, $extension) {
        $absoluteDirectory = JPATH_ROOT.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativeDirectory);
        $minifiedFile = $name.'.min'.$extension;
        $standardFile = $name.$extension;
        if (!self::$debug && file_exists($absoluteDirectory.DIRECTORY_SEPARATOR.$minifiedFile)) {
            $path = $relativeDirectory.'/'.$minifiedFile;
        } else {
            $path = $relativeDirectory.'/'.$standardFile;
        }
        return JURI::base(true).'/'.$path;
    }

    /**
    * Adds style and script declarations for an image slideshow.
    */
    private function addHeadDeclarations($id, $data = null) {
        $document = JFactory::getDocument();
        $language = JFactory::getLanguage();

        // add style and script imports
        $document->addStyleSheet(self::getResourceURL('media/mod_showplus/css', 'showplus', '.css'));
        $document->addStyleSheet(self::getResourceURL('media/mod_showplus/css', 'showplusx', '.css'));
        $document->addScript(self::getResourceURL('media/mod_showplus/js', 'showplusx', '.js'), array(), array('defer' => true));

        // set navigation control button visibility
        if ($this->params->buttons === false) {
            $selectors["#{$id} .showplusx-previous, #{$id} .showplusx-next"] = array('display' => 'none');
        }

        // add inline style declarations based on back-end settings
        $rules = array();
        if ($this->params->margin !== false) {
            $rules['margin-top'] = $this->params->margin.'px !important';
            $rules['margin-bottom'] = $this->params->margin.'px !important';
            $alignment = str_replace(array('before','after'), $language->isRTL() ? array('right','left') : array('left','right'), $this->params->alignment);
            switch ($alignment) {
                case 'left-float':
                    $rules['margin-right'] = $this->params->margin.'px !important'; break;
                case 'right-float':
                    $rules['margin-left'] = $this->params->margin.'px !important'; break;
            }
        }
        if ($this->params->border_width !== false && $this->params->border_style !== false && $this->params->border_color !== false) {
            $rules['border'] = $this->params->border_width.'px '.$this->params->border_style.' '.$this->params->border_color;
        } else {
            if ($this->params->border_width !== false) {
                $rules['border-width'] = $this->params->border_width.'px';
            }
            if ($this->params->border_style !== false) {
                $rules['border-style'] = $this->params->border_style;
            }
            if ($this->params->border_color !== false) {
                $rules['border-color'] = $this->params->border_color;
            }
        }
        if ($this->params->padding !== false) {
            $rules['padding'] = $this->params->padding.'px';
        }
        $selectors["#{$id}"] = $rules;
        $selectors["#{$id} > .showplusx-slideshow"] = array(
            'width' => $this->params->width,
            'height' => $this->params->height
        );

        // background color for transparent preview images
        if ($this->params->background_color !== false) {
            $selectors["#{$id} .showplusx-item"] = array(
                'background-color' => $this->params->background_color
            );
        }

        // transition animation
        $transition = $this->params->transition_easing.'-'.$this->params->transition_timing;
        switch ($transition) {
            case 'linear':
                $func = 'linear'; break;
            case 'quad-in':  // http://easings.net/#easeInQuad
                $func = 'cubic-bezier(0.55, 0.085, 0.68, 0.53)'; break;
            case 'quad-out':  // http://easings.net/#easeOutQuad
                $func = 'cubic-bezier(0.25, 0.46, 0.45, 0.94)'; break;
            case 'quad-in:out':  // http://easings.net/#easeInOutQuad
                $func = 'cubic-bezier(0.455, 0.03, 0.515, 0.955)'; break;
            case 'cubic-in':  // http://easings.net/#easeInCubic
                $func = 'cubic-bezier(0.55, 0.055, 0.675, 0.19)'; break;
            case 'cubic-out':  // http://easings.net/#easeOutCubic
                $func = 'cubic-bezier(0.215, 0.61, 0.355, 1)'; break;
            case 'cubic-in:out':  // http://easings.net/#easeInOutCubic
                $func = 'cubic-bezier(0.645, 0.045, 0.355, 1)'; break;
            case 'quart-in':  // http://easings.net/#easeInQuart
                $func = 'cubic-bezier(0.895, 0.03, 0.685, 0.22)'; break;
            case 'quart-out':  // http://easings.net/#easeOutQuart
                $func = 'cubic-bezier(0.165, 0.84, 0.44, 1)'; break;
            case 'quart-in:out':  // http://easings.net/#easeInOutQuart
                $func = 'cubic-bezier(0.77, 0, 0.175, 1)'; break;
            case 'quint-in':  // http://easings.net/#easeInQuint
                $func = 'cubic-bezier(0.755, 0.05, 0.855, 0.06)'; break;
            case 'quint-out':  // http://easings.net/#easeOutQuint
                $func = 'cubic-bezier(0.23, 1, 0.32, 1)'; break;
            case 'quint-in:out':  // http://easings.net/#easeInOutQuint
                $func = 'cubic-bezier(0.86, 0, 0.07, 1)'; break;
            case 'expo-in':  // http://easings.net/#easeInExpo
                $func = 'cubic-bezier(0.95, 0.05, 0.795, 0.035)'; break;
            case 'expo-out':  // http://easings.net/#easeOutExpo
                $func = 'cubic-bezier(0.19, 1, 0.22, 1)'; break;
            case 'expo-in:out':  // http://easings.net/#easeInOutExpo
                $func = 'cubic-bezier(1, 0, 0, 1)'; break;
            case 'circ-in':  // http://easings.net/#easeInCirc
                $func = 'cubic-bezier(0.6, 0.04, 0.98, 0.335)'; break;
            case 'circ-out':  // http://easings.net/#easeOutCirc
                $func = 'cubic-bezier(0.075, 0.82, 0.165, 1)'; break;
            case 'circ-in:out':  // http://easings.net/#easeInOutCirc
                $func = 'cubic-bezier(0.785, 0.135, 0.15, 0.86)'; break;
            case 'sine-in':  // http://easings.net/#easeInSine
                $func = 'cubic-bezier(0.47, 0, 0.745, 0.715)'; break;
            case 'sine-out':  // http://easings.net/#easeOutSine
                $func = 'cubic-bezier(0.39, 0.575, 0.565, 1)'; break;
            case 'sine-in:out':  // http://easings.net/#easeInOutSine
                $func = 'cubic-bezier(0.445, 0.05, 0.55, 0.95)'; break;
            case 'back-in':  // http://easings.net/#easeInBack
                $func = 'cubic-bezier(0.6, -0.28, 0.735, 0.045)'; break;
            case 'back-out':  // http://easings.net/#easeOutBack
                $func = 'cubic-bezier(0.175, 0.885, 0.32, 1.275)'; break;
            case 'back-in:out':  // http://easings.net/#easeInOutBack
                $func = 'cubic-bezier(0.68, -0.55, 0.265, 1.55)'; break;
            case 'bounce':  // not supported in CSS
            case 'elastic':  // not supported in CSS
            default:
                $func = 'linear'; break;
        }
        $selectors["#{$id} .showplusx-animation-in, #{$id} .showplusx-animation-out"] = array(
            'animation-timing-function' => $func,
            'animation-duration' => $this->params->duration.'ms'
        );
        $selectors["#{$id} .showplusx-animation-show"] = array(
            'animation-duration' => $this->params->delay.'ms'
        );

        $css = '';
        foreach ($selectors as $selector => $rules) {
            if (!empty($rules)) {
                $css .= $selector." { ";
                foreach ($rules as $name => $value) {
                    $css .= $name.':'.$value.'; ';
                }
                $css .= "}\n";
            }
        }
        $document->addStyleDeclaration($css);

        // set slideshow image option defaults
        $defaults = array();
        if ($this->params->defcaption) {  // default caption to use if none is set
            $defaults['title'] = $this->params->defcaption;
        }
        if ($this->params->deflink) {  // default link target to use if none is set
            $defaults['href'] = $this->params->deflink;
        }
        if ($this->params->target) {
            $defaults['target'] = $this->params->target;
        }
        if ($this->params->captions) {
            $defaults['caption'] = 'bottom';
        }
        $options = array(
            'defaults' => $defaults
        );

        // add images
        if (!empty($data)) {
            $items = array();
            foreach ($data as $imagedata) {
                $item = array();
                $item['src'] = $imagedata->imageurl;
                if ($imagedata->caption) {
                    $item['title'] = $imagedata->caption;
                }
                if ($imagedata->hyperlink) {
                    $item['href'] = $imagedata->hyperlink;
                }
                if ($imagedata->thumburl) {
                    $item['thumbsrc'] = $imagedata->thumburl;
                }
                $items[] = $item;
            }
            $options['items'] = $items;
        }

        // set order in which images are shown
        switch ($this->params->sort_criterion) {
            case SHOWPLUS_SORT_RANDOM:
            case SHOWPLUS_SORT_RANDOMLABELS:
                $options['order'] = 'random';
                break;
        }

        // set up quick-access navigation bar
        if ($this->params->orientation !== false) {
            switch ($this->params->orientation) {
                case 'horizontal':
                case 'horizontal-bottom':
                    $navigation = 'bottom';
                    break;
                case 'horizontal-top':
                    $navigation = 'top';
                    break;
                case 'vertical':
                case 'vertical-before':
                    $navigation = 'start';
                    break;
                case 'vertical-after':
                    $navigation = 'end';
                    break;
            }
            $options['navigation'] = $navigation;
        }

        // set animation effects used to morph one image into another
        $effects = array();
        switch ($this->params->transition) {
            case 'circle':
                $effects = array('circle');
                break;
            case 'fade':
                $effects = array('fade');
                break;
            case 'fold':
                $effects = array('fold-left','fold-right');
                break;
            case 'push':
                $effects = array('push-left','push-right','push-top','push-bottom');
                break;
            case 'kenburns':
                $effects = array('kenburns-topleft','kenburns-topright','kenburns-bottomright','kenburns-bottomleft');
                break;
        }
        if (!empty($effects)) {
            $options['effects'] = $effects;
        }

        if ($language->isRTL()) {
            $options['dir'] = 'rtl';
        }

        // add initialization script
        if ($this->params->links) {
            $bindings =
                "container.querySelector('a.showplus-previous').addEventListener('click', function (event) {".
                    "slideshow.previous();".
                    "event.preventDefault();".
                "}, false);".
                "container.querySelector('a.showplus-next').addEventListener('click', function (event) {".
                    "slideshow.next();".
                    "event.preventDefault();".
                "}, false);";
        } else {
            $bindings = '';
        }

        if (is_array($this->params->options)) {
            $options = array_merge($options, $this->params->options);
        }

        $jsonoptions = json_encode($options);
        $document->addScriptDeclaration(
            "document.addEventListener('DOMContentLoaded', function () {".
                "document.documentElement.classList.add('showplus-js');".
                "var container = document.getElementById('{$id}');".
                "var slideshow = new ShowPlusXSlideshow(container, {$jsonoptions});".
                $bindings.
            "});"
        );
    }

    //
    // Thumbnail image generation
    //

    /**
    * Pre-generates a set of thumbnail images.
    * @param list A list of original image filenames, or a list of ShowPlusImageLabel instances.
    */
    private function createThumbnailImages(array $list) {
        if ($this->params->orientation !== false) {  // navigation bar with image thumbnails is enabled
            if ($this->params->thumb_cache) {
                $imagedirectory = JPATH_CACHE.DIRECTORY_SEPARATOR.$this->params->thumb_folder;
            } else {
                $imagedirectory = JPATH_ROOT.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $this->params->folder).DIRECTORY_SEPARATOR.$this->params->thumb_folder;
            }
            ShowPlusUtility::make_directory($imagedirectory);  // create thumbnail image folder if necessary

            foreach ($list as $listitem) {
                $this->createThumbnailImage($imagedirectory, is_string($listitem) ? $listitem : $listitem->imagefile);
            }
        }
    }

    /**
    * Creates a thumbnail image for an original.
    * Images are generated only if they do not already exist.
    */
    private function createThumbnailImage($imagedirectory, $imagefile) {
        $imagepath = JPATH_ROOT.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $this->params->folder.'/'.$imagefile);

        require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'thumbs.php';
        $imagelibrary = ShowPlusImageLibrary::instantiate($this->params->library);

        // create thumbnail image
        if ($this->params->thumb_cache) {
            $imagehashedname = md5('showplus:'.$this->params->thumb_width.'x'.$this->params->thumb_height.':'.$this->params->thumb_quality.':'.$imagepath).'.'.pathinfo($imagefile, PATHINFO_EXTENSION);
            $previewpath = $imagedirectory.DIRECTORY_SEPARATOR.$imagehashedname;
        } else {
            $previewpath = $imagedirectory.DIRECTORY_SEPARATOR.$imagefile;
        }
        if (!is_file($previewpath)) {  // create image on-the-fly if not exists
            $result = $imagelibrary->createThumbnail($imagepath, $previewpath, $this->params->thumb_width, $this->params->thumb_height, true, $this->params->thumb_quality);
        }
    }

    //
    // Image labels
    //

    /**
    * Generates labels from image filenames.
    * @return A (possibly empty) array of ShowPlusImageLabel instances.
    */
    private function getLabelsFromFilenames() {
        $files = @scandir(JPATH_ROOT.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $this->params->folder));
        if ($files === false) {
            return array();
        }
        $files = array_filter($files, array('ShowPlusUtility', 'is_regular_file'));  // list files inside the specified path but omit hidden files
        $files = array_filter($files, array('ShowPlusUtility', 'is_image_file'));
        $labels = array();
        foreach ($files as $file) {
            $labels[] = new ShowPlusImageLabel($file, null, pathinfo($file, PATHINFO_FILENAME));
        }
        return $labels;
    }

    /**
    * Returns the language-specific labels filename.
    * @return File system path to the language file to use, or false if no labels file exists.
    */
    private function getLabelsFilename() {
        if ($this->params->labels_multilingual) {  // check for language-specific labels file
            $lang = JFactory::getLanguage();
            $labelsfile = JPATH_ROOT.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $this->params->folder).DIRECTORY_SEPARATOR.$this->params->labels.'.'.$lang->getTag().'.txt';
            if (is_file($labelsfile)) {
                return $labelsfile;
            }
        }
        // default to language-neutral labels file
        $labelsfile = JPATH_ROOT.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $this->params->folder).DIRECTORY_SEPARATOR.$this->params->labels.'.txt';  // filesystem path to labels file
        if (is_file($labelsfile)) {
            return $labelsfile;
        }
        return false;
    }

    /**
    * Short captions attached to images with a "labels.txt" file.
    * @return An array of ShowPlusImageLabel instances, or an empty array of no "labels.txt" file is found.
    */
    private function getLabelsFromExternalFile() {
        $labelsfile = $this->getLabelsFilename();
        if ($labelsfile === false) {
            return array();
        }
        $labels = array();
        $contents = file_get_contents($labelsfile);
        if (!strcmp("\xEF\xBB\xBF", substr($contents,0,3))) {  // file starts with UTF-8 BOM
            $contents = substr($contents, 3);  // remove UTF-8 BOM
        }
        return $this->getLabelsFromString($contents);
    }

    /**
    * Short captions attached to images where the labels source is a string.
    * @return An array of ShowPlusImageLabel instances, or an empty array.
    */
    private function getLabelsFromString($contents) {
        $contents = str_replace("\r", "\n", $contents);  // normalize line endings
        $matches = array();
        preg_match_all('/^([^|\r\n]+)(?:[|]([^|\r\n]*)(?:[|]([^\r\n]*))?)?$/mu', $contents, $matches, PREG_SET_ORDER);
        switch (preg_last_error()) {
            case PREG_BAD_UTF8_ERROR:
                throw new ShowPlusEncodingException($labelsfile);
        }
        $labels = array();
        foreach ($matches as $match) {
            $imagefile = $match[1];
            $hyperlink = false;
            $caption = false;
            switch (count($match) - 1) {
                case 3:
                    $hyperlink = $match[2];
                    $caption = html_entity_decode($match[3], ENT_QUOTES, 'UTF-8');
                    break;
                case 2:
                    if (preg_match('/^(?:https?|ftps?|javascript):/', $match[2])) {  // looks like a URL
                        $hyperlink = $match[2];
                    } else {
                        $caption = html_entity_decode($match[2], ENT_QUOTES, 'UTF-8');
                    }
                    break;
            }

            if ($imagefile == '*') {  // set default label
                if ($hyperlink) {
                    $this->params->deflink = $hyperlink;
                }
                if ($caption) {
                    $this->params->defcaption = $caption;
                }
            } else {
                if (ShowPlusUtility::is_remote_path($imagefile)) {  // a URL to a remote image
                    $imagefile = ShowPlusUtility::safeurlencode($imagefile);
                } else {  // a local image
                    $imagefile = ShowPlusUtility::file_exists_lenient($this->params->folder.DIRECTORY_SEPARATOR.$imagefile);
                    if ($imagefile === false) {  // check that image file truly exists
                        continue;
                    }
                }
                $labels[] = new ShowPlusImageLabel($imagefile, $hyperlink, $caption);
            }
        }
        return $labels;
    }
}
