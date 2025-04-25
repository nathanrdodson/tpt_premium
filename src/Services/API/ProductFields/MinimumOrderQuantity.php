<?php namespace TierPricingTable\Services\API\ProductFields;

use TierPricingTable\PriceManager;
use WC_Product;

class MinimumOrderQuantity extends ProductField {
	
	public function getFieldSlug(): string {
		return 'tiered_pricing_minimum_quantity';
	}
	
	public function getValue( array $product ): ?int {
		if ( tpt_fs()->can_use_premium_code__premium_only() ) {
			return PriceManager::getProductQtyMin( $product['id'], 'edit' );
		}
		
		return null;
	}
	
	public function updateValue( $value, WC_Product $product ) {
		if ( tpt_fs()->can_use_premium_code__premium_only() ) {
			$value = empty( $value ) ? null : (int) $value;
			
			PriceManager::updateProductMinimumQuantity( $product->get_id(), $value );
		}
	}
	
	public function getType(): string {
		return 'string';
	}
	
	public function getDescription(): string {
		return 'Minimum order quantity.';
	}
}
