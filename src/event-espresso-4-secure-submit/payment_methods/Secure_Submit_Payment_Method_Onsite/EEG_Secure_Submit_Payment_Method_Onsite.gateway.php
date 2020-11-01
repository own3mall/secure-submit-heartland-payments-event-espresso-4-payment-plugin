<?php

if (!defined('EVENT_ESPRESSO_VERSION')) {
	exit('No direct script access allowed');
}

if(!class_exists("HpsServicesConfig")) {
	require_once (dirname(__FILE__).'/../../lib/Hps.php');
}

/**
 *
 * EEG_Secure_Submit_Payment_Method_Onsite
 *
 * Just approves payments where billing_info[ 'credit_card' ] == 1.
 * If $billing_info[ 'credit_card' ] == '2' then its pending.
 * All others get refused
 *
 * @package			Event Espresso
 * @subpackage
 * @author				Mike Nelson
 *
 */
class EEG_Secure_Submit_Payment_Method_Onsite extends EE_Onsite_Gateway{

	
	public function __construct($pmInstance = null) {
		
		if($pmInstance != null){
			$this->_public_key = $pmInstance->get_extra_meta("public_key");
			$this->_secret_key = $pmInstance->get_extra_meta("secret_key");
			
			if(!empty($this->_public_key) && is_array($this->_public_key)){
				foreach($this->_public_key as $key => $value) {
					$this->_public_key = $value;
				}
			}
			
			if(!empty($this->_secret_key) && is_array($this->_secret_key)){
				foreach($this->_secret_key as $key => $value) {
					$this->_secret_key = $value;
				}
			}
		}
	}

	/**
	 * All the currencies supported by this gateway. Add any others you like,
	 * as contained in the esp_currency table
	 * @var array
	 */
	protected $_currencies_supported = array(
					'USD');

	protected $_public_key = null;
	
	protected $_secret_key = null;

