<?php
/**
 * @package     Joomla.Site
 * @subpackage  mod_articles_latest
 *
 * @copyright   Copyright (C) 2005 - 2015 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;
?>
<ul class="list-unstyled latestnews<?php echo $moduleclass_sfx; ?>">
<?php foreach ($list as $item) : ?>
	<li itemscope itemtype="https://schema.org/Article" class="media">
	<div class="media-image">
	<img src="<?php echo json_decode($item->images)->image_intro; ?>"/>
	</div>
	<div class="media-body">	
	<h4><a href="<?php echo $item->link; ?>" itemprop="url"> <?php echo $item->title; ?> </a></h4>
<p class="item-meta"><i class="far fa-clock"></i> <?php echo JHtml::_('date', $item->created, JText::_('DATE_FORMAT_LC3')); ?></p>
	</div>
	</li>
<?php endforeach; ?>
</ul>
