<?php namespace TierPricingTable;

use WC_Product;

class PriceManager {
	
	protected static $pricingRules = array();
	
	public static function getFixedPriceRules( $productId, string $context = 'view' ): array {
		return self::getPriceRules( $productId, 'fixed', $context );
	}
	
	public static function getPercentagePriceRules( $productId, string $context = 'view' ): array {
		return self::getPriceRules( $productId, 'percentage', $context );
	}
	
	public static function getPriceRules( $productId, ?string $type = null, string $context = 'view' ): array {
		
		$type = $type ? $type : self::getPricingType( $productId, 'fixed', $context );
		
		if ( 'fixed' === $type ) {
			$rules = (array) get_post_meta( $productId, '_fixed_price_rules', true );
		} else {
			$rules = (array) get_post_meta( $productId, '_percentage_price_rules', true );
		}
		
		$rules = ! empty( $rules ) ? array_filter( $rules ) : array();
		ksort( $rules );
		
		if ( 'edit' !== $context ) {
			$rules = apply_filters( 'tiered_pricing_table/price/product_price_rules', $rules, $productId, $type );
		}
		
		return array_filter( $rules, function ( $quantity ) {
			return intval( $quantity ) > 1;
		}, ARRAY_FILTER_USE_KEY );
	}
	
	/**
	 * Get price by product quantity
	 *
	 * @param  int  $quantity
	 * @param  int  $productId
	 * @param  ?string  $context
	 * @param  ?string  $place
	 * @param  bool  $withTaxes
	 *
	 * @return bool|float|int
	 */
	public static function getPriceByRules(
		$quantity,
		$productId,
		$context = 'view',
		$place = 'shop',
		bool $withTaxes = true,
		PricingRule $pricingRule = null,
		bool $roundPrice = false
	) {
		$pricingRule = $pricingRule ? $pricingRule : self::getPricingRule( $productId );
		$roundPrice  = $roundPrice ? $roundPrice : CalculationLogic::roundPrice();
		
		foreach ( array_reverse( $pricingRule->getRules(), true ) as $_amount => $price ) {
			if ( $_amount <= $quantity ) {
				
				if ( $pricingRule->isPercentage() ) {
					$product = wc_get_product( $productId );
					
					if ( $product ) {
						$productPrice = self::getProductPriceWithPercentageDiscount( $product, $price );
					}
				} else {
					$productPrice = $price;
				}
				
				if ( 'view' === $context && $withTaxes ) {
					$product      = wc_get_product( $productId );
					$productPrice = self::getPriceToDisplay( $productPrice, $product, $place );
				}
				break;
			}
		}
		
		$productPrice = $productPrice ?? false;
		
		if ( $productPrice && apply_filters( 'tiered_pricing_table/price/round_price', $roundPrice ) ) {
			$productPrice = round( $productPrice, max( 2, wc_get_price_decimals() ) );
		}
		
		if ( 'edit' !== $context ) {
			return apply_filters( 'tiered_pricing_table/price/price_by_rules', $productPrice, $quantity, $productId,
				$context, $place, $pricingRule );
		}
		
		return $productPrice;
	}
	
	/**
	 * Calculate displayed price depend on taxes
	 *
	 * @param  float  $price
	 * @param  WC_Product  $product
	 * @param  ?string  $displayContext
	 *
	 * @return ?float
	 */
	public static function getPriceToDisplay( $price, WC_Product $product, ?string $displayContext = 'shop' ): ?float {
		
		if ( wc_tax_enabled() ) {
			
			$price = wc_get_price_to_display( $product, array(
				'price'           => $price,
				'qty'             => 1,
				'display_context' => $displayContext,
			) );
			
		}
		
		return floatval( $price );
	}
	
	/**
	 * Calculate price using percentage discount
	 *
	 * @param  float|int  $price
	 * @param  float|int  $discount
	 *
	 * @return bool|float|int
	 */
	public static function getPriceByPercentDiscount( $price, $discount ) {
		if ( $price > 0 && $discount <= 100 ) {
			$discount_amount = ( $price / 100 ) * $discount;
			
			return $price - $discount_amount;
		}
		
		return false;
	}
	
	public static function getProductPriceWithPercentageDiscount( WC_Product $product, float $discount ) {
		$productPrice = CalculationLogic::calculateDiscountBasedOnRegularPrice() ? $product->get_regular_price() : $product->get_price();
		
		return self::getPriceByPercentDiscount( $productPrice, $discount );
	}
	
	public static function getPricingType(
		$productId,
		string $default = 'fixed',
		string $context = 'view'
	): string {
		
		if ( ! tpt_fs()->can_use_premium_code() ) {
			return 'fixed';
		}
		
		$type = get_post_meta( $productId, '_tiered_price_rules_type', true );
		$type = in_array( $type, array( 'fixed', 'percentage' ) ) ? $type : $default;
		
		if ( 'edit' !== $context ) {
			return apply_filters( 'tiered_pricing_table/price/type', $type, $productId );
		}
		
		return $type;
	}
	
