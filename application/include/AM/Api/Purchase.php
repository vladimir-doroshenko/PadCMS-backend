<?php
/**
 * @file
 * AM_Api_Purchase class definition.
 *
 * LICENSE
 *
 * $DOXY_LICENSE
 *
 * @author $DOXY_AUTHOR
 * @version $DOXY_VERSION
 */

/**
 * This class is responsible for checking purchase operation
 * @ingroup AM_Api
 */
class AM_Api_Purchase extends AM_Api
{
    const URL_APPLE_VERIFY_PRODUCTION = 'https://buy.itunes.apple.com/verifyReceipt';
    const URL_APPLE_VERIFY_SANDBOX    = 'https://sandbox.itunes.apple.com/verifyReceipt';

    const SUBSCRIPTION_1YEAR   = '1year';
    const SUBSCRIPTION_2YEAR   = '2year';
    const SUBSCRIPTION_ARCHIVE = 'archive';

    protected static $_aSubscriptionTypes = array(self::SUBSCRIPTION_1YEAR,
                                                  self::SUBSCRIPTION_2YEAR,
                                                  self::SUBSCRIPTION_ARCHIVE);

    /**
     * Verifying a Receipt with the App Store
     *
     * @param string $sReceiptData
     * @param string $sUdid
     * @param string $sSecretPassword The password for subscription
     * @return int
     */
    public function verifyReceipt($sReceiptData, $sUdid, $sSecretPassword = null)
    {
        if (empty($sUdid) || empty($sReceiptData)) {
            throw new AM_Api_Purchase_Exception('Wrong arguments given');
        }

        $sUdid = trim($sUdid);

        $sUrlVerify = self::URL_APPLE_VERIFY_PRODUCTION;

        $this->getLogger()->debug(sprintf('UDID: "%s"%sSending request to: "%s"%sData: %s', $sUdid, PHP_EOL, $sUrlVerify, PHP_EOL, $sReceiptData));

        $aRequestData                 = array();
        $aRequestData['receipt-data'] = base64_encode('{' . $sReceiptData . '}');
        if (!is_null($sSecretPassword)) {
            $aRequestData['password'] = $sSecretPassword;
        }

        $aResponce = $this->_sendRequest($sUdid, $aRequestData);

        $bHasLatestReceiptInfo = array_key_exists('latest_receipt_info', $aResponce);
        $aReceiptData          = $bHasLatestReceiptInfo ? $aResponce['latest_receipt_info'] : $aResponce['receipt'];
        $sProductId            = $aReceiptData['product_id'];
        $sSubscriptionType     = null;
        //Checking subscription data
        $aProductIdChunks  = explode('.', $sProductId);
        $sSubscriptionType = array_pop($aProductIdChunks);
        if (in_array($sSubscriptionType, self::$_aSubscriptionTypes)) {
            $sProductId = implode('.', $aProductIdChunks);
        } else {
            $sSubscriptionType = null;
        }

        $sTransactionId = $aReceiptData['original_transaction_id'];
        $oPurchaseDate  = new Zend_Date($aReceiptData['purchase_date']);
        $mExpiresDate   = null;

        //Checking for product existence
        if (is_null($sSubscriptionType) && !$bHasLatestReceiptInfo) {
            $this->getLogger()->debug(sprintf('UDID: "%s" We are in purchase mode', $sUdid));
            $oIssue = AM_Model_Db_Table_Abstract::factory('issue')->findOneBy(array('product_id' => $sProductId));
            if (is_null($oIssue)) {
                $this->getLogger()->debug(sprintf('UDID: "%s" Issue with product id "%s" not found!', $sUdid, $sProductId));
                throw new AM_Api_Purchase_Exception('Invalid product id: ' . $sProductId);
            }
        } else {
            $this->getLogger()->debug(sprintf('UDID: "%s" We are in subscription mode', $sUdid));
            $oApplication = AM_Model_Db_Table_Abstract::factory('application')->findOneBy(array('product_id' => $sProductId));
            if (is_null($oApplication)) {
                $this->getLogger()->debug(sprintf('UDID: "%s" Application with product id "%s" not found!', $sUdid, $sProductId));
                throw new AM_Api_Purchase_Exception('Invalid product id: ' . $sProductId);
            }
            if (array_key_exists('expires_date_formatted', $aReceiptData)) {
                $this->getLogger()->debug(sprintf('UDID: "%s" Subscription is auto-renewable ', $sUdid));
                $sSubscriptionType = 'auto-renewable';
                $mExpiresDate      = new Zend_Date($aReceiptData['expires_date_formatted']);
                $this->getLogger()->debug(sprintf('UDID: "%s" Set subscription expires_date to "%s"', $sUdid, $mExpiresDate->toString()));
            } else {
                $mExpiresDate = clone $oPurchaseDate;
                switch ($sSubscriptionType) {
                    case '1year':
                        $mExpiresDate->add(1, Zend_Date::YEAR);
                        $this->getLogger()->debug(sprintf('UDID: "%s" Set subscription expires_date to "%s"', $sUdid, $mExpiresDate->toString()));
                        break;
                    case '2year':
                        $mExpiresDate->add(2, Zend_Date::YEAR);
                        $this->getLogger()->debug(sprintf('UDID: "%s" Set subscription expires_date to "%s"', $sUdid, $mExpiresDate->toString()));
                        break;
                }
            }
        }

        //Checking for device existence
        $oDevice = AM_Model_Db_Table_Abstract::factory('device')->findOneBy(array('identifer' => $sUdid));
        $this->getLogger()->debug(sprintf('UDID: "%s" Looking for device', $sUdid));

        if (is_null($oDevice)) {
            $this->getLogger()->debug(sprintf('UDID: "%s" There are not device row for this udid', $sUdid));
            $oDevice = new AM_Model_Db_Device();
            $oDevice->identifer = $sUdid;
            $oDevice->created   = new Zend_Db_Expr('NOW()');
            $oDevice->save();
            $this->getLogger()->debug(sprintf('UDID: "%s" New device row has been created', $sUdid));
        }

        if ('yes' == $oDevice->deleted) {
            $oDevice->deleted = 'no';
            $oDevice->save();
        }

        $iDeviceId = $oDevice->id;

        $oPurchase = AM_Model_Db_Table_Abstract::factory('purchase')
                ->findOneBy(array('device_id'      => $oDevice->id,
                                  'product_id'     => $sProductId,
                                  'transaction_id' => $sTransactionId
                 ));

        $iReturnValue = 2;
        // Check for transaction existence
        if (is_null($oPurchase)) {
            $oPurchase = new AM_Model_Db_Purchase();
            $oPurchase->device_id  = $iDeviceId;
            $oPurchase->product_id = $sProductId;
            $oPurchase->transaction_id = $sTransactionId;
            $oPurchase->created = new Zend_Db_Expr('NOW()');

            $this->getLogger()->debug(sprintf('UDID: "%s" New purchase record created', $sUdid));
            $iReturnValue = 1;
        }

        $oPurchase->subscription_type = $sSubscriptionType;
        $oPurchase->purchase_date     = $oPurchaseDate->toString(Zend_Date::ISO_8601);
        $oPurchase->expires_date      = is_null($mExpiresDate)? null : $mExpiresDate->toString(Zend_Date::ISO_8601);
        $oPurchase->deleted           = 'no';
        $oPurchase->save();

        $this->getLogger()->debug(sprintf('UDID: "%s" Purchase record has been saved', $sUdid));

        return $iReturnValue;
    }

