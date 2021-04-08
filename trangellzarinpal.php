<?php
/**
 * @package     Joomla - > Site and Administrator payment info
 * @subpackage  com_jgive
 * @subpackage 	Trangell_Zarinpal
 * @copyright   trangell team => https://trangell.com
 * @copyright   Copyright (C) 2017 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

// No direct access
defined('_JEXEC') or die('Restricted access');
jimport('joomla.plugin.plugin');
require_once dirname(__FILE__) . '/trangellzarinpal/helper.php';
if (!class_exists ('checkHack')) {
	require_once( dirname(__FILE__) . '/trangellzarinpal/trangell_inputcheck.php');
}

class PlgPaymentTrangellZarinpal extends JPlugin
{

	public function __construct(& $subject, $config)
	{
		parent::__construct($subject, $config);

		// Set the language in the class
		$config = JFactory::getConfig();
	}

	public function buildLayoutPath($layout)
	{
		$layout = trim($layout);

		if (empty($layout))
		{
			$layout = 'default';
		}

		$app = JFactory::getApplication();
		$core_file = dirname(__FILE__) . '/' . $this->_name . '/' . 'tmpl' . '/' . $layout . '.php';
	
			return  $core_file;
	}

	public function buildLayout($vars, $layout = 'default' )
	{
		// Load the layout & push variables
		ob_start();
		$layout = $this->buildLayoutPath($layout);
		include $layout;
		$html = ob_get_contents();
		ob_end_clean();

		return $html;
	}

	public function onTP_GetInfo($config)
	{
		if (!in_array($this->_name, $config))
		{
			return;
		}

		$obj = new stdClass;
		$obj->name = $this->params->get('plugin_name');
		$obj->id = $this->_name;

		return $obj;
	}

	public function onTP_GetHTML($vars) {
		$app	= JFactory::getApplication();
		$config = JFactory::getConfig();

		$Amount = round($vars->amount,0)/10; // Toman 
		$Description = 'پرداخت برای سایت'.' ' .$config->get( 'sitename' );
		$Email = ''; 
		$Mobile = ''; 
		$CallbackURL = $vars->notify_url;
		

		try {
			 // $client = new SoapClient('https://www.zarinpal.com/pg/services/WebGate/wsdl', ['encoding' => 'UTF-8']);
			//$client = new SoapClient('https://sandbox.zarinpal.com/pg/services/WebGate/wsdl', ['encoding' => 'UTF-8']); // for local

			/*$result = $client->PaymentRequest(
				[
				'MerchantID' => $this->params->get('merchant_id',''),
				'Amount' => $Amount,
				'Description' => $Description,
				'Email' => $Email,
				'Mobile' => $Mobile,
				'CallbackURL' => $CallbackURL,
				]
			);*/
			/////////////////////////////////////////////////////////////////////////
            $data = array("merchant_id" => $this->params->get('merchant_id',''),
                "amount" => $Amount,
                "callback_url" => $CallbackURL,
                "description" => $Description,
                "metadata" => [ "email" => "0","mobile"=>"0"],
            );
            $jsonData = json_encode($data);
            $ch = curl_init('https://api.zarinpal.com/pg/v4/payment/request.json');
            curl_setopt($ch, CURLOPT_USERAGENT, 'ZarinPal Rest Api v1');
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($jsonData)
            ));

            $result = curl_exec($ch);
            $err = curl_error($ch);
            $result = json_decode($result, true, JSON_PRETTY_PRINT);
            curl_close($ch);
            /// /////////////////////////////////////////////////////////////////////
			
			//$resultStatus = abs($result->Status);
			if ($result['data']['code'] == 100) {
			
			// Header('Location: https://www.zarinpal.com/pg/StartPay/'.$result->Authority); 
			//	if ($this->params->get('zaringate', '') == 0){
					//$vars->action_url = 'https://www.zarinpal.com/pg/StartPay/'.$result->Authority;
				//}
				//else {
					$vars->action_url = 'https://www.zarinpal.com/pg/StartPay/'.$result['data']["authority"];
				//}
				$html = $this->buildLayout($vars);

				return $html;
			} else {
				$msg= plgPaymentTrangellZarinpalHelper::getGateMsg('error'); 
				$link = JRoute::_(JUri::root().	'index.php?option=com_jgive&task=donations.cancel&processor=trangellzarinpal&order_id='.$vars->order_id,false);
				$app->redirect($link, '<h2>'.$msg.'  خطای: '.$result['errors']['code'].'</h2>', $msgType='Error');
			}
		}
		catch(Exception $e) {
			$msg= plgPaymentTrangellZarinpalHelper::getGateMsg('error'); 
			$link = JRoute::_(JUri::root().	'index.php?option=com_jgive&task=donations.cancel&processor=trangellzarinpal&order_id='.$vars->order_id,false);
			$app->redirect($link, '<h2>'.$msg.'</h2>', $msgType='Error'); 
		}

	}

	public function onTP_Processpayment($data, $vars = array()) {
		$app	= JFactory::getApplication();		
		$jinput = $app->input;
		$Authority = $jinput->get->get('Authority', 'STRING');
		$status = $jinput->get->get('Status', '', 'STRING');
			
			if (checkHack::checkString($status)){
				if ($status == 'OK') {
					try {
						//$client = new SoapClient('https://www.zarinpal.com/pg/services/WebGate/wsdl', ['encoding' => 'UTF-8']);
						//$client = new SoapClient('https://sandbox.zarinpal.com/pg/services/WebGate/wsdl', ['encoding' => 'UTF-8']); // for local

						/*$result = $client->PaymentVerification(
							[
								'MerchantID' =>$this->params->get('merchant_id',''),
								'Authority' => $Authority,
								'Amount' => round($vars->amount/10,0),
							]
						);*/
						////////////////////////////////////////////////////////////////////////////////
                        $data = array("merchant_id" => $this->params->get('merchant_id',''), "authority" => $Authority, "amount" => round($vars->amount/10,0));
                        $jsonData = json_encode($data);
                        $ch = curl_init('https://api.zarinpal.com/pg/v4/payment/verify.json');
                        curl_setopt($ch, CURLOPT_USERAGENT, 'ZarinPal Rest Api v4');
                        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
                        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                            'Content-Type: application/json',
                            'Content-Length: ' . strlen($jsonData)
                        ));
                        $result = curl_exec($ch);
                        curl_close($ch);
                        $result = json_decode($result, true);
                        /// ///////////////////////////////////////////////////////////////////////////
						//$resultStatus = abs($result->Status);
						if ($result['data']['code'] == 100) {
							
							$msg= plgPaymentTrangellZarinpalHelper::getGateMsg($result['data']['code']);
							JFactory::getApplication()->enqueueMessage('<h2>'.$msg.'</h2>'.'<h3>'. $result['data']['ref_id'] .'شماره پیگری ' .'</h3>', 'Message');
							
							plgPaymentTrangellZarinpalHelper::saveComment($this->params->get('plugin_name'), str_replace('JGOID-','',$vars->order_id), $result['data']['ref_id'] .'شماره پیگری ');
							$result                 = array(
							'transaction_id' => '',
							'order_id' => $vars->order_id,
							'status' => 'C',
							'total_paid_amt' => $vars->amount,
							'raw_data' => '',
							'error' => '',
							'return' => $vars->return
							);

							return $result;
						} 
						else {
							$msg= plgPaymentTrangellZarinpalHelper::getGateMsg($result['errors']['code']);
							$link = JRoute::_(JUri::root().	'index.php?option=com_jgive&task=donations.cancel&processor=trangellzarinpal&order_id='.$vars->order_id,false);
							$app->redirect($link, '<h2>'.$msg.'</h2>', $msgType='Error'); 
							return false;
						}
					}
					catch(Exception $e) {
						$msg= plgPaymentTrangellZarinpalHelper::getGateMsg('error'); 
						$link = JRoute::_(JUri::root().	'index.php?option=com_jgive&task=donations.cancel&processor=trangellzarinpal&order_id='.$vars->order_id,false);
						$app->redirect($link, '<h2>'.$msg.'</h2>', $msgType='Error'); 
						return false;
					}
			}
			else {
				$msg= plgPaymentTrangellZarinpalHelper::getGateMsg(intval(17)); 
				$link = JRoute::_(JUri::root().	'index.php?option=com_jgive&task=donations.cancel&processor=trangellzarinpal&order_id='.$vars->order_id,false);
				$app->redirect($link, '<h2>'.$msg.'</h2>', $msgType='Error'); 
				return false;	
			}
		}
		else {
			$msg= plgPaymentTrangellZarinpalHelper::getGateMsg('hck2'); 
			$link = JRoute::_(JUri::root().	'index.php?option=com_jgive&task=donations.cancel&processor=trangellzarinpal&order_id='.$vars->order_id,false);
			$app->redirect($link, '<h2>'.$msg.'</h2>' , $msgType='Error'); 
			return false;	
		}
	}


	public function onTP_Storelog($data)
	{
		$log_write = $this->params->get('log_write', '0');

		if ($log_write == 1)
		{
			$log = plgPaymentTrangellZarinpalHelper::Storelog($this->_name, $data);
		}
	}
}
