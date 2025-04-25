<?php namespace TierPricingTable\Services\API\ProductFields;

use TierPricingTable\PriceManager;
use WC_Product;

class PercentageTieredPricing extends ProductField {
	
	public function getFieldSlug(): string {
		return 'tiered_pricing_percentage_rules';
	}
	
	public function sanitizeValue( $value ): array {
		$rules = array();
		$value = is_array( $value ) ? $value : array();
		
		foreach ( $value as $key => $val ) {
			$rules[ (int) $key ] = (float) $val;
		}
		
		return $rules;
	}
	
	public function getValue( array $product ): array {
		if ( tpt_fs()->can_use_premium_code__premium_only() ) {
			return PriceManager::getPercentagePriceRules( (int) $product['id'], 'edit' );
		}
		
		return array();
	}
	
	public function updateValue( $value, WC_Product $product ) {
		if ( tpt_fs()->can_use_premium_code__premium_only() ) {
			update_post_meta( $product->get_id(), '_percentage_price_rules', $this->sanitizeValue( $value ) );
		}
	}
	
	public function getType(): string {
		return 'object';
	}
	
	public function getDescription(): string {
		return 'Tiered pricing percentage rules. Key is a quantity and value is a percentage discount. Minimum quantity is 2';
	}
}
