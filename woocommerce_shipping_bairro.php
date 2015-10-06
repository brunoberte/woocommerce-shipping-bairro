<?php
/** 
 * Plugin Name: Woocommerce Shipping Bairro
 * Description: Este plugin permite definir valores de entrega por bairro(s)
 * Version: 0.1.0
 * Author: Bruno Berté
 * Text Domain: woocommerce_shipping_bairro
 * Domain Path: /lang
**/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly


/**
 * Check if WooCommerce is active
 **/

if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	

	function woocommerce_shipping_bairro_init() {

		if ( ! class_exists( 'WC_Shipping_Por_Bairro' ) ) {

		class WC_Shipping_Por_Bairro extends WC_Shipping_Method {

			/**
			 * Constructor for your shipping class
			 *
			 * @access public
			 * @return void
			 */

			public function __construct() {

				$this->id					= 'woocommerce_shipping_por_bairro';
            	load_plugin_textdomain($this->id, false, dirname(plugin_basename(__FILE__)) . '/lang/');
				$this->method_title			= __('Entrega por Bairro', $this->id);
				$this->method_description	= __('Este plugin permite definir valores de entrega por bairro(s)', $this->id);

				$this->wc_shipping_init();

				$this->init_shipping_fields_per_bairro();

			}



			/* Init the settings */

			function wc_shipping_init() {

				//Let's sort arrays the right way
				setlocale(LC_ALL, get_locale());

				//Regions - Source: http://www.geohive.com/earth/gen_codes.aspx
				
				// Load the settings API
				$this->init_form_fields(); // This is part of the settings API. Override the method to add your own settings
				$this->init_settings(); // This is part of the settings API. Loads settings you previously init.

				$this->title = $this->settings['title'];
				$this->enabled = $this->settings['enabled'];

				// Save settings in admin if you have any defined
				add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );

			}

			/* The Shipping Fields */
			function init_form_fields() {

				$fields = array(
					'enabled' => array(
						'title' 		=> __('Enable/Disable', 'woocommerce'),
						'type' 			=> 'checkbox',
						'label' 		=> __('Enable this shipping method', 'woocommerce'),
						'default' 		=> 'no',
					),
					'tax_status' => array(
						'title' 		=> __('Tax Status', 'woocommerce'),
						'type' 			=> 'select',
						'description' 	=> '',
						'default' 		=> 'taxable',
						'options'		=> array(
							'taxable' 	=> __('Taxable', 'woocommerce'),
							'none' 		=> __('None', 'woocommerce'),
						),
					),
					'title' => array(
						'title' 		=> __('Method Title', 'woocommerce'),
						'type' 			=> 'text',
						'description' 	=> __('This controls the title which the user sees during checkout.', 'woocommerce'),
						'default'		=> __('Entrega por bairro', $this->id),
					),			
					'per_postcode_count' => array(
						'title' 		=> __('Number of Postcodes/Zip rules', $this->id),
						'type' 			=> 'number',
						'description'	=> __('How many different "per postcode" rates do you want to set?', $this->id).' '.__('Please save the options after changing this value.', $this->id),
						'default'		=> 1,
					),
				);

				$this->form_fields=$fields;

			}


			/* per PostNumber form fields*/
			function init_shipping_fields_per_bairro() {

				global $woocommerce;
				
				$this->form_fields['per_postcode']=array(
					'title'         => __('Por região :::::::::::', $this->id),
					'type'          => 'title',
					/* 'description'   => __('Set how many "per postnumber" fees as you want.', $this->id), */
				);

				$count = $this->settings['per_postcode_count'];				
				
				for($counter = 1; $count >= $counter; $counter++) {

					$this->form_fields['per_postcode_'.$counter.'_postcode']=array(
						'title'		=> sprintf(__( 'Região #%s', $this->id), $counter),
						'type'		=> 'textarea',
						'description'	=> __('Digite as regiões no formato a seguir: UF|Cidade|Bairro;UF|Cidade|Bairro<br/>Exemplo: PR|Curitiba|Água Verde<br/>Para todos os bairros de uma cidade coloque *. <br/>Exemplo: PR|Curitiba|*<br/>***Atenção: Utilize a mesma nomenclatura dos correios.'),
						'default'	=> '',
						'placeholder'	=>	'UF|Cidade|Bairro;UF|Cidade|Bairro;...',
					);
					$this->form_fields['per_postcode_'.$counter.'_fee']=array(
						'title' 		=> sprintf(__( 'Taxa de entrega #%s', 'woocommerce'), $counter).' ('.get_woocommerce_currency().')',
						'type' 			=> 'text',
						'description'	=> __('Set your quantity based shipping fee with semicolon (;) separated values for all postcodes specified above. Example:Quantity|Price;Quantity|Price. Example: 1|100;2|500.00 OR you can enter single price for all quantities Example: 100.00', $this->id),
						'default'		=> '',
						'placeholder'	=>	'1|10.00',
					);

				}
			
			}
			
			/***
			 * Find Postcode Function
			 * Usage: getPostcode(User_Enterd_Postcode, '1000-2000') 			 
			*/
			public function getPostcode($Usercode, $mpostcode){
				
				$mpostcode = explode("-",$mpostcode);
				
				$fCode= $mpostcode[0];
				$lCode = $mpostcode[1];
				for($fc=$fCode; $fc<=$lCode; $fc++){
					if($Usercode == $fc){
						$postCode=$fc;
						break;
					}
					else{
						$postCode='';	
					}
				}
				
				return $postCode;
			}
			
			/***
			 * Method Title: calPriceByQuantity
			 * Description : Calulate shipping Price By Quantity
			 * Syntax : calPriceByQuantity('cart_qty','qty|price,qty|price,qty|price')
			 * Usage: calPriceByQuantity('2', '1|100,2|200,3|300')
			 * Result: 200
			*/
			function calPriceByQuantity($Qty, $Price) {
					
				$shipPrice ='';
				
				//Explode Price Classes
				$priceValue = explode(";",$Price);
				
				//Count of Price Classes
				$countPriceValue = count($priceValue);
				
				//Get Last Price Class
				$lastPriceClass = $priceValue[$countPriceValue-1];
				
				for($v=0; $v<$countPriceValue; $v++){
					
					$priceWithQty = $priceValue[$v];
					$finalPrice=$this->getQtyPrice($Qty, $priceWithQty);
					
					if($finalPrice != ''){
						$shipPrice =$finalPrice;
						break;
					}
								
				}
				
				//If Quantity exceeded. Last class price Will Apply  
				if($lastPriceClass !='' && $shipPrice==''){
								
					//Explode Last Price Class
					$otherPrice = explode("|",$lastPriceClass);
					
					$shipPrice=$otherPrice[1];	
								
				}
				
				return $shipPrice;
			}
			
			/***
			 * Method Title: getQtyPrice
			 * Description : Returns shipping Price By Quantity. Here You calciulate price only for single quantity. 
			 * Syntax : getQtyPrice('cart_qty','qty|price')
			 * Usage: getQtyPrice('1', '1|100')
			 * Result: 100
			*/
			function getQtyPrice($Qty, $priceWithQty) {
							
				$qtyPrice = explode("|",$priceWithQty);
				
				$qQuantity = $qtyPrice[0];
				$qPrice= '';
				
				if($Qty > 0 && $Qty <= $qQuantity){
					//Return Price
					$qPrice= $qtyPrice[1];
				}		
				
				return $qPrice;
			}


			public static function busca_cep_api($cep) {

				$cep = ereg_replace('[^0-9]', '', $cep);// remove caracteres nao numericos
				if ( strlen($cep) != 8 ) {
					return false;
				}

				// Informações de entrada da requisição
				$vr_CEP  = $cep;
				//$vr_Link = "http://correiosapi.apphb.com/cep/" . $cep;
				$vr_Link = 'http://cep.correiocontrol.com.br/'.$cep.'.json';

				$sessao_curl = curl_init();
				curl_setopt($sessao_curl, CURLOPT_URL, $vr_Link);
				curl_setopt($sessao_curl, CURLOPT_FAILONERROR, true);

				//  CURLOPT_CONNECTTIMEOUT
				//  o tempo em segundos de espera para obter uma conexão
				curl_setopt($sessao_curl, CURLOPT_CONNECTTIMEOUT, 10);

				//  CURLOPT_TIMEOUT
				//  o tempo máximo em segundos de espera para a execução da requisição (curl_exec)
				curl_setopt($sessao_curl, CURLOPT_TIMEOUT, 10);

				//  CURLOPT_RETURNTRANSFER
				//  TRUE para curl_exec retornar uma string de resultado em caso de sucesso, ao
				//  invés de imprimir o resultado na tela. Retorna FALSE se há problemas na requisição
				curl_setopt($sessao_curl, CURLOPT_RETURNTRANSFER, true);

				$resultado = curl_exec($sessao_curl);

				if($resultado) {

					$resultado = json_decode($resultado);

					return array(
						'uf' 			=> $resultado->uf,//estado,
						'cidade' 		=> $resultado->localidade,//cidade,
						'bairro' 		=> $resultado->bairro,
					);

				} else {

					return false;

				}

			    return false;
			}

			public function get_array_postcode() {

				$ret = array();

				$postcount = $this->settings['per_postcode_count'];

				for ( $i = 1 ; $i <= $postcount; $i++ ) {	
								
					$shipcode = $this->settings['per_postcode_'.$i.'_postcode'];
					
					$pcodes = explode(";", $shipcode);
					
					$p = 0;
					
					foreach($pcodes as $pc) {
						
						$aux = explode('|', $pc);
						// $this->console_log(print_r($aux, true));
						if ( count($aux) == 3 ) {
							if ( !isset($ret[$aux[0]]) ) {
								$ret[$aux[0]] = array();
							}
							if ( !isset($ret[$aux[0]][$aux[1]]) ) {
								$ret[$aux[0]][$aux[1]] = array();
							}
							$ret[$aux[0]][$aux[1]][$aux[2]] = $i;
						}
			
						$p++;
					}

				}

				$this->console_log(print_r($ret, true));

				return $ret;

			}

			public function console_log($text) {
				$file = WP_PLUGIN_DIR."/woocommerce-shipping-bairro/debug.txt"; 

				// Write the contents back to the file
				file_put_contents($file, $text . "\r\n", FILE_APPEND);
			}

			/* Calculate the rate */  
			public function calculate_shipping($package) {

				// This is where you'll add your rates
				global $woocommerce;

				$this->console_log('Calculando frete para cep ' . $package['destination']['postcode']);


				$label='';

				//Assign Default Flate Rate as False
				$final_rate = false;

				if( trim($package['destination']['postcode']) != '' ) {
					
					//Get Cart Quantity 
					$cartQuantity = $woocommerce->cart->cart_contents_count;
					
					//PostNumber
					$pcode = $package['destination']['postcode'];

					if($pcode != '') {


						$dados_cep = $this->busca_cep_api($pcode);
						$this->console_log('Dados CEP: ' . print_r($dados_cep, true));
						if ( $dados_cep === false ) {
							return;
						}

						$aux = $this->get_array_postcode();

						$i = false;
						if( isset($aux[ $dados_cep['uf'] ][ $dados_cep['cidade'] ][ $dados_cep['bairro'] ]) ) {
							$i = $aux[ $dados_cep['uf'] ][ $dados_cep['cidade'] ][ $dados_cep['bairro'] ];
						} else {
							if( isset($aux[ $dados_cep['uf'] ][ $dados_cep['cidade'] ][ '*' ]) ) {
								$i = $aux[ $dados_cep['uf'] ][ $dados_cep['cidade'] ][ '*' ];
							}
						}

						$this->console_log('i: ' . $i);

						if($i !== false) {
							$isMultiPrices = strpos($this->settings['per_postcode_'.$i.'_fee'], "|");
							
							if ($isMultiPrices === false) {
								$final_rate = floatval($this->settings['per_postcode_'.$i.'_fee']);
							} else {
								//Get Price By Quantity
								$final_rate = $this->calPriceByQuantity($cartQuantity,$this->settings['per_postcode_'.$i.'_fee']);
							}
						}

						$this->console_log('final_rate: ' . $final_rate);
						
					}
					
				}

				if ($final_rate !== false) {

					$rate = array(
						'id'       => $this->id,
						'label'    => $this->settings['title'],
						'cost'     => $final_rate,
						'calc_tax' => 'per_order'
					);

					// Register the rate
					$this->add_rate($rate);
				}

			}

		}

	}

	}

	add_action( 'woocommerce_shipping_init', 'woocommerce_shipping_bairro_init' );



	/* Add to WooCommerce */

	function woocommerce_shipping_bairro_add( $methods ) {

		$methods[] = 'WC_Shipping_Por_Bairro'; 

		return $methods;

	}

	add_filter( 'woocommerce_shipping_methods', 'woocommerce_shipping_bairro_add' );

}
