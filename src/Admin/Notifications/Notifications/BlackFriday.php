<?php namespace TierPricingTable\Admin\Notifications\Notifications;

/**
 * Class Notifications
 *
 * @package TierPricingTable\Admin\Notifications
 */
abstract class BlackFriday extends Notification {
	
	public function getTemplate(): string {
		return 'admin/notifications/black-friday.php';
	}
	
	public function getId(): string {
		return 'black-friday-' . $this->getBlackFridayDate();
	}
	
	abstract public function getBlackFridayDate(): string;
	
	public function isBlackFriday(): bool {
		
		$blackFriday = $this->getBlackFridayDate();
		
		$start = strtotime( "$blackFriday - 3 days" );
		$end   = strtotime( "$blackFriday + 4 days" );
		
		return time() > $start && time() < $end;
	}
	
	public function passedRequirements(): bool {
		
		if ( tpt_fs()->can_use_premium_code__premium_only() ) {
			return false;
		}
		
		return $this->isBlackFriday();
	}
	
	public function isActive(): bool {
		return $this->passedRequirements() && ! $this->isSeen();
	}
}
