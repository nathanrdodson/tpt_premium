<?php namespace TierPricingTable\Services;

/*
 * Class TieredPricingCartService
 *
 * Service modifies product's price in the cart based on quantity and tiered pricing rules
 *
 * @package TierPricingTable\Services
 */

use TierPricingTable\CalculationLogic;
use TierPricingTable\Core\ServiceContainerTrait;
use TierPricingTable\PriceManager;
use TierPricingTable\Settings\Sections\GeneralSection\Subsections\CartOptionsSubsection;
use WC_Product;
use WC_Cart;

class TieredPricingCartService {
	
	use ServiceContainerTrait;
	
	protected $originalPrices = [];
	
	public function __construct() {
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'calculateTieredPricingInCart' ), 99999, 3 );
		add_action( 'woocommerce_before_mini_cart_contents', array( $this, 'triggerMiniCartUpdate' ), 9999, 3 );
		add_filter( 'woocommerce_cart_item_price', array( $this, 'calculateCartItemPrice' ), 999, 2 );
		add_filter( 'woocommerce_cart_item_subtotal', array( $this, 'modifyCartItemSubtotal' ), 999, 3 );
	}
	
	public function modifyCartItemSubtotal( $subtotal, $cartItem, $cartItemKey ) {
		
		$newPrice = $this->getCartItemPrice( $cartItem, $cartItemKey );
		
		if ( false === $newPrice ) {
			return $subtotal;
		}
		
		$recalculateCartItemSubtotal = apply_filters( 'tiered_pricing_table/cart/recalculate_cart_item_subtotal', true,
			$cartItem, $cartItemKey, $subtotal );
		
		if ( ! $recalculateCartItemSubtotal ) {
			return $subtotal;
		}
		
		$considerSalePriceAsDiscount = apply_filters( 'tiered_pricing_table/cart/subtotal/consider_sale_price_as_discount',
			false, $cartItem, $cartItemKey );
		
		// Reset product instance because prices is already modified in the "woocommerce_before_calculate_totals" hook.
		// We will not be able to get the original price
		$product = wc_get_product( $cartItem['data']->get_id() );
		
		if ( $product->is_taxable() ) {
			
			if ( wc()->cart->display_prices_including_tax() ) {
				
				$originalCartItemPrice = wc_get_price_including_tax( $product, array(
					'qty'   => $cartItem['quantity'],
					'price' => $considerSalePriceAsDiscount ? $product->get_regular_price() : $product->get_price(),
				) );
				
				$newCartItemPrice = wc_get_price_including_tax( $product, array(
					'qty'   => $cartItem['quantity'],
					'price' => $newPrice,
				) );
				
				$originalProductSubtotal = wc_price( $originalCartItemPrice );
				$newProductSubtotal      = wc_price( $newCartItemPrice );
				
				if ( tpt_fs()->can_use_premium_code__premium_only() ) {
					if ( $originalCartItemPrice !== $newCartItemPrice && CartOptionsSubsection::showSubtotalInCartAsDiscount() ) {
						$newProductSubtotal = '<del>' . $originalProductSubtotal . '</del> <ins>' . $newProductSubtotal . '</ins>';
					}
				}
				
				if ( ! wc_prices_include_tax() && wc()->cart->get_subtotal_tax() > 0 ) {
					$newProductSubtotal .= ' <small class="tax_label">' . WC()->countries->inc_tax_or_vat() . '</small>';
				}
			} else {
				$originalCartItemPrice = wc_get_price_excluding_tax( $product, array(
					'qty'   => $cartItem['quantity'],
					'price' => $considerSalePriceAsDiscount ? $product->get_regular_price() : $product->get_price(),
				) );
				
				$newCartItemPrice = wc_get_price_excluding_tax( $product, array(
					'qty'   => $cartItem['quantity'],
					'price' => $newPrice,
				) );
				
				$originalProductSubtotal = wc_price( $originalCartItemPrice );
				$newProductSubtotal      = wc_price( $newCartItemPrice );
				
				
				if ( tpt_fs()->can_use_premium_code__premium_only() ) {
					if ( $originalCartItemPrice !== $newCartItemPrice && CartOptionsSubsection::showSubtotalInCartAsDiscount() ) {
						$newProductSubtotal = '<del>' . $originalProductSubtotal . '</del> <ins>' . $newProductSubtotal . '</ins>';
					}
				}
				
				if ( wc_prices_include_tax() && wc()->cart->get_subtotal_tax() > 0 ) {
					$newProductSubtotal .= ' <small class="tax_label">' . WC()->countries->ex_tax_or_vat() . '</small>';
				}
			}
		} else {
			$_originalItemPrice    = $considerSalePriceAsDiscount ? $product->get_regular_price() : $product->get_price();
			$originalCartItemPrice = ( float ) $_originalItemPrice * ( float ) $cartItem['quantity'];
			
			$newCartItemPrice = ( float ) $newPrice * ( float ) $cartItem['quantity'];
			
			$originalProductSubtotal = wc_price( $originalCartItemPrice );
			$newProductSubtotal      = wc_price( $newCartItemPrice );
			
			if ( tpt_fs()->can_use_premium_code__premium_only() ) {
				if ( $originalProductSubtotal !== $newProductSubtotal && CartOptionsSubsection::showSubtotalInCartAsDiscount() ) {
					$newProductSubtotal = '<del>' . $originalProductSubtotal . '</del> <ins>' . $newProductSubtotal . '</ins>';
				}
			}
		}
		
		return $newProductSubtotal;
	}
	
	public function getCartItemPrice( $cartItem, $cartItemKey, $cart = null ) {
		
		$cart = $cart instanceof WC_Cart ? $cart : wc()->cart;
		
		if ( ! ( $cart instanceof WC_Cart ) ) {
			return false;
		}
		
		$calculateTieredPriceForItem = apply_filters( 'tiered_pricing_table/cart/need_price_recalculation', true,
			$cartItem, $cart );
		
		if ( ! $calculateTieredPriceForItem ) {
			return false;
		}
		
		if ( empty( $cartItem['data'] ) || ! ( $cartItem['data'] instanceof WC_Product ) ) {
			return false;
		}
		
		$pricingRule = PriceManager::getPricingRule( $cartItem['data']->get_id() );
		
		$totalQuantity = $this->getTotalProductCount( $cartItem );
		$newPrice      = $pricingRule->getTierPrice( $totalQuantity, false, 'cart' );
		
		return apply_filters( 'tiered_pricing_table/cart/product_cart_price', $newPrice, $cartItem, $cartItemKey,
			$totalQuantity );
	}
	
	/**
	 * Calculate price by quantity rules
	 *
	 * @param  WC_Cart  $cart
	 */
	public function calculateTieredPricingInCart( WC_Cart $cart ) {
		
		if ( ! empty( $cart->get_cart_contents() ) ) {
			
			foreach ( $cart->get_cart_contents() as $key => $cartItem ) {
				
				$newPrice = $this->getCartItemPrice( $cartItem, $key, $cart );
				
				if ( false !== $newPrice ) {
					
					if ( ! isset( $this->originalPrices[ $key ] ) ) {
						$this->originalPrices[ $key ] = $cartItem['data']->get_price();
					}
					
					$cartItem['data']->set_price( $newPrice );
					$cartItem['data']->add_meta_data( 'tiered_pricing_cart_price_calculated', 'yes' );
				} else {
					
					if ( isset( $this->originalPrices[ $key ] ) ) {
						$cartItem['data']->set_price( $this->originalPrices[ $key ] );
					}
				}
				
				// Update tiered pricing data
				$cart->cart_contents[ $key ]['tiered_pricing_data'] = array(
					'total_item_quantity' => $this->getTotalProductCount( $cartItem ),
				);
			}
		}
	}
	
	/**
	 * Calculate price in mini cart
	 *
	 * @param  string  $price
	 * @param  array  $cartItem
	 *
	 * @return string
	 */
	public function calculateCartItemPrice( $price, $cartItem ) {
		
		$calculateTieredPriceForItem = apply_filters( 'tiered_pricing_table/cart/need_price_recalculation/item', true,
			$cartItem );
		
		if ( $cartItem['data'] instanceof WC_Product && $calculateTieredPriceForItem ) {
			
			$product = wc_get_product( $cartItem['data']->get_id() );
			
			$pricingRule = PriceManager::getPricingRule( $cartItem['data']->get_id() );
			
			$newPrice = $pricingRule->getTierPrice( $this->getTotalProductCount( $cartItem ), true, 'cart' );
			$newPrice = apply_filters( 'tiered_pricing_table/cart/product_cart_price/item', $newPrice, $cartItem );
			
			if ( false !== $newPrice ) {
				
				if ( tpt_fs()->can_use_premium_code__premium_only() ) {
					
					if ( CalculationLogic::showTieredPriceInCartAsDiscount() ) {
						
						$considerSalePriceAsDiscount = apply_filters( 'tiered_pricing_table/cart/item/consider_sale_price_as_discount',
							false, $cartItem );
						
						$_oldPrice = $considerSalePriceAsDiscount ? $product->get_regular_price() : $product->get_price();
						
						$oldPrice = PriceManager::getPriceToDisplay( $_oldPrice, $product, 'cart' );
						
						$oldPrice = apply_filters( 'tiered_pricing_table/cart/product_cart_old_price', $oldPrice,
							$cartItem );
						
						return '<del> ' . wc_price( $oldPrice ) . ' </del> <ins> ' . wc_price( $newPrice ) . ' </ins>';
					}
				}
				
				return wc_price( $newPrice );
			}
		}
		
		return $price;
	}
	
	public function triggerMiniCartUpdate() {
		$cart = wc()->cart;
		$cart->calculate_totals();
	}
	
	/**
	 * Get total product count depend on user's settings
	 *
	 * @param  ?array  $cartItem
	 *
	 * @return int
	 */
	public function getTotalProductCount( ?array $cartItem ): int {
		
		if ( CalculationLogic::considerProductVariationAsOneProduct() ) {
			$count = 0;
			
			foreach ( wc()->cart->cart_contents as $cart_content ) {
				if ( $cart_content['product_id'] == $cartItem['product_id'] ) {
					$count += $cart_content['quantity'];
				}
			}
		} else {
			$count = $cartItem['quantity'];
		}
		
		return (int) apply_filters( 'tiered_pricing_table/cart/total_product_count', $count, $cartItem );
	}
}