	/**
	 *
	 * @param EEI_Payment $payment
	 * @param array $billing_info
	 * @return \EE_Payment|\EEI_Payment
	 */
	public function do_direct_payment($payment, $billing_info = null) {
		$tokenValue = $_POST["securesubmit_token"];
		$this->log( $billing_info, $payment );
		
		// Make sure we have a secret key set
		if($this->_secret_key == null){
			$payment->set_status( $this->_pay_model->failed_status() );
			$payment->set_gateway_response("Secure Submit secret key must be set before payments can be properly processed.");
			return $payment;
		}

		if((isset($tokenValue) && !empty($tokenValue)) || !empty($billing_info['securesubmit_giftcardnumber'])){
			
			$result = array();
			$response = null;
			$config = new HpsServicesConfig();
			$config->secretApiKey = $this->_secret_key;
			$config->versionNumber = '1741';
			$config->developerId = '002914';
			
			$address = new HpsAddress();
			$address->address = $billing_info["address"];
			$address->city = $billing_info["city"];
			$address->state = $billing_info["state"];
			$address->zip = preg_replace('/[^0-9]/', '', $billing_info["zip"]);
			$address->country = $billing_info["country"];

			$cardHolder = new HpsCardHolder();
			$cardHolder->firstName = $billing_info['first_name'];
			$cardHolder->lastName = $billing_info['last_name'];
			$cardHolder->address = $address;
			$cardHolder->phoneNumber = preg_replace('/[^0-9]/', '', $billing_info["phone"]);        

			$token = new HpsTokenData();
			$token->tokenValue = $tokenValue;
			
			$currencySymbol = "$";
			$payment->currency_code();
			
			$amount = $payment->amount();
			
			$balanceAmount = 0;

			if(empty($billing_info['securesubmit_giftcardnumber'])){
				// Process credit card payment
				try {
					if(array_key_exists("card_number", $billing_info) && !empty($billing_info["card_number"])){
						$creditService = new HpsCreditService($config);
						$response = $creditService->charge($amount,'usd',$token,$cardHolder);

						$result["status"] = 1;
						$result["msg"] = "Credit card transaction was completed successfully.&nbsp; [Transaction ID# ".$response->transactionId."]";
						$result['txid'] = $response->transactionId;
						$payment_data['credit_card_charged_amount'] = $amount;
						$payment_data['last_four_cc'] = substr($billing_info['card_number'], -4);
						$payment_data['txn_type'] = 'CreditCard';
						
						$payment->set_status( $this->_pay_model->approved_status() );
						$payment->set_gateway_response( $payment_data['txn_type'] . " " . $result['txid'] . " - Last 4 CC:" . $payment_data['last_four_cc']);
					}else{
						$payment->set_status( $this->_pay_model->failed_status() );
						$payment->set_gateway_response("Please provide a valid credit card number!");
					}
				} catch (Exception $e) {
					$result["status"] = 0;
					$result["error_msg"] = $e->getMessage();
					
					$payment->set_status( $this->_pay_model->failed_status() );
					$payment->set_gateway_response($result["error_msg"]);
				}
			}else{
				// Gift card was submitted?
				if(!empty($billing_info['securesubmit_giftcardnumber'])){
					$gcNumber = trim(str_replace(' ', '', $billing_info['securesubmit_giftcardnumber']));
					$gcPin = array_key_exists("securesubmit_giftcardpin", $billing_info) ? trim(str_replace(' ', '', $billing_info['securesubmit_giftcardpin'])) : "";
					
					try {
						$gcService = new HpsGiftCardService($config);
						$giftCard = new HpsGiftCard($gcNumber);
						if(!empty($gcPin)){
							$giftCard->pin = $gcPin;
						}
						
						// Get balance on the gift card
						$response = $gcService->balance($giftCard);
						$balanceAmount = $response->balanceAmount;
						if($balanceAmount < 0){
							$balanceAmount = 0;
						}
						
						if($balanceAmount >= $amount){
							$response = $gcService->sale($giftCard, $amount, 'usd');
							
							$payment->set_status( $this->_pay_model->approved_status() );
							$payment->set_gateway_response( "GiftCard - TXN ID " . $response->transactionId);
						}else{
							if(array_key_exists('card_number', $billing_info) && !empty($billing_info['card_number']) && $billing_info['card_number'] != '4111111111111111' && isset($tokenValue) && !empty($tokenValue)){
								// Charge the full balance amount since it's less than the price of the item
								$chargedGiftCard = false;
								if($balanceAmount > 0){						
									$gcResponse = $gcService->sale($giftCard, $balanceAmount, 'usd');
									$chargedGiftCard = true;
									$result['txid'] = 'Gift Card Charged ' . $balanceAmount . ' with TXN ID ' . $gcResponse->transactionId;
									$payment_data['gift_card_charged_amount'] = $balanceAmount;
								}
								
								try {
									// Now charge the credit card for the rest of the value
									$creditService = new HpsCreditService($config);
									$response = $creditService->charge(($amount - $balanceAmount),'usd',$token,$cardHolder);

									$result["status"] = 1;
									$result["msg"] = "Gift card and credit card transaction were completed successfully.&nbsp; " . $balanceAmount . " was used from the submitted gift card.&nbsp; The submitted credit card was charged the remaining amount of " . ($amount - $balanceAmount) . ".&nbsp; [Gift Card Transaction ID# " . $gcResponse->transactionId . " - Credit Card Transaction ID# ".$response->transactionId."]";
									$result['txid'] .= (isset($result['txid']) && !empty($result['txid']) ? ' and ' : '') . 'Credit Card Charged ' . ($amount - $balanceAmount) . ' with TXN ID ' . $response->transactionId;
									$payment_data['txn_type'] = 'GiftCard&CreditCard';
									$payment_data['credit_card_charged_amount'] = ($amount - $balanceAmount);
									$payment_data['last_four_cc'] = substr($billing_info['card_number'], -4);
									
									$payment->set_status( $this->_pay_model->approved_status() );
									$payment->set_gateway_response( $payment_data['txn_type'] . " " . $result['txid'] . " - Last 4 CC:" . $payment_data['last_four_cc']);
									
								}catch (Exception $e) {
									$result["status"] = 0;
									$result["error_msg"] = $e->getMessage();
										
									// Credit card payment failed, so reverse the charge done to the card
									if($chargedGiftCard){
										$gcResponse = $gcService->reverse($giftCard, $balanceAmount, 'usd');
										unset($payment_data['gift_card_charged_amount']);
									}
									
									$payment->set_status( $this->_pay_model->failed_status() );
									$payment->set_gateway_response($result["error_msg"]);
								}
							}else{
								$result["status"] = 0;
								$result["error_msg"] = "Gift card balance of " . $balanceAmount . " is less than the amount due of " . $amount . ". Please provide valid credit card information to charge the remaining amount.";
								
								$payment->set_status( $this->_pay_model->failed_status() );
								$payment->set_gateway_response($result["error_msg"]);
							}
						}
					}catch (Exception $e) {
						$payment->set_status( $this->_pay_model->failed_status() );
						$payment->set_gateway_response("The payment failed for technical reasons or expired. " . $e->getMessage());
					}
				}
			}
		}else{
			$payment->set_status( $this->_pay_model->failed_status() );
			$payment->set_gateway_response("No Secure Submit token was included in the request.");
		}
		return $payment;
	}
}

// End of file EEG_Secure_Submit_Payment_Method_Onsite.php
