<?php

if (!defined('EVENT_ESPRESSO_VERSION')) {
	exit('No direct script access allowed');
}

if(!class_exists("HpsServicesConfig")) {
	require_once (dirname(__FILE__).'/../../lib/Hps.php');
}

/**
 *
 * EE_PMT_Onsite
 *
 *
 * @package			Event Espresso
 * @subpackage
 * @author				Mike Nelson
 *
 */
class EE_PMT_Secure_Submit_Payment_Method_Onsite extends EE_PMT_Base{
	
	protected $_public_key = null;

	/**
	 *
	 * @param EE_Payment_Method $pm_instance
	 * @return EE_PMT_Secure_Submit_Payment_Method_Onsite
	 */
	public function __construct($pm_instance = NULL) {
		require_once($this->file_folder().'EEG_Secure_Submit_Payment_Method_Onsite.gateway.php');
		$this->_gateway = new EEG_Secure_Submit_Payment_Method_Onsite($pm_instance);
		$this->_pretty_name = __("Secure Submit (Heartland Payments) Onsite", 'event_espresso');
		$this->_requires_https = true;
		parent::__construct($pm_instance);
		
		if($pm_instance != null){
			$this->_public_key = $this->_pm_instance->get_extra_meta("public_key");
			
			if(!empty($this->_public_key) && is_array($this->_public_key)){
				foreach($this->_public_key as $key => $value) {
					$this->_public_key = $value;
				}
			}
		}
	}

	/**
	 * Adds the help tab
	 * @see EE_PMT_Base::help_tabs_config()
	 * @return array
	 */
	public function help_tabs_config(){
		return array(
			$this->get_help_tab_name() => array(
				'title' => __('Secure Submit Payment Method Onsite Settings', 'event_espresso'),
				'filename' => 'secure_submit_payment_method_onsite'
				),
		);
	}

	/**
	 * @param \EE_Transaction $transaction
	 * @return \EE_Billing_Attendee_Info_Form
	 */
	public function generate_new_billing_form( EE_Transaction $transaction = null ) {
		if(!empty($this->_public_key)){		
			
			$form = new EE_Billing_Attendee_Info_Form( $this->_pm_instance, array(
				'id' => 'securesubmit_payment_form',
				'name'        => 'Secure_Submit_Payment_Method_Onsite_Form',
				'subsections' => array(
					'card_number' => new EE_Credit_Card_Input( array(
						'required'        => false,
						'html_name' => 'card_number',
						'html_id' => 'card_number',
						'html_label_text' => __( 'Credit Card', 'event_espresso' ),
					) ),
					'exp_month'   => new EE_Credit_Card_Month_Input( true, array(
						'required'        => false,
						'html_name' => 'exp_month',
						'html_id' => 'exp_month',
						'html_label_text' => __( 'Expiry Month', 'event_espresso' )
					) ),
					'exp_year'    => new EE_Credit_Card_Year_Input( array(
						'required'        => false,
						'html_name' => 'exp_year',
						'html_id' => 'exp_year',
						'html_label_text' => __( 'Expiry Year', 'event_espresso' ),
					) ),
					'card_cvc'         => new EE_CVV_Input( array(
						'html_id' => 'card_cvc',
						'required'        => false,
						'html_name' => 'card_cvc',
						'html_label_text' => __( 'CVV', 'event_espresso' )
					) ),
					'securesubmit_giftcardnumber'         => new EE_Text_Input( array(
						'html_id' => 'securesubmit_giftcardnumber',
						'required'        => false,
						'html_name' => 'securesubmit_giftcardnumber',
						'html_label_text' => __( 'Gift Card Number', 'event_espresso' )
					) ),
					'securesubmit_giftcardpin'         => new EE_Text_Input( array(
						'html_id' => 'securesubmit_giftcardpin',
						'required'        => false,
						'html_name' => 'securesubmit_giftcardpin',
						'html_label_text' => __( 'Gift Card PIN', 'event_espresso' )
					) ),
					'securesubmit_token'         => new EE_Fixed_Hidden_Input( array(
						'html_id' => 'securesubmit_token',
						'required'        => false,
						'html_name' => 'securesubmit_token'
					) ),
					'heartland_js'         => new EE_Form_Section_HTML('
						<script src="' . get_home_url() . '/wp-content/plugins/event-espresso-4-secure-submit/payment_methods/Secure_Submit_Payment_Method_Onsite/js/secure.submit-1.1.1.js"></script>
						<!-- Init the form as a secure submit form -->
						<script>
							jQuery("#ee-spco-payment_options-reg-step-form").SecureSubmit({
								public_key: "' . $this->_public_key . '",
								error: function (response) {
									alert(response.message);
								}
							});
						</script>'
					),
					
				)
			) );
			return $form;
		}else{
			$form = new EE_Billing_Info_Form( $this->_pm_instance, array(
				'id' => 'securesubmit_payment_form',
				'name'        => 'Secure_Submit_Payment_Method_Onsite_Form',
				'subsections' => array(
					'FAIL'   => new EE_Form_Section_HTML('<p style="color: red; font-weight: bold;">Secure Submit cannot currently be used as a payment method.&nbsp; The site administrator has not yet configured their Secure Submit Payment Settings API keys!</p>')
				)
			));
			return $form;
		}
	}

	/**
	 * Gets the form for all the settings related to this payment method type
	 * @return EE_Payment_Method_Form
	 */
	public function generate_new_settings_form() {
		$form = new EE_Payment_Method_Form(array(
		
			'extra_meta_inputs'=>array(
				'public_key'=>new EE_Text_Input(array(
					'html_label_text'=> "Secure Submit Public Key"
				)), 
				'secret_key'=>new EE_Text_Input(array(
					'html_label_text'=> "Secure Submit Secret Key"
				))
			)
		));
		return $form;
	}
}
// End of file EE_PMT_Onsite.php
