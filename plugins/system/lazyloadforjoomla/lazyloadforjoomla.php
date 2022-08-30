<?php

/**
 * @copyright
 * @package     Lazy Load for Joomla! Pro - LLFJ for Joomla! 3.x
 * @author      Viktor Vogel <admin@kubik-rubik.de>
 * @version     3.5.0-FREE - 2020-06-06
 * @link        https://kubik-rubik.de/llfj-lazy-load-for-joomla
 *
 * @license     GNU/GPL
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program. If not, see <http://www.gnu.org/licenses/>.
 */
defined('_JEXEC') || die('Restricted access');

use Joomla\CMS\{Plugin\CMSPlugin, Application\CMSApplication, Factory, HTML\HTMLHelper, Uri\Uri, Document\HtmlDocument};

class PlgSystemLazyLoadForJoomla extends CMSPlugin
{
    /**
     * @var CMSApplication $app
     *
     * @since 3.0.0-FREE
     */
    protected $app;

    /**
     * @var bool $execute
     *
     * @since 3.0.0-FREE
     */
    protected $execute = false;

    /**
     * PlgSystemLazyLoadForJoomla constructor.
     *
     * @param object $subject
     * @param array  $config
     *
     * @throws Exception
     * @since 3.0.0-FREE
     */
    public function __construct(object &$subject, array $config)
    {
        $this->app = Factory::getApplication();

        if ($this->app->isClient('site')) {
            parent::__construct($subject, $config);
            $this->execute = true;
        }
    }

    /**
     * Checks the execution status in the onAfterRoute trigger
     *
     * @since 3.0.0-FREE
     */
    public function onAfterRoute()
    {
        if ($this->params->get('excludeBots')) {
            if (!$this->excludeBots()) {
                return;
            }
        }

        if ($this->params->get('excludeComponents')) {
            if (!$this->excludeComponents()) {
                return;
            }
        }

        if ($this->params->get('excludeUrls')) {
            if (!$this->excludeUrls()) {
                return;
            }
        }

        if ($this->params->get('viewsList')) {
            $this->excludeViews();
        }
    }

    /**
     * Excludes the execution for specified bots if option is selected
     *
     * @return bool
     * @since 3.0.0-FREE
     */
    private function excludeBots(): bool
    {
        $excludeBots = array_map('trim', explode(',', $this->params->get('botsList')));
        $agent = $_SERVER['HTTP_USER_AGENT'];

        foreach ($excludeBots as $excludeBot) {
            if (preg_match('@' . $excludeBot . '@i', $agent)) {
                $this->execute = false;
                break;
            }
        }

        return $this->execute;
    }

    /**
     * Excludes the execution in specified components if option is selected
     *
     * @return bool
     * @since 3.0.0-FREE
     */
    private function excludeComponents(): bool
    {
        $option = $this->app->input->getWord('option');
        $excludeComponents = array_map('trim', explode("\n", $this->params->get('excludeComponents')));
        $hit = false;

        foreach ($excludeComponents as $excludeComponent) {
            if ($option === $excludeComponent) {
                $hit = true;
                break;
            }
        }

        if ($this->params->get('excludeComponentsToggle')) {
            if ($hit === false) {
                $this->execute = false;
            }

            return $this->execute;
        }

        if ($hit === true) {
            $this->execute = false;
        }

        return $this->execute;
    }

    /**
     * Excludes the execution in specified URLs if option is selected
     *
     * @return bool
     * @since 3.0.0-FREE
     */
    private function excludeUrls(): bool
    {
        $url = Uri::getInstance()->toString();
        $excludeUrls = array_map('trim', explode("\n", $this->params->get('excludeUrls')));
        $hit = false;

        foreach ($excludeUrls as $excludeUrl) {
            if ($url === $excludeUrl) {
                $hit = true;
                break;
            }
        }

        if ($this->params->get('excludeUrlsToggle')) {
            if ($hit === false) {
                $this->execute = false;
            }

            return $this->execute;
        }

        if ($hit === true) {
            $this->execute = false;
        }

        return $this->execute;
    }

    /**
     * Stops the execution if view is loaded which is entered in the settings (e.g. tmpl=component)
     *
     * @return bool
     * @since 3.0.0-FREE
     */
    private function excludeViews(): bool
    {
        $view = $this->app->input->getWord('tmpl', '');
        $excludeViews = array_map('trim', explode(',', $this->params->get('viewsList')));

        if (in_array($view, $excludeViews)) {
            $this->execute = false;
        }

        return $this->execute;
    }

