<?php

class Cart_Models_Model_CartSession extends Application_Model_Models_Abstract {

	protected $_cartContent = '';

	protected $_ipAddress   = '';

	protected $_id          = '';

	protected $_userId      = null;

	public function setCartContent($cartContent) {
		$this->_cartContent = $cartContent;
		return $this;
	}

	public function getCartContent() {
		return $this->_cartContent;
	}

	public function setIpAddress($ipAddress) {
		$this->_ipAddress = $ipAddress;
		return $this;
	}

	public function getIpAddress() {
		return $this->_ipAddress;
	}

	public function setId($id) {
		$this->_id = $id;
		return $this;
	}

	public function getId()	{
		return $this->_id;
	}

	public function setUserId($userId) {
		$this->_userId = $userId;
		return $this;
	}

	public function getUserId()	{
		return $this->_userId;
	}


}
