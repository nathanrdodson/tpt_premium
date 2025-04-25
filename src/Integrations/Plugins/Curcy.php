<?php namespace TierPricingTable\Integrations\Plugins;

use TierPricingTable\PriceManager;
use TierPricingTable\PricingRule;

class Curcy extends PluginIntegrationAbstract {
	
	public function run() {
		add_filter( 'tiered_pricing_table/price/price_by_rules',
			function ( $productPrice, $quantity, $productId, $context, $place, PricingRule $pricingRule ) {
				
				if ( ! function_exists( 'wmc_get_price' ) ) {
					return $productPrice;
				}
				
				if ( $pricingRule->isPercentage() ) {
					return $productPrice;
				}
				
				if ( $productPrice && 'view' === $context ) {
					return wmc_get_price( $productPrice );
				}
				
				return $productPrice;
				
			}, 10, 10 );
		
		add_filter( 'tiered_pricing_table/cart/product_cart_price',
			function ( $price, $cartItem, $cartItemKey, $totalQuantity ) {
				
				if ( ! function_exists( 'wmc_get_price' ) ) {
					return $price;
				}
				
				if ( $price ) {
					return PriceManager::getPriceByRules( $totalQuantity, $cartItem['data']->get_id(), 'edit', 'cart',
						false );
				}
				
				return $price;
			}, 10, 4 );
		
		add_filter( 'tiered_pricing_table/cart/recalculate_cart_item_subtotal', function ( $state ) {
			if ( function_exists( 'wmc_get_price' ) ) {
				return false;
			}
			
			return $state;
		} );
	}
	
	public function getTitle(): string {
		return __( 'CURCY Multicurrency', 'tier-pricing-table' );
	}
	
	public function getIconURL(): string {
		return $this->getContainer()->getFileManager()->locateAsset( 'admin/integrations/curcy-icon.png' );
	}
	
	public function getAuthorURL(): string {
		return 'https://ru.wordpress.org/plugins/woo-multi-currency/';
	}
	
	public function getDescription(): string {
		return __( 'Make the tiered pricing properly work with multiple currencies.', 'tier-pricing-table' );
	}
	
	public function getSlug(): string {
		return 'curcy';
	}
	
	public function getIntegrationCategory(): string {
		return 'multicurrency';
	}
	
	protected function isActiveByDefault(): bool {
		return true;
	}
}