	/**
	 * Update product pricing type
	 *
	 * @param  int  $productId
	 * @param  string  $type
	 */
	public static function updatePriceRulesType( $productId, string $type ) {
		if ( in_array( $type, array( 'percentage', 'fixed' ) ) ) {
			update_post_meta( $productId, '_tiered_price_rules_type', $type );
		}
	}
	
	/**
	 * Get product minimum quantity
	 *
	 * @param  int  $productId
	 * @param  ?string  $context
	 *
	 * @return int
	 */
	public static function getProductQtyMin( $productId, ?string $context = 'view' ): ?int {
		
		if ( tpt_fs()->can_use_premium_code__premium_only() ) {
			
			$minimum = get_post_meta( $productId, '_tiered_price_minimum_qty', true );
			$minimum = $minimum ? intval( $minimum ) : null;
			$minimum = $minimum > 1 ? $minimum : null;
			
			if ( 'view' === $context ) {
				return apply_filters( 'tiered_pricing_table/price/minimum', $minimum, $productId );
			}
			
			return $minimum;
		}
		
		return null;
	}
	
	/**
	 * Update product minimum quantity
	 *
	 * @param  int  $productId
	 * @param  ?int  $minimum
	 */
	public static function updateProductMinimumQuantity( $productId, ?int $minimum ) {
		if ( ! $minimum ) {
			delete_post_meta( $productId, '_tiered_price_minimum_qty' );
		} else {
			update_post_meta( $productId, '_tiered_price_minimum_qty', $minimum );
		}
	}
	
	public static function calculateDiscount( $originalPrice, $currentPrice ) {
		
		if ( $currentPrice >= $originalPrice ) {
			return 0;
		}
		
		return 100 * ( $originalPrice - $currentPrice ) / $originalPrice;
	}
	
	/**
	 * Main function to get pricing information for a product.
	 *
	 * @param  int  $productId
	 * @param  ?string  $tieredPricingType  - 'percentage' or 'fixed'. Leave null to use default.
	 *
	 * @return PricingRule
	 */
	public static function getPricingRule( $productId, ?string $tieredPricingType = null ): PricingRule {
		
		/****************************************
		 *
		 * Object cache
		 *
		 ****************************************/
		if ( array_key_exists( $productId, self::$pricingRules ) && ! $tieredPricingType ) {
			return self::$pricingRules[ $productId ];
		}
		
		/****************************************
		 *
		 * Initialize variables
		 *
		 ****************************************/
		$product           = null;
		$pricingRule       = new PricingRule( $productId );
		$tieredPricingType = $tieredPricingType ? $tieredPricingType : self::getPricingType( $productId );
		
		/****************************************
		 *
		 * Set tiered pricing for the product
		 *
		 ****************************************/
		$tieredPricingRules = self::getPriceRules( $productId, $tieredPricingType );
		
		// If product does not have rules, check parent product
		if ( empty( $tieredPricingRules ) ) {
			$product = wc_get_product( $productId );
			
			if ( $product && TierPricingTablePlugin::isVariationProductSupported( $product ) ) {
				$tieredPricingType  = self::getPricingType( $product->get_parent_id() );
				$tieredPricingRules = self::getPriceRules( $product->get_parent_id(), $tieredPricingType );
				
				if ( ! empty( $tieredPricingRules ) ) {
					$pricingRule->logPricingModification( 'Using parent product pricing rules' );
				}
			}
		}
		
		$pricingRule->setRules( $tieredPricingRules );
		$pricingRule->setType( $tieredPricingType );
		
		/****************************************
		 *
		 * Set minimum quantity for the product
		 *
		 ****************************************/
		$minimum = self::getProductQtyMin( $productId );
		
		// If product does not have minimum quantity, check parent product
		if ( ! $minimum ) {
			$product = $product ? $product : wc_get_product( $productId );
			
			if ( $product && TierPricingTablePlugin::isVariationProductSupported( $product ) ) {
				$minimum = self::getProductQtyMin( $product->get_parent_id() );
				
				if ( $minimum ) {
					$pricingRule->logPricingModification( 'Using parent product minimum quantity' );
				}
			}
		}
		
		$pricingRule->setMinimum( $minimum );
		
		/*****************************************
		 *
		 * Services that modify pricing rule
		 *
		 * @hooked QuantityManager - 1:  Added maximum and quantity step information.
		 * @hooked CategoryTierAddon - 10:  Filter with category-based rules
		 * @hooked RoleBasedPricingAddon - 20:  Filter with role-based rules
		 * @hooked GlobalPricingService - 30:  Filter with global rules
		 *
		 *****************************************/
		$pricingRule = apply_filters( 'tiered_pricing_table/price/pricing_rule', $pricingRule, $productId );
		
		self::$pricingRules[ $productId ] = $pricingRule;
		
		return $pricingRule;
	}
}
