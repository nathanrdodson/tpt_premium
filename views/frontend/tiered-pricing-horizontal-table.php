<?php
	/**
	 * Available variables
	 *
	 * @var PricingRule $pricing_rule
	 * @var array $price_rules
	 * @var string $pricing_type
	 * @var string $real_price
	 * @var string $product_name
	 * @var WC_Product $product
	 * @var string $id
	 * @var int $product_id
	 * @var int $minimum
	 * @var array $settings
	 */
	
	use TierPricingTable\CalculationLogic;
	use TierPricingTable\PriceManager;
	use TierPricingTable\PricingRule;
	
if ( ! defined( 'WPINC' ) ) {
	die;
}
	
	$sale_price = $product->get_sale_price();
	
if ( $sale_price ) {
	$sale_price = wc_get_price_to_display( $product, array(
		'price' => $sale_price,
	) );
}
	
	$regular_price = wc_get_price_to_display( $product, array(
		'price' => $product->get_regular_price(),
	) );
	
	$price = wc_get_price_to_display( $product, array(
		'price' => $product->get_price(),
	) );
	
	$price_incl_taxes = wc_get_price_including_tax( wc_get_product( $product_id ), array(
		'price' => $real_price,
	) );
	
	$price_excl_taxes = wc_get_price_excluding_tax( wc_get_product( $product_id ), array(
		'price' => $real_price,
	) );

	?>

