<?php

class Hipay extends PaymentModule
{
	private $arrayCategories;
	private $prod;

	public function __construct()
	{
		$this->name = 'hipay';
		$this->tab = 'Payment';
		$this->version = 1.0;

		$this->currencies = true;
		$this->currencies_mode = 'radio';

		parent::__construct();

		$this->prod = (int)Tools::getValue('HIPAY_PROD', Configuration::get('HIPAY_PROD'));
		// Define extracted from mapi/mapi_defs.php
		define('HIPAY_GATEWAY_URL','https://'.($this->prod ? '' : 'test.').'payment.hipay.com/order/');

		$this->displayName = $this->l('Hipay');
		$this->description = $this->l('Accepts payments by Hipay');
	}

	public function	install()
	{
		Configuration::updateValue('HIPAY_PROD', Configuration::get('HIPAY_PROD'));
		Configuration::updateValue('HIPAY_UNIQID', uniqid());
		Configuration::updateValue('HIPAY_RATING', 'ALL');
		return (parent::install() AND $this->registerHook('payment'));
	}

	public function hookPayment($params)
	{
		global $smarty;

		if (Configuration::get('HIPAY_ACCOUNT') AND Configuration::get('HIPAY_PASSWORD') AND Configuration::get('HIPAY_SITEID')
			AND Configuration::get('HIPAY_RATING') AND Configuration::get('HIPAY_CATEGORY'))
		{
			$smarty->assign(array('this_path' => $this->_path, 'this_path_ssl' => Tools::getHttpHost(true, true).__PS_BASE_URI__.'modules/'.$this->name.'/'));
			return $this->display(__FILE__, 'payment.tpl');
		}
	}