    /**
     * Do all checks whether the plugin has to be loaded and load needed JavaScript instructions
     *
     * @since 3.0.0-FREE
     */
    public function onBeforeCompileHead()
    {
        if ($this->params->get('excludeEditor')) {
            $this->excludeEditor();
        }

        if ($this->execute === true) {
            $head = [];

            HTMLHelper::_('jquery.framework');
            $head[] = '<script type="text/javascript" src="' . Uri::base() . 'plugins/system/lazyloadforjoomla/src/assets/js/lazyloadforjoomla.min.js"></script>';

            $jsCall = '<script type="text/javascript">jQuery(document).ready(function(){jQuery("img").lazyload(';

            if ($this->params->get('threshold')) {
                $jsCall .= '{threshold : 1, rootMargin : "' . (int)$this->params->get('threshold') . 'px"}';
            }

            $jsCall .= ');});</script>';

            $head[] = $jsCall;
            $head = "\n" . implode("\n", $head) . "\n";

            /** @var HtmlDocument $document */
            $document = Factory::getDocument();
            $document->addCustomTag($head);
        }
    }

    /**
     * Excludes the execution if editor class was loaded
     *
     * @since 3.0.0-FREE
     */
    private function excludeEditor()
    {
        if (class_exists('JEditor', false) || class_exists('Joomla\CMS\Editor\Editor', false)) {
            $this->execute = false;
        }
    }

    /**
     * Trigger onAfterRender executes the main plugin procedure
     *
     * @since 3.0.0-FREE
     */
    public function onAfterRender()
    {
        if ($this->execute === true) {
            $blankImage = Uri::base() . 'plugins/system/lazyloadforjoomla/src/assets/images/blank.gif';
            $patternImage = "@<img[^>]*src=[\"\']([^\"\']*)[\"\'][^>]*>@";
            $body = $inputString = $this->app->getBody(false);

            // Remove JavaScript template replacement files
            if (strpos($body, '<script type="text/template"') !== false) {
                $inputString = preg_replace('@<script type="text/template".*</script>@isU', '', $body);
            }

            preg_match_all($patternImage, $inputString, $matches);

            if ($this->params->get('excludeImageNames') && !empty($matches)) {
                $this->excludeImageNames($matches);
            }

            if ($this->params->get('imageClass') && !empty($matches)) {
                $this->processImageClass($matches);
            }

            if (!empty($matches[0])) {
                $base = Uri::base();
                $basePath = Uri::base(true);

                foreach ($matches[0] as $key => $match) {
                    if (strpos($matches[1][$key], 'http://') === false && strpos($matches[1][$key], 'https://') === false) {
                        if (!empty($basePath)) {
                            if (strpos($matches[1][$key], $basePath) === false) {
                                $match = str_replace($matches[1][$key], $basePath . '/' . $matches[1][$key], $match);
                            }
                        } elseif (strpos($matches[1][$key], $base) === false) {
                            $match = str_replace($matches[1][$key], $base . $matches[1][$key], $match);
                        }
                    }

                    $matchLazy = str_replace('src=', 'src="' . $blankImage . '" data-src=', $match);

                    if ($this->params->get('noscriptFallback')) {
                        $matchLazy .= '<noscript>' . $match . '</noscript>';
                    }

                    $body = str_replace($matches[0][$key], $matchLazy, $body);
                }

                $this->app->setBody($body);
            }
        }
    }

    /**
     * Excludes the execution in specified image names if option is selected
     *
     * @param array $matches
     *
     * @since 3.0.0-FREE
     */
    private function excludeImageNames(array &$matches)
    {
        $excludeImageNames = array_map('trim', explode("\n", $this->params->get('excludeImageNames')));
        $excludeImageNamesToggle = $this->params->get('excludeImageNamesToggle');
        $matchesTemp = [];

        foreach ($excludeImageNames as $excludeImageName) {
            $count = 0;

            foreach ($matches[1] as $match) {
                if (preg_match('@' . preg_quote($excludeImageName) . '@', $match)) {
                    if (empty($excludeImageNamesToggle)) {
                        unset($matches[0][$count]);
                    } else {
                        $matchesTemp[] = $matches[0][$count];
                    }
                }

                $count++;
            }
        }

        if ($excludeImageNamesToggle) {
            unset($matches[0]);
            $matches[0] = $matchesTemp;
        }
    }

    /**
     * Process image CSS class exclusion
     *
     * @param array $matches
     *
     * @since 3.0.0-FREE
     */
    private function processImageClass(&$matches)
    {
        $imageClass = $this->params->get('imageClass');
        $imageClassToggle = $this->params->get('imageClassToggle', false);

        foreach ($matches[0] as $key => $match) {
            $imageClassLazy = preg_match('@class=[\"\'].*' . $imageClass . '.*[\"\']@Ui', $match);

            if (empty($imageClassToggle)) {
                if (empty($imageClassLazy)) {
                    unset($matches[0][$key]);
                }

                continue;
            }

            if (!empty($imageClassLazy)) {
                unset($matches[0][$key]);
            }
        }
    }
}
