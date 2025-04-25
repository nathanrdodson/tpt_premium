<?php namespace TierPricingTable\Services;

use ArrayIterator;
use TierPricingTable\Core\ServiceContainerTrait;
use TierPricingTable\PriceManager;
use TierPricingTable\PricingRule;

/**
 * Class CartUpsellsService
 *
 * Service shows upsells for each cart item that has tiered pricing
 *
 * @package TierPricingTable\Services
 */
class CartUpsellsService {
	
	use ServiceContainerTrait;
	
	/**
	 * CatalogPriceManager constructor.
	 */
	public function __construct() {
		if ( tpt_fs()->can_use_premium_code__premium_only() ) {
			add_action( 'woocommerce_after_cart_item_name', array( $this, 'showUpsell' ), 1, 3 );
		}
	}
	
	public function showUpsell( $cartItem ) {
		
		if ( ! $this->isCartUpsellEnabled() ) {
			return;
		}
		
		$upsellString = $this->formatUpsellString( $cartItem );
		
		if ( ! $upsellString ) {
			return;
		}
		
		?>
		<div>
			<small style="color: <?php echo esc_attr( $this->getUpsellColor() ); ?>">
				<?php echo wp_kses_post( $upsellString ); ?>
			</small>
		</div>
		<?php
	}
	
	protected function formatUpsellString( $cartItem ) {
		
		$nextPriceData = $this->getNextPriceData( $cartItem );
		
		if ( empty( $nextPriceData ) ) {
			return false;
		}
		
		$template = $this->getTemplate();
		
		return strtr( $template, array(
			'{tp_required_quantity}' => $nextPriceData['next_quantity'],
			'{tp_next_price}'        => wc_price( $nextPriceData['next_price'] ),
			'{tp_next_discount}'     => number_format( $nextPriceData['next_discount'], wc_get_price_decimals(),
				wc_get_price_decimal_separator(), wc_get_price_thousand_separator() ),
			'{tp_actual_discount}'   => number_format( $nextPriceData['actual_discount'], wc_get_price_decimals(),
				wc_get_price_decimal_separator(), wc_get_price_thousand_separator() ),
		) );
	}
	
	protected function getNextPriceData( $cartItem ) {
		$pricingRule = $this->getPricingRule( $cartItem );
		
		if ( ! empty( $pricingRule->getRules() ) ) {
			
			$iterator        = new ArrayIterator( array_reverse( $pricingRule->getRules(), true ) );
			$currentQuantity = $this->getTotalProductCountInCart( $cartItem );
			
			while ( $iterator->valid() ) {
				if ( $currentQuantity < $iterator->key() ) {
					
					$prevQuantity = $iterator->key();
					$prevPrice    = $iterator->current();
					
					$iterator->next();
					
					if ( $iterator->valid() && $currentQuantity >= $iterator->key() ) {
						
						$product = wc_get_product( $this->getProductId( $cartItem ) );
						
						if ( $pricingRule->isFixed() ) {
							$nextPrice    = $prevPrice;
							$currentPrice = $iterator->current();
						} else {
							$nextPrice    = PriceManager::getProductPriceWithPercentageDiscount( $product, $prevPrice );
							$currentPrice = PriceManager::getProductPriceWithPercentageDiscount( $product,
								$iterator->current() );
						}
						
						$currentPrice = PriceManager::getPriceToDisplay( $currentPrice, $product, 'cart' );
						$nextPrice    = PriceManager::getPriceToDisplay( $nextPrice, $product, 'cart' );
						
						return array(
							'next_price'      => $nextPrice,
							'next_discount'   => PriceManager::calculateDiscount( $currentPrice, $nextPrice ),
							'actual_discount' => $pricingRule->isPercentage() ? $prevPrice : PriceManager::calculateDiscount( $product->get_price( 'edit' ),
								$currentPrice ),
							'next_quantity'   => $prevQuantity - $currentQuantity,
						);
					} elseif ( ! $iterator->valid() ) {
						$product = wc_get_product( $this->getProductId( $cartItem ) );
						
						if ( $pricingRule->isFixed() ) {
							$nextPrice    = $prevPrice;
							$currentPrice = $product->get_price();
						} else {
							$nextPrice    = PriceManager::getProductPriceWithPercentageDiscount( $product, $prevPrice );
							$currentPrice = $product->get_price();
						}
						
						$nextPrice = PriceManager::getPriceToDisplay( $nextPrice, $product, 'cart' );
						
						return array(
							'next_price'      => $nextPrice,
							'next_discount'   => PriceManager::calculateDiscount( $currentPrice, $nextPrice ),
							'actual_discount' => $pricingRule->isPercentage() ? $prevPrice : PriceManager::calculateDiscount( $currentPrice,
								$product->get_price( 'edit' ) ),
							'next_quantity'   => $prevQuantity - $currentQuantity,
						);
					}
				} else {
					$iterator->next();
				}
			}
		}
		
		return array();
	}
	
	public function getTotalProductCountInCart( $cartItem ) {
		return ! empty( $cartItem['tiered_pricing_data']['total_item_quantity'] ) ? $cartItem['tiered_pricing_data']['total_item_quantity'] : $cartItem['quantity'];
	}
	
	/**
	 * Get pricing rules from cart item
	 *
	 * @param  array  $cartItem
	 *
	 * @return PricingRule|false
	 */
	protected function getPricingRule( $cartItem ) {
		
		$productId = $this->getProductId( $cartItem );
		
		if ( $productId ) {
			return PriceManager::getPricingRule( $productId );
		}
		
		return false;
	}
	
	protected function getProductId( $cartItem ) {
		return ! empty( $cartItem['variation_id'] ) ? $cartItem['variation_id'] : $cartItem['product_id'];
	}
	
	protected function getTemplate() {
		return $this->getContainer()->getSettings()->get( 'cart_upsell_template',
			__( 'Buy <b>{tp_required_quantity}</b> more to get <b>{tp_next_price}</b> each', 'tier-pricing-table' ) );
	}
	
	protected function getUpsellColor() {
		return $this->getContainer()->getSettings()->get( 'cart_upsell_color', '#96598A' );
	}
	
	protected function isCartUpsellEnabled() {
		return $this->getContainer()->getSettings()->get( 'cart_upsell_enabled', 'no' ) === 'yes';
	}
}
