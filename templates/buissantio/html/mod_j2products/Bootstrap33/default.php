<?php
/**
 * @package 		J2Store
 * @copyright 	Copyright (c)2016-19 Sasi varna kumar / J2Store.org
 * @license 		GNU GPL v3 or later
 */
defined('_JEXEC') or die;
$total_cols = $params->get('number_of_coloums', 3);
$total_cols = ((int)$total_cols == 0) ? 1 : $total_cols;
$total_count = count($list); $counter = 0;
?>
<div itemscope itemtype="http://schema.org/BreadCrumbList" class="j2store-product-module j2store-product-module-list row">
	<?php if( count($list) > 0 ):?>
		<?php foreach ($list as $product_id => $product) : ?>
			<?php  $rowcount = ((int) $counter % (int) $total_cols) + 1; ?>
			<!-- single product -->

			<?php if ($rowcount == 1) : ?>
				<?php $row = $counter / $total_cols; ?>
				<div class="j2store-module-product-row <?php echo 'row-'.$row; ?> row">
			<?php endif;?>
			<div class="col-sm-<?php echo round((12 / $total_cols));?>">
				<div itemprop="itemListElement" itemscope="" itemtype="http://schema.org/Product" class="j2store product-<?php echo $product->j2store_product_id; ?>">

					<!-- product image if postion is top -->
					<?php if ($product->image_position == 'top') {
						require( __DIR__.'/default_image.php' );
					} ?>



					<div class="product-cart-section media">
						<!-- product image if postion is left -->
						<?php if ($product->image_position == 'left') {
							require( __DIR__.'/default_image.php' );
						} ?>
						
		<div class="media-body">
						
					<!-- product title -->
					<?php if($product->show_title): ?>
						<h4 itemprop="name">
							<?php if( $product->link_title ): ?>
							<a itemprop="url"
							   href="<?php echo JRoute::_( $product->module_display_link ); ?>"
							   title="<?php echo $product->product_name; ?>" >
								<?php endif; ?>

								<?php echo $product->product_name; ?>
								<?php if($product->link_title ): ?>
							</a>
						<?php endif; ?>
						</h4>
					<?php endif; ?>
					<?php if(isset($product->event->afterDisplayTitle)) : ?>
						<?php echo $product->event->afterDisplayTitle; ?>
					<?php endif;?>
					<!-- end product title -->
					
						<div class="product-cart-left-block <?php echo $img_class; ?>" >
							<!-- Product price block-->
							<?php echo J2Store::plugin()->eventWithHtml('BeforeRenderingProductPrice', array($product)); ?>
							<div itemprop="offers" itemscope itemtype="http://schema.org/Offer" class="product-price-container">
								<?php if($product->show_price && $product->show_special_price):?>
									<?php if($product->pricing->base_price != $product->pricing->price):?>
										<?php $class='';?>
										<?php if(isset($product->pricing->is_discount_pricing_available)) $class='strike'; ?>
										<div class="base-price <?php echo $class?>">
											<?php echo J2Store::product()->displayPrice($product->pricing->base_price, $product, $j2params);?>
										</div>
									<?php endif; ?>
									<div class="sale-price">
										<?php echo J2Store::product()->displayPrice($product->pricing->price, $product, $j2params);?>
									</div>
								<?php elseif ($product->show_price && !$product->show_special_price):?>
									<?php if($product->pricing->base_price != $product->pricing->price):?>
										<?php $class='';?>
										<?php if(isset($product->pricing->is_discount_pricing_available)) $class=''; ?>
										<div class="base-price <?php echo $class?>">
											<?php echo J2Store::product()->displayPrice($product->pricing->base_price, $product, $j2params);?>
										</div>
									<?php else:?>
										<div class="sale-price">
											<?php echo J2Store::product()->displayPrice($product->pricing->price, $product, $j2params);?>
										</div>
									<?php endif; ?>
								<?php elseif (!$product->show_price && $product->show_special_price):?>
									<?php if($product->pricing->base_price != $product->pricing->price):?>
										<?php $class='';?>
										<?php if(isset($product->pricing->is_discount_pricing_available)) $class=''; ?>
										<div class="base-price <?php echo $class?>">
											<?php echo J2Store::product()->displayPrice($product->pricing->price, $product, $j2params);?>
										</div>
									<?php endif; ?>
								<?php endif;?>
								<?php if($product->show_price_taxinfo ): ?>
									<div class="tax-text">
										<?php echo J2Store::product()->get_tax_text(); ?>
									</div>
								<?php endif; ?>
							</div>
							<?php echo J2Store::plugin()->eventWithHtml('AfterRenderingProductPrice', array($product)); ?>

							<?php if( $product->show_offers && isset($product->pricing->is_discount_pricing_available) && $product->pricing->base_price > 0): ?>
								<?php $discount =(1 - ($product->pricing->price / $product->pricing->base_price) ) * 100; ?>
								<?php if($discount > 0): ?>
									<div class="discount-percentage">
										<?php  echo round($discount).' % '.JText::_('J2STORE_PRODUCT_OFFER');?>
									</div>
								<?php endif; ?>
							<?php endif; ?>
							<!-- end Product price block-->

							<!-- SKU -->
							<?php if( $product->show_sku ) : ?>
								<?php if(!empty($product->variant->sku)) : ?>
									<div class="product-sku">
										<span class="sku-text"><?php echo JText::_('J2STORE_SKU')?></span>
										<span itemprop="sku" class="sku"> <?php echo $product->variant->sku; ?> </span>
									</div>
								<?php endif; ?>
							<?php endif; ?>

							<!-- STOCK -->
							<?php if($product->show_stock && J2Store::product()->managing_stock($product->variant)): ?>
								<div class="product-stock-container">
									<?php if($product->variant->availability): ?>
										<span class="<?php echo $product->variant->availability ? 'instock':'outofstock'; ?>">
						<?php echo J2Store::product()->displayStock($product->variant, $params); ?>
					</span>
									<?php else: ?>
										<span class="outofstock">
						<?php echo JText::_('J2STORE_OUT_OF_STOCK'); ?>
					</span>
									<?php endif; ?>
								</div>

								<?php if($product->variant->allow_backorder == 2 && !$product->variant->availability): ?>
									<span class="backorder-notification">
					<?php echo JText::_('J2STORE_BACKORDER_NOTIFICATION'); ?>
				</span>
								<?php endif; ?>
							<?php endif; ?>

						
						</div>
						<!-- product image if postion is right -->
						<?php if ($product->image_position == 'right') {
							require( __DIR__.'/default_image.php' );
						} ?>
					</div> <!-- end of product_cart_block -->

					<!-- intro text -->
					<?php if(isset($product->event->beforeDisplayContent) && $product->show_beforedisplaycontent) : ?>
						<?php echo $product->event->beforeDisplayContent; ?>
					<?php endif;?>

					<?php if($product->show_introtext): ?>
						<div class="product-short-description"><?php echo $product->module_introtext; ?></div>
					<?php endif; ?>
					<?php if(isset($product->event->afterDisplayContent) && $product->show_afterdisplaycontent) : ?>
						<?php echo $product->event->afterDisplayContent; ?>
					<?php endif;?>
					<!-- end intro text -->
</div>

				</div> <!-- End of ItemListElement -->
			</div> <!--  end of col -->

			<?php $counter++; ?>
			<?php if (($rowcount == $total_cols) or ($counter == $total_count)) : ?>
				</div>
			<?php endif; ?>

			<!-- end single product -->
		<?php endforeach; ?>
	<?php endif; ?>
</div>
