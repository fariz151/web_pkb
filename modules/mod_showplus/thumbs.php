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
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'librarian.php';
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'exception.php';

class ShowPlusImageLibrary {
    /**
    * Generates a thumbnail image for an original image.
    */
    public function createThumbnail($imagepath, $thumbpath, $thumb_w, $thumb_h, $crop = true, $quality = 85) {
        throw new ShowPlusLibraryUnavailableException();
    }

    /**
    * Generates a watermarked image for an original image.
    * @param imagepath The full path to the image to place a watermark into.
    * @param watermarkpath The full path to the image to use as a watermark.
    * @param watermarkedimagepath The full path where the watermarked image should be written.
    */
    public function createWatermarked($imagepath, $watermarkpath, $watermarkedimagepath, $params) {
        throw new ShowPlusLibraryUnavailableException();
    }

    public static function instantiate($library) {
        if ($library == 'default') {
            if (ShowPlusLibrarian::is_imagick_supported()) {
                $library = 'imagick';
            } else {
                $library = 'gd';
            }
        }
        switch ($library) {
            case 'imagick':
                if (ShowPlusLibrarian::is_imagick_supported()) {
                    return new ShowPlusImageLibraryImagick();
                }
            case 'gd':
                if (ShowPlusLibrarian::is_gd_supported()) {
                    return new ShowPlusImageLibraryGD();
                }
        }
        return new ShowPlusImageLibrary();  // all operations will throw an image library unavailable exception
    }

    /**
    * Checks whether sufficient memory is available to load and process an image.
    */
    protected function checkMemory($imagepath) {
        $memory_available = ShowPlusUtility::memory_get_available();
        if ($memory_available !== false) {
            $imagedata = getimagesize($imagepath);
            if ($imagedata === false) {
                return;
            }
            if (!isset($imagedata['channels'])) {  // assume RGB (i.e. 3 channels)
                $imagedata['channels'] = 3;
            }
            if (!isset($imagedata['bits'])) {  // assume 8 bits per channel
                $imagedata['bits'] = 8;
            }

            $memory_required = (int)ceil($imagedata[0] * $imagedata[1] * $imagedata['channels'] * $imagedata['bits'] / 8);

            if ($memory_required >= $memory_available) {
                throw new ShowPlusOutOfMemoryException($memory_required, $memory_available, $imagepath);
            }
        }
    }

    protected function computeCoordinates($params, $width, $height, $w, $h) {
        $position = isset($params['position']) ? $params['position'] : false;
        $x = isset($params['x']) ? $params['x'] : 0;
        $y = isset($params['y']) ? $params['y'] : 0;
        $centerx = floor(($width - $w) / 2);
        $centery = floor(($height - $h) / 2);
        switch ($position) {
            case 'nw': break;
            case 'n':  $x = $centerx; break;
            case 'ne': $x = $width - $w - $x; break;
            case 'w':  $y = $centery; break;
            case 'c':  $x = $centerx; $y = $centery; break;
            case 'e':  $y = $centery; $x = $width - $w - $x; break;
            case 'sw': $y = $height - $h - $y; break;
            case 's':  $x = $centerx; $y = $height - $h - $y; break;
            case 'se': $x = $width - $w - $x; $y = $height - $h - $y; break;
            default:   $y = $height - $h - $y; break;
        }
        return array($x, $y);
    }
}

class ShowPlusImageLibraryGD extends ShowPlusImageLibrary {
    /**
    * Creates an in-memory image from a local or remote image.
    * @param imagepath The absolute path to a local image or the URL to a remote image.
    */
    private function imageFromFile($imagepath) {
        $ext = strtolower(pathinfo($imagepath, PATHINFO_EXTENSION));
        switch ($ext) {
            case 'jpg': case 'jpeg':
                return @imagecreatefromjpeg($imagepath);
            case 'gif':
                return @imagecreatefromgif($imagepath);
            case 'png':
                return @imagecreatefrompng($imagepath);
            default:
                return false;  // missing or unrecognized extension
        }
    }

    /**
    * Exports an in-memory image to a local image file.
    * @param imagepath The absolute path to a local image.
    * @param image In-memory image to export.
    * @param quality Quality measure between 0 and 100 for JPEG compression.
    */
    private function imageToFile($imagepath, $image, $quality) {
        $ext = strtolower(pathinfo($imagepath, PATHINFO_EXTENSION));
        switch ($ext) {
            case 'jpg': case 'jpeg':
                return imagejpeg($image, $imagepath, $quality);
            case 'gif':
                return imagegif($image, $imagepath);
            case 'png':
                return imagepng($image, $imagepath, 9);
            default:
                return false;  // missing or unrecognized extension
        }
    }

