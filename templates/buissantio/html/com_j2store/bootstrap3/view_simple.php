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
?>
<div itemscope itemtype="https://schema.org/Product" class="product-<?php echo $this->product->j2store_product_id; ?> <?php echo $this->product->product_type; ?>-product">
	<div class="product type-product row">
		<div class="col-lg-6">
			<?php $images = $this->loadTemplate('images');
			J2Store::plugin()->event('BeforeDisplayImages', array(&$images, $this, 'com_j2store.products.view.bootstrap'));
			echo $images;
			?>
		</div>

		<div class="col-lg-6">
		

	<div class="product-simple-title-price">
	<div class="product-simple-title-sku">			
			<?php echo $this->loadTemplate('title'); ?>
			<?php if(isset($this->product->source->event->afterDisplayTitle)) : ?>
				<?php echo $this->product->source->event->afterDisplayTitle; ?>
			<?php endif;?>
			<?php echo $this->loadTemplate('sku'); ?>
	
	</div>
			
				<?php echo $this->loadTemplate('price'); ?>
			
				<?php echo $this->loadTemplate('sdesc'); ?>
	
	</div>
			


				<?php if(isset($this->product->source->event->beforeDisplayContent)) : ?>
					<?php echo $this->product->source->event->beforeDisplayContent; ?>
				<?php endif;?>
					<?php echo $this->loadTemplate('brand'); ?>
					<?php if($this->params->get('item_show_product_stock', 1) && J2Store::product()->managing_stock($this->product->variant)) : ?>
						<?php echo $this->loadTemplate('stock'); ?>
					<?php endif; ?>

			<?php if( J2Store::product()->canShowCart($this->params) ): ?>
			<form action="<?php echo $this->product->cart_form_action; ?>"
					method="post" class="j2store-addtocart-form-simple"
					id="j2store-addtocart-form-<?php echo $this->product->j2store_product_id; ?>"
					name="j2store-addtocart-form-<?php echo $this->product->j2store_product_id; ?>"
					data-product_id="<?php echo $this->product->j2store_product_id; ?>"
					data-product_type="<?php echo $this->product->product_type; ?>"
					enctype="multipart/form-data">

				<?php echo $this->loadTemplate('options'); ?>
				<?php echo $this->loadTemplate('cart'); ?>

			</form>
			<?php endif; ?>
		</div>
	</div>
	
	<?php if($this->params->get('item_use_tabs', 1)): ?>
		<?php echo $this->loadTemplate('tabs'); ?>
	<?php else: ?>
		<?php echo $this->loadTemplate('notabs'); ?>
	<?php endif; ?>

	<?php if(isset($this->product->source->event->afterDisplayContent)) : ?>
		<?php echo $this->product->source->event->afterDisplayContent; ?>
	<?php endif;?>
</div>	

	<?php if($this->params->get('item_show_product_upsells', 0) && count($this->up_sells)): ?>
			<?php echo $this->loadTemplate('upsells'); ?>
	<?php endif;?>

	<?php if($this->params->get('item_show_product_cross_sells', 0) && count($this->cross_sells)): ?>
			<?php echo $this->loadTemplate('crosssells'); ?>
	<?php endif;?>

