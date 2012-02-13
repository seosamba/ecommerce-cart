<?php

class Cart_Models_Mapper_CartSessionMapper extends Application_Model_Mappers_Abstract {

	protected $_dbTable	= 'Cart_Models_DbTable_CartSession';

	protected $_model	= 'Cart_Models_Model_CartSession';

	public function save($model) {
		if(!$model instanceof Cart_Models_Model_CartSession) {
			throw new Exceptions_SeotoasterPluginException('Wrong model type given.');
		}
		$data = array(
			'id'           => $model->getId(),
			'cart_content' => $model->getCartContent(),
			'ip_address'   => $model->getIpAddress(),
			'user_id'      => $model->getUserId()
		);

		;
		if(null === ($exists = $this->find($data['id']))) {
			$data['created_at'] = date(DATE_ATOM);
			return $this->getDbTable()->insert($data);
		}
		else {
			$data['updated_at'] = date(DATE_ATOM);
			return $this->getDbTable()->update($data, array('id = ?' => $exists->getId()));
		}
	}
}
