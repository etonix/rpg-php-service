<?php
class PlayerClearStage extends \DB\SQL\Mapper {
	public function __construct() {
		parent::__construct( \Base::instance()->get('DB'), 'player_clear_stage' );
	}
}
?>