    /**
     * @param string $sUdid
     * @param string $aRequestData
     * @param string $sUrlVerify
     * @return void
     * @throws AM_Api_Purchase_Exception
     */
    protected function _sendRequest($sUdid, $aRequestData, $sUrlVerify = self::URL_APPLE_VERIFY_PRODUCTION)
    {
        $oClient   = new Zend_Http_Client($sUrlVerify);
        $oResponce = $oClient->setMethod(Zend_Http_Client::POST)
                        ->setRawData(Zend_Json_Encoder::encode($aRequestData))
                        ->request();
        $aResponce = Zend_Json::decode($oResponce->getBody());

        $this->getLogger()->debug(sprintf('UDID: "%s"%sResponse: %s', $sUdid, PHP_EOL, print_r($aResponce, true)));

        if (!array_key_exists('status', $aResponce)) {
            $this->getLogger()->debug(sprintf('UDID: "%s" Wrong response! Status property not found', $sUdid));
            throw new AM_Api_Purchase_Exception(sprintf('Wrong responce given! Status property not found'));
        }

        switch (intval($aResponce['status'])) {
            case 21007:
                $this->getLogger()->debug(sprintf('UDID: "%s" Sendbox request! Status code: %s', $sUdid, $aResponce['status']));
                return $this->_sendRequest($sUdid, $aRequestData, self::URL_APPLE_VERIFY_SANDBOX);
                break;
            case 0:
                if (empty($aResponce['receipt']) || empty($aResponce['receipt']['original_transaction_id']) || empty($aResponce['receipt']['product_id'])) {
                    $this->getLogger()->debug(sprintf('UDID: "%s" Wrong response! Status code: %s', $sUdid, $aResponce['status']));
                    throw new AM_Api_Purchase_Exception(sprintf('Wrong responce given! Status code: %s', $aResponce['status']));
                }
                return $aResponce;
                break;
            default:
                $this->getLogger()->debug(sprintf('UDID: "%s" Wrong response! Status code: %s', $sUdid, $aResponce['status']));
                throw new AM_Api_Purchase_Exception(sprintf('Wrong responce given! Status code: %s', $aResponce['status']));
        }
    }
}