	public function payment()
	{
		global $cookie, $cart;

		$id_currency = (int)Db::getInstance()->getValue('SELECT id_currency FROM `'._DB_PREFIX_.'module_currency` WHERE id_module = '.(int)$this->id);
		if (!$id_currency OR $id_currency == -2)
			$id_currency = Configuration::get('PS_CURRENCY_DEFAULT');
		elseif ($id_currency == -1)
			$id_currency = $cart->id_currency;
		if ($cart->id_currency != $id_currency)
			if (Db::getInstance()->execute('UPDATE '._DB_PREFIX_.'cart SET id_currency = '.(int)$id_currency.' WHERE id_cart = '.(int)$cart->id))
				$cart->id_currency = $id_currency;

		$currency = new Currency($id_currency);
		$language = new Language($cart->id_lang);
		$customer = new Customer($cart->id_customer);
		$carrier = new Carrier($cart->id_carrier, $cart->id_lang);
		$id_zone = Db::getInstance()->getValue('SELECT id_zone FROM '._DB_PREFIX_.'address a INNER JOIN '._DB_PREFIX_.'country c ON a.id_country = c.id_country WHERE id_address = '.(int)$cart->id_address_delivery);

		require_once(dirname(__FILE__).'/mapi/mapi_package.php');
		
		$hipayAccount = ($this->prod ? Configuration::get('HIPAY_ACCOUNT') : Configuration::get('HIPAY_ACCOUNT_TEST'));
		$hipayPassword = ($this->prod ? Configuration::get('HIPAY_PASSWORD') : Configuration::get('HIPAY_PASSWORD_TEST'));
		$hipaySiteID = ($this->prod ? Configuration::get('HIPAY_SITEID') : Configuration::get('HIPAY_SITEID_TEST'));

		$paymentParams = new HIPAY_MAPI_PaymentParams();
		$paymentParams->setLogin($hipayAccount, $hipayPassword);
		$paymentParams->setAccounts($hipayAccount, $hipayAccount);
		$paymentParams->setDefaultLang(strtolower($language->iso_code).'_'.strtoupper($language->iso_code));
		$paymentParams->setMedia('WEB');
		$paymentParams->setRating(Configuration::get('HIPAY_RATING'));
		$paymentParams->setPaymentMethod(HIPAY_MAPI_METHOD_SIMPLE);
		$paymentParams->setCaptureDay(HIPAY_MAPI_CAPTURE_IMMEDIATE);
		$paymentParams->setCurrency(strtoupper($currency->iso_code));
		$paymentParams->setIdForMerchant($cart->id);
		$paymentParams->setMerchantSiteId($hipaySiteID);
		$paymentParams->setUrlCancel(Tools::getHttpHost(true, true).__PS_BASE_URI__.'order.php?step=3');
		$paymentParams->setUrlNok(Tools::getHttpHost(true, true).__PS_BASE_URI__.'order-confirmation.php?id_cart='.(int)$cart->id.'&amp;id_module='.(int)$this->id.'&amp;secure_key='.$customer->secure_key);
		$paymentParams->setUrlOk(Tools::getHttpHost(true, true).__PS_BASE_URI__.'order-confirmation.php?id_cart='.(int)$cart->id.'&amp;id_module='.(int)$this->id.'&amp;secure_key='.$customer->secure_key);
		$paymentParams->setUrlAck(Tools::getHttpHost(true, true).__PS_BASE_URI__.'modules/'.$this->name.'/validation.php');
		$paymentParams->setBackgroundColor('#FFFFFF');

		if (!$paymentParams->check())
		  return $this->l('[Hipay] Error: cannot create PaymentParams');

		// $taxes = array();
		// $result = Db::getInstance()->executeS('
		// SELECT DISTINCT t.id_tax, tl.name, t.rate
		// FROM '._DB_PREFIX_.'cart_product cp
		// INNER JOIN '._DB_PREFIX_.'product p ON cp.id_product = p.id_product
		// INNER JOIN '._DB_PREFIX_.'tax t ON p.id_tax = t.id_tax
		// INNER JOIN '._DB_PREFIX_.'tax_lang tl ON tl.id_tax = t.id_tax AND tl.id_lang = '.(int)$cookie->id_lang.'
		// WHERE cp.id_cart = '.(int)$cart->id.'
		// UNION
		// SELECT t2.id_tax, tl2.name, t2.rate
		// FROM '._DB_PREFIX_.'cart c
		// INNER JOIN '._DB_PREFIX_.'carrier ca ON c.id_carrier = ca.id_carrier
		// INNER JOIN '._DB_PREFIX_.'tax t2 ON ca.id_tax = t2.id_tax
		// INNER JOIN '._DB_PREFIX_.'tax_lang tl2 ON tl2.id_tax = t2.id_tax AND tl2.id_lang = '.(int)$cookie->id_lang.'
		// WHERE c.id_cart = '.(int)$cart->id);
		// foreach ($result as $row)
		// {
			// $tax = new HIPAY_MAPI_Tax();
			// $tax->setTaxName($row['name']);
			// $tax->setTaxVal($row['rate']);
			// if (!$tax->check())
				// return $this->l('[Hipay] Error: cannot create Tax');
			// $taxes[$row['id_tax']] = $tax;
		// }

		// $items = array();
		// foreach ($cart->getProducts($cookie->id_lang) as $product)
		// {
			// $item = new HIPAY_MAPI_Product();
			// $item->setName($product['name']);
			// $item->setInfo($product['reference']);
			// $item->setquantity($product['cart_quantity']);
			// $item->setRef($product['id_product'].($product['id_product_attribute'] ? '-'.$product['id_product_attribute'] : ''));
			// $item->setCategory(Configuration::get('HIPAY_CATEGORY_'.(int)$product['id_category_default']) ? Configuration::get('HIPAY_CATEGORY_'.(int)$product['id_category_default']) : Configuration::get('HIPAY_CATEGORY'));
			// $price = Product::getPriceStatic($product['id_product'], false, $product['id_product_attribute'], 2, NULL, false, true, $product['cart_quantity']);
			// $item->setPrice($price);
			// if (Tax::checkTaxZone($product['id_tax'], $id_zone))
		       	  // $item->setTax(array($taxes[$product['id_tax']]));
			// if (!$item->check())
			    // return $this->l('[Hipay] Error: cannot create Product').' ('.$product['id_product'].($product['id_product_attribute'] ? '-'.$product['id_product_attribute'] : '').')';
			// $items[] = $item;
		// }
		

			$item = new HIPAY_MAPI_Product();
			$item->setName($this->l('Cart'));
			$item->setInfo('');
			$item->setquantity(1);
			$item->setRef($cart->id);
			$item->setCategory(Configuration::get('HIPAY_CATEGORY'));
			$item->setPrice($cart->getOrderTotal());
			if (!$item->check())
			    return $this->l('[Hipay] Error: cannot create "Cart" Product');
			$items = array($item);


		// foreach ($cart->getDiscounts() as $voucher)
		// {
			/* For the moment, if there is a voucher you can't use hipay */
			// return;

			// $item = new HIPAY_MAPI_Product();
			// $item->setName($voucher['name']);
			// $item->setInfo($voucher['description']);
			// $item->setquantity(1);
			// $item->setRef('voucher_'.$voucher['id_discount']);
			// $item->setCategory(Configuration::get('HIPAY_CATEGORY'));
			// $item->setPrice(-1 * $voucher['value_real']);
			// if (!$item->check())
			    // return $this->l('[Hipay] Error: cannot create Voucher').' ('.$voucher['name'].')';
			// $items[] = $item;
		// }

		$order = new HIPAY_MAPI_Order();
		$order->setOrderTitle(Configuration::get('PS_SHOP_NAME'));
		$order->setOrderCategory(Configuration::get('HIPAY_CATEGORY'));
		// $price = $cart->getOrderShippingCost($carrier->id, false);
		// $shippingTax = (Tax::checkTaxZone($carrier->id_tax, $id_zone) ? array($taxes[$carrier->id_tax]) : array());
		// $order->setShipping($price, $shippingTax);

		if (!$order->check())
		    return $this->l('[Hipay] Error: cannot create Order');

		try {
			$commande = new HIPAY_MAPI_SimplePayment($paymentParams, $order, $items);
		} catch (Exception $e) {
		  	return $this->l('[Hipay] Error:').' '.$e->getMessage();
		}

		$xmlTx = $commande->getXML();
		//d(htmlentities($xmlTx));
		$output = HIPAY_MAPI_SEND_XML::sendXML($xmlTx);
		$reply = HIPAY_MAPI_COMM_XML::analyzeResponseXML($output, $url, $err_msg, $err_keyword, $err_value, $err_code);

		if ($reply === true)
			Tools::redirectLink($url);
		else
		{
			global $smarty;
			include(dirname(__FILE__).'/../../header.php');
			$smarty->assign('errors', array('[Hipay] '.strval($err_msg).' ('.$output.')'));
			$_SERVER['HTTP_REFERER'] = Tools::getHttpHost(true, true).__PS_BASE_URI__.'order.php?step=3';
			$smarty->display(_PS_THEME_DIR_.'errors.tpl');
			include(dirname(__FILE__).'/../../footer.php');
		}
	}

