<?php
/**
 * @package J2Store
 * @copyright Copyright (c)2014-17 Ramesh Elamathi / J2Store.org
 * @license GNU GPL v3 or later
 *
 * Bootstrap 2 layout of product detail
 */
// No direct access
defined('_JEXEC') or die;
$columns = $this->params->get('item_related_product_columns', 3);
$total = count($this->cross_sells); $counter = 0;
$cross_image_width = $this->params->get('item_product_cross_image_width', '100');
?>

<div class="related products">

<h3> You May Also Like </h3>

				<?php foreach($this->cross_sells as $cross_sell_product):?>
					<?php
						$cross_sell_product->product_link = JRoute::_('index.php?option=com_j2store&view=products&task=view&id='.$cross_sell_product->j2store_product_id);
						if(!empty($cross_sell_product->addtocart_text)) {
							$cart_text = JText::_($cross_sell_product->addtocart_text);
						} else {
							$cart_text = JText::_('J2STORE_ADD_TO_CART');
						}
                        $cross_product_name = $this->escape($cross_sell_product->product_name);
					?>

					<?php $rowcount = ((int) $counter % (int) $columns) + 1; ?>
					<?php if ($rowcount == 1) : ?>
						<?php $row = $counter / $columns; ?>
						<div class="crosssell-product-row <?php echo 'row-'.$row; ?> row">
					<?php endif;?>
					
					<div class="col-sm-<?php echo round((12 / $columns));?> crosssell-product product-<?php echo $cross_sell_product->j2store_product_id;?> <?php echo $cross_sell_product->params->get('product_css_class','');?>">
					
					<div class="j2store-single-product">
						<div class="j2store-product-images">
						<div class="j2store-thumbnail-image">
							<a href="<?php echo $cross_sell_product->product_link; ?>">
						<?php
							$thumb_image = '';
							if(isset($cross_sell_product->thumb_image) && $cross_sell_product->thumb_image){
	      					$thumb_image = $cross_sell_product->thumb_image;
	      					}

	      				?>
		   				<?php if(isset($thumb_image) &&  JFile::exists(JPATH::clean(JPATH_SITE.'/'.$thumb_image))):?>
		   					<img title="<?php echo $cross_product_name ;?>" alt="<?php echo $cross_product_name ;?>" class="j2store-product-thumb-image-<?php echo $cross_sell_product->j2store_product_id; ?>"  src="<?php echo JUri::root().JPath::clean($thumb_image);?>" width="<?php echo intval($cross_image_width);?>"/>
					   	<?php endif; ?>

							</a>
						</div>
						</div>
		<div class="product-wrap">
						<h2 class="product-title">
							<a href="<?php echo $cross_sell_product->product_link; ?>">
								<?php echo $cross_product_name; ?>
							</a>
						</h2>

						<?php
						$this->singleton_product = $cross_sell_product;
						$this->singleton_params = $this->params;
						echo $this->loadAnyTemplate('site:com_j2store/products/price');
						?>
						<?php
							$this->singleton_product = $cross_sell_product;
							$this->singleton_params = $this->params;
							$this->singleton_cartext = $this->escape($cart_text);
							echo $this->loadAnyTemplate('site:com_j2store/products/cart');
						?>						

		</div>
								
					</div>
					</div>
				<?php $counter++; ?>
				<?php if (($rowcount == $columns) or ($counter == $total)) : ?>
					</div>
				<?php endif; ?>

			<?php endforeach;?>
  </div>