    public function createThumbnail($imagepath, $thumbpath, $thumb_w, $thumb_h, $crop = true, $quality = 85) {
        // check memory requirement for operation
        $this->checkMemory($imagepath);

        // load image
        $source_img = $this->imageFromFile($imagepath);
        if (!$source_img) {
            return false;  // could not create image from file
        }

        // get dimensions for cropping and resizing
        $orig_w = imagesx($source_img);
        $orig_h = imagesy($source_img);
        if (false && $thumb_w >= $orig_w && $thumb_h >= $orig_h) {  // nothing to do
            $thumb_img = $source_img;
        } else {
            $ratio_orig = $orig_w/$orig_h;  // width-to-height ratio of original image
            $ratio_thumb = $thumb_w/$thumb_h;  // width-to-height ratio of thumbnail image
            if ($crop) {  // resize with automatic centering, crop image if necessary
                if ($ratio_thumb > $ratio_orig) {  // crop top and bottom
                    $zoom = $orig_w / $thumb_w;  // zoom factor of original image w.r.t. thumbnail
                    $crop_h = floor($zoom * $thumb_h);
                    $crop_w = $orig_w;
                    $crop_x = 0;
                    $crop_y = floor(0.5 * ($orig_h - $crop_h));
                } else {  // crop left and right
                    $zoom = $orig_h / $thumb_h;  // zoom factor of original image w.r.t. thumbnail
                    $crop_h = $orig_h;
                    $crop_w = floor($zoom * $thumb_w);
                    $crop_x = floor(0.5 * ($orig_w - $crop_w));
                    $crop_y = 0;
                }
            } else {  // resize with fitting larger dimension, do not crop image
                $crop_w = $orig_w;
                $crop_h = $orig_h;
                $crop_x = 0;
                $crop_y = 0;
                if ($ratio_thumb > $ratio_orig) {  // fit height
                    $zoom = $orig_h / $thumb_h;
                    $thumb_w = floor($orig_w / $zoom);
                } else {  // fit width
                    $zoom = $orig_w / $thumb_w;
                    $thumb_h = floor($orig_h / $zoom);
                }
            }

            $thumb_img = imagecreatetruecolor($thumb_w, $thumb_h);
            $result = imagealphablending($thumb_img, false) && imagesavealpha($thumb_img, true);

            if (!imageistruecolor($source_img) && ($transparentindex = imagecolortransparent($source_img)) >= 0) {
                // convert color index transparency to alpha channel transparency
                if (imagecolorstotal($source_img) > $transparentindex) {  // transparent color is in palette
                    $transparentrgba = imagecolorsforindex($source_img, $transparentindex);
                } else {  // use white as transparent background color
                    $transparentrgba = array('red' => 255, 'green' => 255, 'blue' => 255);
                }

                // fill image with transparent color
                $transparentcolor = imagecolorallocatealpha($thumb_img, $transparentrgba['red'], $transparentrgba['green'], $transparentrgba['blue'], 127);
                imagefilledrectangle($thumb_img, 0, 0, $orig_w, $orig_h, $transparentcolor);
                imagecolordeallocate($thumb_img, $transparentcolor);
            }

            // resample image into thumbnail size
            $result = $result && imagecopyresampled($thumb_img, $source_img, 0, 0, $crop_x, $crop_y, $thumb_w, $thumb_h, $crop_w, $crop_h);
            imagedestroy($source_img);

            if ($result === false) {
                imagedestroy($thumb_img);
                return false;
            }
        }

        $result = $this->imageToFile($thumbpath, $thumb_img, $quality);
        imagedestroy($thumb_img);
        return $result;
    }

    public function createWatermarked($imagepath, $watermarkpath, $watermarkedimagepath, $params) {
        // check memory requirement for operation
        $this->checkMemory($imagepath);

        // load watermark image
        $watermark_img = $this->imageFromFile($watermarkpath);
        if (!$watermark_img) {
            return false;  // could not create image from file
        }

        // load image
        $source_img = $this->imageFromFile($imagepath);
        if (!$source_img) {
            return false;  // could not create image from file
        }

        $width = imagesx($source_img);
        $height = imagesy($source_img);
        $w = imagesx($watermark_img);
        $h = imagesy($watermark_img);
        list($x, $y) = $this->computeCoordinates($params, $width, $height, $w, $h);

        imagecopy($source_img, $watermark_img, $x, $y, 0, 0, $w, $h);
        imagedestroy($watermark_img);

        $result = $this->imageToFile($watermarkedimagepath, $source_img, isset($params['quality']) ? $params['quality'] : 85);
        imagedestroy($source_img);
        return $result;
    }
}

class ShowPlusImageLibraryImagick extends ShowPlusImageLibrary {
    public function createThumbnail($imagepath, $thumbpath, $thumb_w, $thumb_h, $crop = true, $quality = 85) {
        $image = new Imagick($imagepath);
        $image->setImageCompressionQuality($quality);
        if ($crop) {  // resize with automatic centering, crop image if necessary
            $image->cropThumbnailImage($thumb_w, $thumb_h);
        } else {  // resize with fitting larger dimension, do not crop image
            $image->thumbnailImage($thumb_w, $thumb_h, true);
        }
        $result = $image->writeImage($thumbpath);
        $image->destroy();
        return $result;
    }

    public function createWatermarked($imagepath, $watermarkpath, $watermarkedimagepath, $params) {
        if (!is_file($watermarkpath)) {
            return false;
        }

        $image = new Imagick($imagepath);
        $geometry = $image->getImageGeometry();
        $width = $geometry['width'];
        $height = $geometry['height'];

        $watermark = new Imagick($watermarkpath);
        $geometry = $watermark->getImageGeometry();
        $w = $geometry['width'];
        $h = $geometry['height'];

        list($x, $y) = $this->computeCoordinates($params, $width, $height, $w, $h);

        $image->compositeImage($watermark, imagick::COMPOSITE_DEFAULT, $x, $y);
        $result = $image->writeImage($watermarkedimagepath);

        $watermark->destroy();
        $image->destroy();
        return $result;
    }
}