	public function validation()
	{
		if (!array_key_exists('xml', $_POST))
			return;

		require_once(dirname(__FILE__).'/mapi/mapi_package.php');

		if (HIPAY_MAPI_COMM_XML::analyzeNotificationXML($_POST['xml'], $operation, $status, $date, $time, $transid, $amount, $currency, $id_cart, $data) === false)
			file_put_contents('logs'.Configuration::get('HIPAY_UNIQID').'.txt', '['.date('Y-m-d H:i:s').'] '.$_POST['xml']."\n", FILE_APPEND);

		$orderStatus = _PS_OS_ERROR_;
		$orderMessage = $operation.': '.$status."\n".'date: '.$date.' '.$time."\n".'transaction: '.$transid."\n".'amount: '.(float)$amount.' '.$currency."\n".'id_cart: '.(int)$id_cart;
        if (trim($operation) == 'authorization' AND trim(strtolower($status)) == 'ok')
        {
            /* Autorisation accepté */
            $orderStatus = _PS_OS_PAYMENT_;
        }
        elseif (trim($operation) == 'capture' AND trim(strtolower($status)) == 'ok')
        {
            /* Paiement capturé sur Hipay = Paiement accepté sur Prestashop */
            $orderStatus = _PS_OS_PAYMENT_;
            $this->validateOrder((int)$id_cart, (int)$orderStatus, (float)$amount, $this->displayName, $orderMessage);
        }
        elseif (trim($operation) == 'refund' AND trim(strtolower($status)) == 'ok')
        {
			
            /* Paiement remboursé sur Hipay */
			if (!($id_order = Order::getOrderByCartId(intval($id_cart))))
				die(Tools::displayError());
            $order = new Order(intval($id_order));
            if (!$order->valid OR $order->getCurrentState() === _PS_OS_REFUND_)
				die(Tools::displayError());
			$orderHistory = new OrderHistory();
			$orderHistory->id_order = intval($order->id);
			$orderHistory->changeIdOrderState(intval(_PS_OS_REFUND_), intval($id_order));
			$orderHistory->addWithemail();
        }
	}

