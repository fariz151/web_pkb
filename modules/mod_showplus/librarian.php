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
defined('_JEXEC') or die('Restricted access');

class ShowPlusLibrarian {
    public static function is_gd_supported() {
        $supported = extension_loaded('gd');
        if (!$supported) {
            return false;
        }

        $supported = function_exists('gd_info');  // might fail in rare cases even if GD is available
        if (!$supported) {
            return false;
        }
        $gd = gd_info();
        $supported = isset($gd['GIF Read Support']) && $gd['GIF Read Support']
                && isset($gd['GIF Create Support']) && $gd['GIF Create Support']
                && (isset($gd['JPG Support']) && $gd['JPG Support'] || isset($gd['JPEG Support']) && $gd['JPEG Support'])
                && isset($gd['PNG Support']) && $gd['PNG Support'];
        return $supported;
    }

    public static function is_imagick_supported() {
        $supported = extension_loaded('imagick');
        if (!$supported) {
            return false;
        }

        $supported = class_exists('Imagick');
        return $supported;
    }
}