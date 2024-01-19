<?php

/**
 * WC_Xpay_Cdo_Gateway
 *
 * Providencia um Gateway de pagamento utilizando os serviços do Xpay
 *
 * @class       WC_Xpay_Cdo_Gateway
 * @extends     WC_Payment_Gateway
 * @version     2.1.0
 * @package     WooCommerce\Classes\Payment
 */
class WC_Xpay_Cdo_Gateway extends WC_Payment_Gateway
{

	/**
	 * Gateway instructions that will be added to the thank you page and emails.
	 *
	 * @var string
	 */
	public $instructions;

	public $status_when_waiting;

	
	public $title;
	public $description;
	public $id;
	public $icon;
	public $method_title;
	public $method_description;
	public $has_fields;
	public $form_fields;

	public $merchant_id;
	public $client_id;
    public $client_secret;
    public $authorization_token;

	public $parcelas = array();
	public $total_amount;
	public $chekout_token;

	/**
	 * Enable for shipping methods.
	 *
	 * @var array
	 */
	public $enable_for_methods;

	/**
	 * Constructor for the gateway.
	 */
	public function __construct()
	{
		// Setup general properties.
		$this->setup_properties();

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Get settings.
		$this->title              = $this->get_option('title');
        $this->merchant_id      = $this->get_option('merchant_id');
        $this->client_id         = $this->get_option('client_id');
        $this->client_secret      = $this->get_option('client_secret');
        $this->authorization_token         = $this->get_option('authorization_token');
		$this->description        = $this->get_option('description');
		$this->instructions       = $this->get_option('instructions');
		$this->enable_for_methods = $this->get_option('enable_for_methods', array());

		// Actions.
		add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

		add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));

		add_action('woocommerce_after_checkout_shipping_form', array($this, 'xpay_after_checkout_shipping_form'));

		add_filter('woocommerce_gateway_description', array($this, 'xpay_description_fields_credit'), 20, 2);
		add_action('woocommerce_checkout_process', array($this, 'xpay_description_fields_validation_credit'));
		

		// Customer Emails.
		add_action('woocommerce_email_before_order_table', array($this, 'email_instructions'), 10, 3);
	}


	/**
	 * Setup general properties for the gateway.
	 */
	protected function setup_properties()
	{
		$this->id                       = 'xpay-credit';
        $this->merchant_id	            = __('Adicionar Merchant ID', 'xpay-cdo-woocommerce');
        $this->client_id                = __('Adicionar Client ID', 'xpay-cdo-woocommerce');
        $this->client_secret	        = __('Adicionar Client Secret', 'xpay-cdo-woocommerce');
        $this->authorization_token      = __('Adicionar Authorization Token', 'xpay-cdo-woocommerce');
		$this->icon                     = apply_filters('xpay-cdo-woocommerce', plugins_url('../assets/icon-credit.png', __FILE__));
		$this->method_title             = __('Cartão de Crédito', 'xpay-cdo-woocommerce');
		$this->method_description       = __('Receba pagamentos no crédito utilizando sua conta Xpay', 'xpay-cdo-woocommerce');
		$this->has_fields               = false;
		$this->instructions 	        = __('Realize seu pagamento com cartão de crédito!', 'xpay-cdo-woocommerce');
	}

	/**
	 * Initialise Gateway Settings Form Fields.
	 */
	public function init_form_fields()
	{
		$this->form_fields = array(
			'enabled'            => array(
				'title'       => __('Ativar/Desativar', 'xpay-cdo-woocommerce'),
				'label'       => __('Ativar Pagamento no Cartão de Crédito - Xpay', 'xpay-cdo-woocommerce'),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no',
			),
            'merchant_id'              => array(
				'title'       => __('Merchant ID', 'xpay-cdo-woocommerce'),
				'type'        => 'text',
			),
            'client_id'              => array(
				'title'       => __('Client ID', 'xpay-cdo-woocommerce'),
				'type'        => 'text',
			),
            'client_secret'              => array(
				'title'       => __('Client Secret', 'xpay-cdo-woocommerce'),
				'type'        => 'text',
			),
            'authorization_token'              => array(
				'title'       => __('Authorization Token', 'xpay-cdo-woocommerce'),
				'type'        => 'text',
			),
			'title'              => array(
				'title'       => __('Título', 'xpay-cdo-woocommerce'),
				'type'        => 'safe_text',
				'description' => __('Título que o cliente verá na tela de pagamento', 'xpay-cdo-woocommerce'),
				'default'     => __('Cartão de Crédito - Xpay Pagamentos', 'xpay-cdo-woocommerce'),
				'desc_tip'    => true,
			),
			'description'        => array(
				'title'       => __('Descrição', 'xpay-cdo-woocommerce'),
				'type'        => 'textarea',
				'description' => __('Descrição do método de pagamento', 'xpay-cdo-woocommerce'),
				'default'     => __('Realize o pagamento utilizando o seu cartão de crédito!', 'xpay-cdo-woocommerce'),
				'desc_tip'    => true,
			),
		);
	}


	/**
	 * Check If The Gateway Is Available For Use.
	 *
	 * @return bool
	 */
	public function is_available()
	{
		$order          = null;
		$needs_shipping = false;

		// Test if shipping is needed first.
		if (WC()->cart && WC()->cart->needs_shipping()) {
			$needs_shipping = true;
		} elseif (is_page(wc_get_page_id('checkout')) && 0 < get_query_var('order-pay')) {
			$order_id = absint(get_query_var('order-pay'));
			$order    = wc_get_order($order_id);

			// Test if order needs shipping.
			if ($order && 0 < count($order->get_items())) {
				foreach ($order->get_items() as $item) {
					$_product = $item->get_product();
					if ($_product && $_product->needs_shipping()) {
						$needs_shipping = true;
						break;
					}
				}
			}
		}

		$needs_shipping = apply_filters('woocommerce_cart_needs_shipping', $needs_shipping);

		// Only apply if all packages are being shipped via chosen method, or order is virtual.
		if (!empty($this->enable_for_methods) && $needs_shipping) {
			$order_shipping_items            = is_object($order) ? $order->get_shipping_methods() : false;
			$chosen_shipping_methods_session = WC()->session->get('chosen_shipping_methods');

			if ($order_shipping_items) {
				$canonical_rate_ids = $this->get_canonical_order_shipping_item_rate_ids($order_shipping_items);
			} else {
				$canonical_rate_ids = $this->get_canonical_package_rate_ids($chosen_shipping_methods_session);
			}

			if (!count($this->get_matching_rates($canonical_rate_ids))) {
				return false;
			}
		}

		return parent::is_available();
	}

	/**
	 * Checks to see whether or not the admin settings are being accessed by the current request.
	 *
	 * @return bool
	 */
	private function is_accessing_settings()
	{
		if (is_admin()) {
			// phpcs:disable WordPress.Security.NonceVerification
			if (!isset($_REQUEST['page']) || 'wc-settings' !== $_REQUEST['page']) {
				return false;
			}
			if (!isset($_REQUEST['tab']) || 'checkout' !== $_REQUEST['tab']) {
				return false;
			}
			if (!isset($_REQUEST['section']) || 'xpay-credit' !== $_REQUEST['section']) {
				return false;
			}
			// phpcs:enable WordPress.Security.NonceVerification

			return true;
		}

		return false;
	}

	/**
	 * Loads all of the shipping method options for the enable_for_methods field.
	 *
	 * @return array
	 */
	private function load_shipping_method_options()
	{
		// Since this is expensive, we only want to do it if we're actually on the settings page.
		if (!$this->is_accessing_settings()) {
			return array();
		}

		$data_store = WC_Data_Store::load('shipping-zone');
		$raw_zones  = $data_store->get_zones();

		foreach ($raw_zones as $raw_zone) {
			$zones[] = new WC_Shipping_Zone($raw_zone);
		}

		$zones[] = new WC_Shipping_Zone(0);

		$options = array();
		foreach (WC()->shipping()->load_shipping_methods() as $method) {

			$options[$method->get_method_title()] = array();

			// Translators: %1$s shipping method name.
			$options[$method->get_method_title()][$method->id] = sprintf(__('Any &quot;%1$s&quot; method', 'xpay-cdo-woocommerce'), $method->get_method_title());

			foreach ($zones as $zone) {

				$shipping_method_instances = $zone->get_shipping_methods();

				foreach ($shipping_method_instances as $shipping_method_instance_id => $shipping_method_instance) {

					if ($shipping_method_instance->id !== $method->id) {
						continue;
					}

					$option_id = $shipping_method_instance->get_rate_id();

					// Translators: %1$s shipping method title, %2$s shipping method id.
					$option_instance_title = sprintf(__('%1$s (#%2$s)', 'xpay-cdo-woocommerce'), $shipping_method_instance->get_title(), $shipping_method_instance_id);

					// Translators: %1$s zone name, %2$s shipping method instance name.
					$option_title = sprintf(__('%1$s &ndash; %2$s', 'xpay-cdo-woocommerce'), $zone->get_id() ? $zone->get_zone_name() : __('Other locations', 'xpay-cdo-woocommerce'), $option_instance_title);

					$options[$method->get_method_title()][$option_id] = $option_title;
				}
			}
		}

		return $options;
	}

	/**
	 * Converts the chosen rate IDs generated by Shipping Methods to a canonical 'method_id:instance_id' format.
	 *
	 * @since  3.4.0
	 *
	 * @param  array $order_shipping_items  Array of WC_Order_Item_Shipping objects.
	 * @return array $canonical_rate_ids    Rate IDs in a canonical format.
	 */
	private function get_canonical_order_shipping_item_rate_ids($order_shipping_items)
	{

		$canonical_rate_ids = array();

		foreach ($order_shipping_items as $order_shipping_item) {
			$canonical_rate_ids[] = $order_shipping_item->get_method_id() . ':' . $order_shipping_item->get_instance_id();
		}

		return $canonical_rate_ids;
	}

	/**
	 * Converts the chosen rate IDs generated by Shipping Methods to a canonical 'method_id:instance_id' format.
	 *
	 * @since  3.4.0
	 *
	 * @param  array $chosen_package_rate_ids Rate IDs as generated by shipping methods. Can be anything if a shipping method doesn't honor WC conventions.
	 * @return array $canonical_rate_ids  Rate IDs in a canonical format.
	 */
	private function get_canonical_package_rate_ids($chosen_package_rate_ids)
	{

		$shipping_packages  = WC()->shipping()->get_packages();
		$canonical_rate_ids = array();

		if (!empty($chosen_package_rate_ids) && is_array($chosen_package_rate_ids)) {
			foreach ($chosen_package_rate_ids as $package_key => $chosen_package_rate_id) {
				if (!empty($shipping_packages[$package_key]['rates'][$chosen_package_rate_id])) {
					$chosen_rate          = $shipping_packages[$package_key]['rates'][$chosen_package_rate_id];
					$canonical_rate_ids[] = $chosen_rate->get_method_id() . ':' . $chosen_rate->get_instance_id();
				}
			}
		}

		return $canonical_rate_ids;
	}

	/**
	 * Indicates whether a rate exists in an array of canonically-formatted rate IDs that activates this gateway.
	 *
	 * @since  3.4.0
	 *
	 * @param array $rate_ids Rate ids to check.
	 * @return boolean
	 */
	private function get_matching_rates($rate_ids)
	{
		// First, match entries in 'method_id:instance_id' format. Then, match entries in 'method_id' format by stripping off the instance ID from the candidates.
		return array_unique(array_merge(array_intersect($this->enable_for_methods, $rate_ids), array_intersect($this->enable_for_methods, array_unique(array_map('wc_get_string_before_colon', $rate_ids)))));
	}

	/**
	 * Função lida com o processamento do pagamento
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 */
	public function process_payment($order_id){
		//buscando token já autenticado
        $token = $this->authToken();

        if(empty($token)){
            return ['result' => 'fail'];
        }

        $order = wc_get_order($order_id);
        $cart_total = $this->get_order_total();
		$total = number_format($cart_total, 2, '.', '') * 100;

		if($cart_total < 100){
			wc_add_notice(
				__('O valor total do pedido deve ser superior a $1.00!', 'xpay-cdo-woocommerce'),
                'error'
            );

            return [
                'result' => 'fail',
            ];
		}

        $urlCard = 'https://api-br.x-pay.app/creditcard-payment/nit';

		$cardInstallments = $_POST['card_installments'];

		$body_req = [
			'access_token' => $token,
			'amount' => $total,
			'installments' => $cardInstallments,
			'soft_descriptor' => 'Venda CDO Travel',
			'mcc' => $this->merchant_id,
			'env' => 'dev',
			'webhook_url' => ''
		];

		$argsCard = array(
			'method' => 'POST',
			'headers' => array(
				'authorizationToken' => $this->authorization_token,
				'Content-Type' => 'application/json',
				'Accept' => 'application/json'
			),
			'body' => json_encode($body_req),
			'timeout' => 90
		);

        $res = wp_remote_post($urlCard, $argsCard);

        if(wp_remote_retrieve_response_code($res) != 200){
            $body = wp_remote_retrieve_body($res);
			$data = json_decode($body, true);

			$message = $data['message'];
			wc_add_notice($message, 'error');

            return ['result'=> 'fail'];
        }

        if(!is_wp_error($res)){
			$body = array();
			$data = array();

            $body = wp_remote_retrieve_body($res);

            $data = json_decode($body, true);

            $order->update_meta_data('nit', $data['payment']['nit']);
            
            $order->save();

			$url_cobranca = 'https://api-br.x-pay.app/creditcard-payment/charge';
			

			$cardNumber = $_POST['card_number'];
			$cardMonth = $_POST['card_month'];
			$cardYear = $_POST['card_year'];
			$cardYearFormatted = str_replace('20', '', $cardYear);
			$cardCvv = $_POST['card_cvv'];

			$body_req_cobranca = array(
				'cardNumber' => $cardNumber,
				'expirityDate' => $cardMonth.$cardYearFormatted,
				'cvv' => $cardCvv,
				'nit' => $data['payment']['nit'],
				'access_token' => $token,
				'env' => 'dev',
				'webhook_url' => '',
			);

			$args_cobranca = array(
				'method' => 'POST',
				'headers' => array(
					'authorizationToken' => $this->authorization_token,
					'Content-Type' => 'application/json',
					'Accept' => 'application/json',
				),
				'body' => json_encode($body_req_cobranca),
				'timeout' => 90
			);

			$response_cobranca = wp_remote_post($url_cobranca, $args_cobranca);

			if(wp_remote_retrieve_response_code($response_cobranca) != 200){
				$body_cobranca = wp_remote_retrieve_body($response_cobranca);
				$data_cobranca = json_decode($body_cobranca, true);
	
				$message = $data_cobranca['message'];
				wc_add_notice($message, 'error');
	
				return ['result'=> 'fail'];
			}

			if(!is_wp_error($response_cobranca)){
				$body_cobranca = wp_remote_retrieve_body($response_cobranca);
				$data_cobranca = json_decode($body_cobranca, true);

				$order->add_order_note(
					__("Xpay ID de transação" . $data_cobranca['transactionId'], 'viconbank-woocommerce')
				);

				$order->update_meta_data('authorizationNumber', $data_cobranca['authorizationNumber']);
            
				$order->save();

				$order->update_status('completed');

				// Remove cart.
				WC()->cart->empty_cart();

				// Return thankyou redirect.
				return array(
					'result'   => 'success',
					'redirect' => $this->get_return_url($order),
				);
			}else{
				return ['result' => 'fail'];
			}
        }else{
            return ['result' => 'fail'];
        }
        
	}

	/**
	 * Função responsável por fazer a autenticação do token para realizar todas as outras requisições no plugin.
	 */
	public function authToken(){

        $url = 'https://api-br.x-pay.app/token';

		$body_req = array(
			'clientId' => $this->client_id,
			'clientSecret' => $this->client_secret,
			'env' => 'dev',
			'webhook_url' => ''
		);
        
        //fazendo requisição para autenticar token do usuário
        $response = wp_remote_post(
            $url,
            [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'authorizationToken' => $this->authorization_token
                ],
				'body' => json_encode($body_req),
				'timeout' => 90
            ]
        );

        //verificando resposta da requisição
        if($response['response']['code'] != 200){
            $body = wp_remote_retrieve_body($response);
			$data = json_decode($body, true);

			$message = $data['message'];
			wc_add_notice($message, 'error');

            return ['result'=> 'fail'];
        }

        if(!is_wp_error($response)){

            $body = wp_remote_retrieve_body($response);

            $data_request = json_decode($body, true);

            $tokenFinal = $data_request['access_token'];

			//retornando token
            return $tokenFinal;
        }
    }

	/**
	 * Output da página de agradecimento
	 */
	public function thankyou_page()
	{
		echo '<div style="font-size: 20px;color: #303030;text-align: center;">';
		echo '<h3>O pagamento foi registrado no sistema com sucesso!</h3>';
		echo '</div>';
	}
	
	/**
	 * Função adiciona conteúdo aos emails WC
	 *
	 * @param WC_Order $order Order object.
	 * @param bool     $sent_to_admin  Sent to admin.
	 * @param bool     $plain_text Email format: plain text or HTML.
	 */
	public function email_instructions($order, $sent_to_admin, $plain_text = false)
	{
		if ($this->instructions && !$sent_to_admin && $this->id === $order->get_payment_method()) {
			echo wp_kses_post(wpautop(wptexturize($this->instructions)) . PHP_EOL);
		}
	}

	/**
	 * Função apresenta campos adicionais para o método de pagamento na tela de checkout
	 */
	public function xpay_description_fields_credit( $description, $payment_id) {
		$total_amount = $this->get_order_total();

		/*
		* Apresentando campos para o método de pagamento com Cartão de Crédito
		*/    
		if($payment_id === 'xpay-credit'){
			ob_start();

			echo '<div style="display: flex; width: 100%!important; height: auto;">';

			echo '<div style="display: block; width: 100% !important; height: auto;">';

			echo '<h4>Número de Parcelas: &nbsp;<abbr class="required" title="obrigatório">*</abbr></h4>';
			echo '<br><span class="woocommerce-input-wrapper" style="padding-top: 15px;">';
			

			for($i = 1; $i <= 12; $i++){
				echo '<div style="margin-bottom: 10px;">';
				echo '<input type="radio" class="input-radio" name="card_installments" value="'.$i.'" data-saved-value="CFW_EMPTY" data-parsley-required="true" data-parsley-multiple="card_installments" id="card_installments_'.$i.'">';
				echo '<label for="card_installments_'.$i.'" class="radio">'.$i.'x de R$'.number_format($total_amount, 2, '.', '') / $i .'</label>';
				echo '</div>';
				
			}

			echo '</span>';

			echo '</div>';
			
			echo '<div style="display: block; width: 100% !important; height: auto;">';

			echo '<p class="form-row form-row validate-required woocommerce-invalid woocommerce-invalid-required-field" id="card_numberfield" data-priority="">';
			echo '<label for="card_number">Informe o número do cartão: <abbr class="required" title="obrigatório">*</abbr></label>';
			echo '<span class="woocommerce-input-wrapper">';
			echo '<input type="text" name="card_number" required class="input-text" onkeypress="return event.charCode >= 48 && event.charCode <= 57">';
			echo '</span>';
			echo '</p>';

			woocommerce_form_field('card_name', array(
					'type' => 'text',
					'class' => array('form-row'),
					'label' => __('Informe o nome que está no cartão: ', 'xpay-cdo-woocommerce'),
					'required' => true,
				)
			);

			woocommerce_form_field('card_month', array(
					'type' => 'select',
					'class' => array('form-row'),
					'label' => __('Mês de vencimento: ', 'xpay-cdo-woocommerce'),
					'required' => true,
					'options' => array(
						'01' => __('01', 'xpay-cdo-woocommerce'),
						'02' => __('02', 'xpay-cdo-woocommerce'),
						'03' => __('03', 'xpay-cdo-woocommerce'),
						'04' => __('04', 'xpay-cdo-woocommerce'),
						'05' => __('05', 'xpay-cdo-woocommerce'),
						'06' => __('06', 'xpay-cdo-woocommerce'),
						'07' => __('07', 'xpay-cdo-woocommerce'),
						'08' => __('08', 'xpay-cdo-woocommerce'),
						'09' => __('09', 'xpay-cdo-woocommerce'),
						'10' => __('10', 'xpay-cdo-woocommerce'),
						'11' => __('11', 'xpay-cdo-woocommerce'),
						'12' => __('12', 'xpay-cdo-woocommerce'),
					),
				)
			);

			echo '<p class="form-row form-row validate-required woocommerce-invalid woocommerce-invalid-required-field" id="card_number_field" data-priority="">';
			echo '<label for="card_year">Ano de vencimento: <abbr class="required" title="obrigatório">*</abbr></label>';
			echo '<span class="woocommerce-input-wrapper">';
			echo '<select name="card_year" required class="input-text">';
			for($i = 2024; $i <= 2061; $i++){
				echo '<option value="'.$i.'">'.$i.'</option>';
			}
			echo '</select>';
			echo '</span>';
			echo '</p>';

			echo '<p class="form-row form-row validate-required woocommerce-invalid woocommerce-invalid-required-field" id="card_number_field" data-priority="">';
			echo '<label for="card_cvv">CVV: <abbr class="required" title="obrigatório">*</abbr></label>';
			echo '<span class="woocommerce-input-wrapper">';
			echo '<input type="number" name="card_cvv" required class="input-text" max="999">';
			echo '</span>';
			echo '</p>';

			echo '<p class="form-row form-row validate-required woocommerce-invalid woocommerce-invalid-required-field" id="card_cpf_cnpj_field" data-priority="">';
			echo '<label for="card_cpf_cnpj">CPF ou CNPJ: <abbr class="required" title="obrigatório">*</abbr></label>';
			echo '<span class="woocommerce-input-wrapper">';
			echo '<input type="text" id="card_cpf_cnpj" name="card_cpf_cnpj" required class="input-text" onkeypress="return event.charCode >= 48 && event.charCode <= 57">';
			echo '</span>';
			echo '</p>';


			echo '</div>';

			echo '</div>';

			$description .= ob_get_clean();
		}

		return $description;
	}

	/**
	 * Função valida campos adicionais do método de pagamento na tela de checkout
	 */
	public function xpay_description_fields_validation_credit(){
		if($_POST['payment_method'] === 'xpay-credit'){


			if(isset($_POST['card_number']) && empty($_POST['card_number'])){
				wc_add_notice('Por favor informe o número do cartão!', 'error');
			}

			if(isset($_POST['card_month']) && empty($_POST['card_month'])){
				wc_add_notice('Por favor informe o mês de vencimento do cartão!', 'error');
			}

			if(isset($_POST['card_year']) && empty($_POST['card_year'])){
				wc_add_notice('Por favor informe o ano de vencimento do cartão!', 'error');
			}

			if(isset($_POST['card_cvv']) && empty($_POST['card_cvv'])){
				wc_add_notice('Por favor informe o CVV do cartão!', 'error');
			}

			if(isset($_POST['card_cvv']) && !empty($_POST['card_cvv']) && $_POST['card_cvv'] > 999){
				wc_add_notice('Por favor informe um CVV válido!', 'error');
			}


		}
	
	}
}