	public function getContent()
	{
		global $currentIndex, $cookie;

		$currencies = DB::getInstance()->ExecuteS('SELECT c.iso_code, c.name, c.sign FROM '._DB_PREFIX_.'currency c');
		
		if (Tools::isSubmit('submitHipay'))
		{
			Configuration::updateValue('HIPAY_PROD', Tools::getValue('HIPAY_PROD'));
			$this->prod = (int)Tools::getValue('HIPAY_PROD', Configuration::get('HIPAY_PROD'));
			
			foreach ($currencies as $currency)
			{
				Configuration::updateValue('HIPAY_ACCOUNT_'.$currency['iso_code'], Tools::getValue('HIPAY_ACCOUNT_'.$currency['iso_code']));
				Configuration::updateValue('HIPAY_ACCOUNT_TEST_'.$currency['iso_code'], Tools::getValue('HIPAY_ACCOUNT_TEST_'.$currency['iso_code']));
			}
			Configuration::updateValue('HIPAY_PASSWORD', Tools::getValue('HIPAY_PASSWORD'));
			Configuration::updateValue('HIPAY_PASSWORD_TEST', Tools::getValue('HIPAY_PASSWORD_TEST'));
			Configuration::updateValue('HIPAY_SITEID', Tools::getValue('HIPAY_SITEID'));
			Configuration::updateValue('HIPAY_SITEID_TEST', Tools::getValue('HIPAY_SITEID_TEST'));
			
			Configuration::updateValue('HIPAY_RATING', Tools::getValue('HIPAY_RATING'));
			Configuration::updateValue('HIPAY_CATEGORY', Tools::getValue('HIPAY_CATEGORY'));

			Tools::redirectAdmin($currentIndex.'&configure='.$this->name.'&token='.Tools::getValue('token').'&conf=4');
		}

		$hipaySiteID = (int)($this->prod ? Configuration::get('HIPAY_SITEID') : Configuration::get('HIPAY_SITEID_TEST'));
		
		// Check configuration
		$allow_url_fopen = ini_get('allow_url_fopen');
		$openssl = extension_loaded('openssl');
		$curl = extension_loaded('curl');
		$ping = ($allow_url_fopen AND $openssl AND file_exists(HIPAY_GATEWAY_URL));
		$siteid = ($allow_url_fopen AND $openssl AND $hipaySiteID AND file_exists('https://'.($this->prod ? '' : 'test.').'www.hipay.com/payment-order/list-categories/id/'.$hipaySiteID));
		if (!$allow_url_fopen OR !$openssl OR !$curl OR !$ping)
		{
			echo '
			<div class="warning warn">
				'.($allow_url_fopen ? '' : '<h3>'.$this->l('You are not allowed to open external URLs (allow_url_fopen)').'</h3>').'
				'.($curl ? '' : '<h3>'.$this->l('cURL is not enabled').'</h3>').'
				'.($openssl ? '' : '<h3>'.$this->l('OpenSSL is not enabled').'</h3>').'
				'.(($allow_url_fopen AND $openssl AND !$ping) ? '<h3>'.$this->l('Cannot access payment gateway').' '.HIPAY_GATEWAY_URL.' ('.$this->l('check your firewall').')</h3>' : '').'
				'.(($allow_url_fopen AND $openssl AND $hipaySiteID AND !$siteid) ? '<h3>'.$this->l('Cannot retrieve Hipay categories (check your Site ID)').'</h3>' : '').'
			</div>';
		}

		$link = $currentIndex.'&configure='.$this->name.'&token='.Tools::getValue('token');
		$form = '
		<fieldset><legend><img src="../modules/'.$this->name.'/logo.gif" /> '.$this->l('Hipay').'</legend>
			'.$this->l('Hipay is a secure electronic wallet which provides, to the merchants, a complete service package for online business transactions: whether for digital contents, software, music, subscriptions, physical goods…without having to negotiate with a bank and without technical charges.').'<br />'.$this->l('Free & Easy, Hipay implementation is a real asset to an e-commerce website that wants to expand in Europe: secure payments by international cards, local payment solutions, bank transfers…').'
			<br /><br />
			<ul>
			'.(Configuration::get('HIPAY_SITEID')
				? '<li><a href="https://www.hipay.com/auth" style="color:#D9263F;font-weight:700">'.$this->l('Log in to your merchant account').'</a></li>'
				: '<li><a href="https://www.hipay.com/info/merchants" style="color:#D9263F;font-weight:700">'.$this->l('Create a hipay account').'</a></li>').'
			'.(Configuration::get('HIPAY_SITEID_TEST')
				? '<li><a href="https://test.www.hipay.com/auth" style="color:#D9263F;font-weight:700">'.$this->l('Log in to your test account').'</a></li>'
				: '<li><a href="https://test.www.hipay.com/registration/register" style="color:#D9263F">'.$this->l('Create a test account').'</a></li>').'
			</ul>
		</fieldset>
		<div class="clear">&nbsp;</div>
		<fieldset><legend><img src="../modules/'.$this->name.'/logo.gif" /> '.$this->l('Configuration').'</legend>
			<form action="'.$link.'" method="post">
				<table cellspacing="0" border="0">
					<tr style="height:40px;">
						<td><b>'.$this->l('Account').'</b></td>
						<td class="hipay_prod" style="width:160px;padding-left:8px">
							<input type="radio" name="HIPAY_PROD" value="1" '.((int)$this->prod ? 'checked="checked"' : '').'
								onclick="switchHipayAccount(1);" />
							<span class="hipay_prod_span">'.$this->l('real / production').'</span>
						</td>
						<td class="hipay_test" style="width:160px;padding-left:8px">
							<input type="radio" name="HIPAY_PROD" value="0" '.((int)$this->prod ? '' : 'checked="checked"').'
								onclick="switchHipayAccount(0);" />
								<span class="hipay_test_span">'.$this->l('sandbox / test').'</span><br />
						</td>
					</tr>';
		foreach ($currencies as $currency)
			$form .= '<tr>
						<td style="width: 250px">
							<b>'.$this->l('Account number').' ('.$currency['name'].' '.$currency['sign'].')</b>
							<p style="color:#7F7F7F;font-size:0.85em;">'.$this->l('eg.').' <a href="../modules/'.$this->name.'/screenshots/accountnumber.png" target="_blank">'.$this->l('screenshot').'</a></p>
						</td>
						<td class="hipay_prod" style="width:160px;padding-left:8px"><input type="text" id="HIPAY_ACCOUNT_'.$currency['iso_code'].'" name="HIPAY_ACCOUNT_'.$currency['iso_code'].'" value="'.Tools::getValue('HIPAY_ACCOUNT_'.$currency['iso_code'], Configuration::get('HIPAY_ACCOUNT_'.$currency['iso_code'])).'" /></td>
						<td class="hipay_test" style="width:160px;padding-left:8px"><input type="text" id="HIPAY_ACCOUNT_TEST_'.$currency['iso_code'].'" name="HIPAY_ACCOUNT_TEST_'.$currency['iso_code'].'" value="'.Tools::getValue('HIPAY_ACCOUNT_TEST_'.$currency['iso_code'], Configuration::get('HIPAY_ACCOUNT_TEST_'.$currency['iso_code'])).'" /></td>
					</tr>';
		$form .= '	<tr><td>&nbsp;</td><td class="hipay_prod">&nbsp;</td><td class="hipay_test">&nbsp;</td></tr>
					<tr>
						<td style="width: 250px">
							<b>'.$this->l('Merchant password').'</b>
							<p style="color:#7F7F7F;font-size:0.85em;">'.$this->l('eg.').' <a href="../modules/'.$this->name.'/screenshots/merchantpassword.png" target="_blank">'.$this->l('screenshot').'</a></p>
						</td>
						<td class="hipay_prod" style="width:160px;padding-left:8px"><input type="text" id="HIPAY_PASSWORD" name="HIPAY_PASSWORD" value="'.Tools::getValue('HIPAY_PASSWORD', Configuration::get('HIPAY_PASSWORD')).'" /></td>
						<td class="hipay_test" style="width:160px;padding-left:8px"><input type="text" id="HIPAY_PASSWORD_TEST" name="HIPAY_PASSWORD_TEST" value="'.Tools::getValue('HIPAY_PASSWORD_TEST', Configuration::get('HIPAY_PASSWORD_TEST')).'" /></td>
					</tr>
					<tr>
						<td style="width: 250px">
							<b>'.$this->l('Site ID').'</b>
							<p style="color:#7F7F7F;font-size:0.85em;">'.$this->l('eg.').' <a href="../modules/'.$this->name.'/screenshots/siteid.png" target="_blank">'.$this->l('screenshot').'</a></p>
						</td>
						<td class="hipay_prod" style="width:160px;padding-left:8px"><input type="text" id="HIPAY_SITEID" name="HIPAY_SITEID" value="'.Tools::getValue('HIPAY_SITEID', Configuration::get('HIPAY_SITEID')).'" /></td>
						<td class="hipay_test" style="width:160px;padding-left:8px"><input type="text" id="HIPAY_SITEID_TEST" name="HIPAY_SITEID_TEST" value="'.Tools::getValue('HIPAY_SITEID_TEST', Configuration::get('HIPAY_SITEID_TEST')).'" /></td>
					</tr>
				</table>';
		if ($siteid)
		{
		    $form .= '<hr class="clear" />
	          	<label for="HIPAY_RATING">'.$this->l('Authorized age group').'</label>
				<div class="margin-form">
					<select id="HIPAY_RATING" name="HIPAY_RATING">
						<option value="ALL">'.$this->l('For all ages').'</option>
						<option value="12+" '.(Tools::getValue('HIPAY_RATING', Configuration::get('HIPAY_RATING')) == '12+' ? 'selected="selected"' : '').'>'.$this->l('For ages 12 and over').'</option>
						<option value="16+" '.(Tools::getValue('HIPAY_RATING', Configuration::get('HIPAY_RATING')) == '16+' ? 'selected="selected"' : '').'>'.$this->l('For ages 16 and over').'</option>
						<option value="18+" '.(Tools::getValue('HIPAY_RATING', Configuration::get('HIPAY_RATING')) == '18+' ? 'selected="selected"' : '').'>'.$this->l('For ages 18 and over').'</option>
					</select>
				</div>
				<div class="clear">&nbsp;</div>
	          	<label for="HIPAY_CATEGORY">'.$this->l('Main category').'</label>
				<div class="margin-form">
					<select id="HIPAY_CATEGORY" name="HIPAY_CATEGORY">';
			foreach ($this->getHipayCategories() as $id => $name)
				$form.= '<option value="'.(int)$id.'" '.(Tools::getValue('HIPAY_CATEGORY', Configuration::get('HIPAY_CATEGORY')) == $id ? 'selected="selected"' : '').'>'.htmlentities($name, ENT_COMPAT, 'UTF-8').'</option>';
			$form .= '	</select>
					'.(!count($this->getHipayCategories()) ? '<p style="color:red">'.$this->l('Impossible to retrieve Hipay categories. Please refer to your error log for more details.').'</p>' : '').'
				</div>';
		}
		$form .= '<hr class="clear" />
				<input type="submit" name="submitHipay" value="'.$this->l('Update configuration').'" class="button" />
        	</form>
		</fieldset>
		<script type="text/javascript">
			function switchHipayAccount(prod)
			{
				if (prod)
				{';
			foreach ($currencies as $currency)
				$form .= '
					$("#HIPAY_ACCOUNT_'.$currency['iso_code'].'").css("background-color", "#FFFFFF");
					$("#HIPAY_ACCOUNT_TEST_'.$currency['iso_code'].'").css("background-color", "#EEEEEE");';
			$form .= '
					$("#HIPAY_PASSWORD").css("background-color", "#FFFFFF");
					$("#HIPAY_PASSWORD_TEST").css("background-color", "#EEEEEE");
					
					$("#HIPAY_SITEID").css("background-color", "#FFFFFF");
					$("#HIPAY_SITEID_TEST").css("background-color", "#EEEEEE");
					
					$(".hipay_prod").css("background-color", "#AADEAA");
					$(".hipay_test").css("background-color", "transparent");
					$(".hipay_prod_span").css("font-weight", "700");
					$(".hipay_test_span").css("font-weight", "200");
				}
				else
				{';
			foreach ($currencies as $currency)
				$form .= '
					$("#HIPAY_ACCOUNT_'.$currency['iso_code'].'").css("background-color", "#EEEEEE");
					$("#HIPAY_ACCOUNT_TEST_'.$currency['iso_code'].'").css("background-color", "#FFFFFF");';
			$form .= '
					$("#HIPAY_PASSWORD").css("background-color", "#EEEEEE");
					$("#HIPAY_PASSWORD_TEST").css("background-color", "#FFFFFF");
					
					$("#HIPAY_SITEID").css("background-color", "#EEEEEE");
					$("#HIPAY_SITEID_TEST").css("background-color", "#FFFFFF");			
					
					$(".hipay_prod").css("background-color", "transparent");
					$(".hipay_test").css("background-color", "#AADEAA");
					$(".hipay_prod_span").css("font-weight", "200");
					$(".hipay_test_span").css("font-weight", "700");
				}
			}
			switchHipayAccount('.(int)$this->prod.');
		</script>';
		return $form;
	}

	private function getHipayCategories()
	{
		if (!is_array($this->arrayCategories))
		{
			$this->arrayCategories = array();
			if ($xml = simplexml_load_string(file_get_contents('https://'.($this->prod ? '' : 'test.').'www.hipay.com/payment-order/list-categories/id/'.Configuration::get('HIPAY_SITEID'))))
			{
				foreach ($xml->children() as $categoriesList)
					foreach ($categoriesList->children() as $category)
						$this->arrayCategories[strval($category['id'])] = strval($category);
			}
		}
		return $this->arrayCategories;
	}
}

?>