<?php if ( ! empty( $pricing_rule->getRules() ) ) : ?>
	<div class="clear"></div>
	
	<div class="tiered-pricing-wrapper">
		<?php if ( ! empty( $settings['title'] ) ) : ?>
			<h3 style="clear:both; margin: 20px 0;"><?php echo esc_attr( $settings['title'] ); ?></h3>
		<?php endif; ?>
		
		<?php do_action( 'tiered_pricing_table/tiered_pricing/before', $pricing_rule ); ?>
		
		<div class="tiered-pricing-horizontal-table"
			 id="<?php echo esc_attr( $id ); ?>"
			 data-tiered-pricing-table
			 data-product-id="<?php echo esc_attr( $product_id ); ?>"
			 data-price-rules="<?php echo esc_attr( json_encode( $pricing_rule->getRules() ) ); ?>"
			 data-minimum="<?php echo esc_attr( $minimum ); ?>"
			 data-product-name="<?php echo esc_attr( $product_name ); ?>"
			 data-regular-price="<?php echo esc_attr( $regular_price ); ?>"
			 data-sale-price="<?php echo esc_attr( $sale_price ); ?>"
			 data-price="<?php echo esc_attr( $price ); ?>"
			 data-product-price-suffix="<?php echo esc_attr( $product->get_price_suffix() ); ?>">
			
			<div class="tiered-pricing-horizontal-table-column tiered-pricing-horizontal-table__labels">
				
				<?php if ( $settings['quantity_column_title'] ) : ?>
					<div class="tiered-pricing-horizontal-table-cell tiered-pricing-horizontal-table-cell--quantity">
						<strong>
							<?php echo esc_attr( $settings['quantity_column_title'] ); ?>
						</strong>
					</div>
				<?php endif; ?>
				
				<?php if ( $settings['discount_column_title'] ) : ?>
					<div class="tiered-pricing-horizontal-table-cell tiered-pricing-horizontal-table-cell--discount">
						<strong>
							<?php echo esc_attr( ( $settings['discount_column_title'] ) ); ?>
						</strong>
					</div>
				<?php endif; ?>
				
				<?php if ( $settings['price_column_title'] ) : ?>
					<div class="tiered-pricing-horizontal-table-cell tiered-pricing-horizontal-table-cell--price">
						<strong>
							<?php echo esc_attr( $settings['price_column_title'] ); ?>
						</strong>
					</div>
				<?php endif; ?>
			
			</div>
			<div
				class="tiered-pricing-horizontal-table-column tiered-pricing-horizontal-table__values tiered-pricing--active"
				data-tiered-quantity="<?php echo esc_attr( $minimum ); ?>"
				data-tiered-price="<?php echo esc_attr( $price ); ?>"
				data-tiered-price-exclude-taxes="<?php echo esc_attr( $price_excl_taxes ); ?>"
				data-tiered-price-include-taxes="<?php echo esc_attr( $price_incl_taxes ); ?>">
				
				<?php if ( $settings['quantity_column_title'] ) : ?>
					<div class="tiered-pricing-horizontal-table-cell tiered-pricing-horizontal-table-cell--quantity">
						<?php if ( 1 >= array_keys( $pricing_rule->getRules() )[0] - $minimum || 'static' === $settings['quantity_type'] ) : ?>
							<span>
								<span>
									<?php echo esc_attr( number_format_i18n( $minimum ) ); ?>
								</span>
								<span>
									<?php echo esc_attr( ' ' . $settings['quantity_measurement_singular'] ); ?>
								</span>
							</span>
						<?php else : ?>
							<span>
								<span>
									<?php echo esc_attr( number_format_i18n( $minimum ) ); ?> - <?php echo esc_attr( number_format_i18n( array_keys( $pricing_rule->getRules() )[0] - 1 ) ); ?>
								</span>
								<span>
									<?php echo esc_attr( ' ' . $settings['quantity_measurement_plural'] ); ?>
								</span>
							</span>
						<?php endif; ?>
					</div>
				<?php endif; ?>
				
				<?php if ( $settings['discount_column_title'] ) : ?>
					
					<?php
					$discountAmount = 0;
					if ( CalculationLogic::calculateDiscountBasedOnRegularPrice() && $product->is_on_sale() ) {
						$discountAmount = PriceManager::calculateDiscount( $product->get_regular_price(),
							$product->get_sale_price() );
					}
					?>
					
					<div class="tiered-pricing-horizontal-table-cell tiered-pricing-horizontal-table-cell--discount">
						<?php if ( $discountAmount > 0 ) : ?>
							<span><?php echo esc_attr( round( $discountAmount, 2 ) ); ?> %</span>
						<?php else : ?>
							<span>—</span>
						<?php endif; ?>
					</div>
				<?php endif; ?>
				
				<?php if ( $settings['price_column_title'] ) : ?>
					<div class="tiered-pricing-horizontal-table-cell tiered-pricing-horizontal-table-cell--price">
						<?php
							echo wp_kses_post( wc_price( wc_get_price_to_display( wc_get_product( $product_id ),
								array( 'price' => $real_price, ) ) ) );
						?>
					</div>
				<?php endif; ?>
			</div>
			
			<?php $iterator = new ArrayIterator( $pricing_rule->getRules() ); ?>
			
			<?php while ( $iterator->valid() ) : ?>
				<?php
				$currentPrice    = $iterator->current();
				$currentQuantity = $iterator->key();
				
				$iterator->next();
				
				if ( $pricing_rule->getType() === 'percentage' ) {
					$discountAmount = $currentPrice;
				} else {
					$discountAmount = PriceManager::calculateDiscount( CalculationLogic::calculateDiscountBasedOnRegularPrice() ? $product->get_regular_price() : $product->get_price(),
						$pricing_rule->getTierPrice( $currentQuantity, false ) );
				}
				
				$quantity = number_format_i18n( $currentQuantity );
				
				if ( $iterator->valid() ) {
					
					if ( intval( $iterator->key() - 1 != $currentQuantity ) && 'range' === $settings['quantity_type'] ) {
						$quantity .= ' - ' . number_format_i18n( intval( $iterator->key() - 1 ) );
					}
					
				} else {
					$quantity .= apply_filters( 'tiered_pricing_table/tiered_pricing/last_tier_postfix', '+',
						$currentQuantity, $pricing_rule, 'table' );
				}
				
				$quantity .= ' ' . $settings['quantity_measurement_plural'];
				
				$currentProductPrice = $pricing_rule->getTierPrice( $currentQuantity );
				
				$currentProductPriceExcludeTaxes = wc_get_price_excluding_tax( wc_get_product( $product_id ), array(
					'price' => $pricing_rule->getTierPrice( $currentQuantity, false ),
				) );
				
				$currentProductPriceIncludeTaxes = wc_get_price_including_tax( wc_get_product( $product_id ), array(
					'price' => $pricing_rule->getTierPrice( $currentQuantity, false ),
				) );
				
				?>
				<div class="tiered-pricing-horizontal-table-column tiered-pricing-horizontal-table__values"
					 data-tiered-quantity="<?php echo esc_attr( $currentQuantity ); ?>"
					 data-tiered-price="<?php echo esc_attr( $currentProductPrice ); ?>"
					 data-tiered-price-exclude-taxes="<?php echo esc_attr( $currentProductPriceExcludeTaxes ); ?>"
					 data-tiered-price-include-taxes="<?php echo esc_attr( $currentProductPriceIncludeTaxes ); ?>">
					
					<?php if ( $settings['quantity_column_title'] ) : ?>
						<div
							class="tiered-pricing-horizontal-table-cell tiered-pricing-horizontal-table-cell--quantity">
							<span><?php echo esc_attr( $quantity ); ?></span>
						</div>
					<?php endif; ?>
					
					<?php if ( $settings['discount_column_title'] ) : ?>
						<div
							class="tiered-pricing-horizontal-table-cell tiered-pricing-horizontal-table-cell--discount">
							<?php if ( $discountAmount > 0 ) : ?>
								<span><?php echo esc_attr( round( $discountAmount, 2 ) ); ?> %</span>
							<?php else : ?>
								<span>—</span>
							<?php endif; ?>
						</div>
					<?php endif; ?>
					
					<?php if ( $settings['price_column_title'] ) : ?>
						<div class="tiered-pricing-horizontal-table-cell tiered-pricing-horizontal-table-cell--price">
							<?php
								echo wp_kses_post( wc_price( $pricing_rule->getTierPrice( $currentQuantity ) ) );
							?>
						</div>
					<?php endif; ?>
				</div>
			<?php endwhile; ?>
		</div>
		
		<?php do_action( 'tiered_pricing_table/tiered_pricing/after', $pricing_rule ); ?>
		
		<script>
			(function () {

				function setHighestHeight($el) {

					if ($el.length < 1) {
						return;
					}

					let highestHeight = parseInt($el.first().css('height'));

					$el.each(function () {

						if (parseInt(jQuery(this).css('height')) > highestHeight) {
							highestHeight = parseInt(jQuery(this).css('height'));
						}
					});

					$el.css('height', highestHeight + 'px');
				}

				setHighestHeight(jQuery('.tiered-pricing-horizontal-table-cell--quantity'))
				setHighestHeight(jQuery('.tiered-pricing-horizontal-table-cell--discount'));
				setHighestHeight(jQuery('.tiered-pricing-horizontal-table-cell--price'));
			})();
		</script>
	</div>
	
	<style>
		<?php
		if ( $settings['clickable_rows'] && tpt_fs()->can_use_premium_code()) {
			echo esc_attr('#' . $id) . ' .tiered-pricing-horizontal-table__values {cursor: pointer; }';
			echo esc_attr('#' . $id) . ' .tiered-pricing-horizontal-table__values:hover { background: #f5f5f5; }';
		}
		?>
		
		<?php echo esc_attr('#' . $id); ?>
		.tiered-pricing--active, .tiered-pricing--active td {
			background-color: <?php echo esc_attr($settings['active_tier_color']); ?> !important;
			color: #fff;
		}
	</style>
<?php endif; ?>