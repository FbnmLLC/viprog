<?php
$pluginData['ir123pay']['type']                        = 'payment';
$pluginData['ir123pay']['name']                        = 'سامانه پرداخت یک دو سه پی';
$pluginData['ir123pay']['uniq']                        = 'ir123pay';
$pluginData['ir123pay']['description']                 = 'پلاگین پرداخت، سامانه پرداخت یک دو سه پی';
$pluginData['ir123pay']['author']['name']              = 'تیم فنی یک دو سه پی';
$pluginData['ir123pay']['author']['url']               = 'https://123pay.ir';
$pluginData['ir123pay']['author']['email']             = 'plugins@123pay.ir';
$pluginData['ir123pay']['field']['config'][1]['title'] = 'کد پذیرنده';
$pluginData['ir123pay']['field']['config'][1]['name']  = 'merchant_id';

function gateway__ir123pay( $data ) {
	global $config, $db, $smarty;
	require_once 'lib/ir123pay.php';

	$merchant_id  = trim( $data['merchant_id'] );
	$amount       = round( $data['amount'] );
	$callback_url = urlencode( $data['callback'] );

	$response = create( $merchant_id, $amount, $callback_url );
	$result   = json_decode( $response );
	if ( $result->status ) {
		$update[ payment_rand ] = $result->RefNum;
		$sql                    = $db->prepare( "UPDATE `payment` SET `payment_rand` = ? WHERE `payment_rand` = ? LIMIT 1" );
		$sql->execute( array( $result->RefNum, $data[ invoice_id ] ) );
		$go = $result->payment_url;
		redirect_to( $go );
	} else {
		$data[ title ]   = 'پیام سیستم';
		$data[ message ] = '<font color="#ff0000">' . $result->message . '</font><br /><a href="index.php" class="button">بازگشت</a>';
		$smarty->assign( 'data', $data );
		$smarty->display( 'message.tpl' );
		exit();
	}
}

function callback__ir123pay( $data ) {
	global $db, $get;
	require_once 'lib/ir123pay.php';

	$merchant_id = trim( $data['merchant_id'] );
	$State       = $_REQUEST['State'];
	$RefNum      = $_REQUEST['RefNum'];
	if ( $State == 'OK' ) {
		$response = verify( $merchant_id, $RefNum );
		$result   = json_decode( $response );
		if ( $result->status ) {
			$sql     = "SELECT * FROM `payment` WHERE `payment_rand` = '$RefNum' LIMIT 1";
			$payment = $db->query( $sql )->fetch();

			if ( $payment ) {
				if ( $payment['payment_amount'] == $result->amount ) {
					if ( $payment['payment_status'] == 1 ) {
						$output['status']     = 1;
						$output['res_num']    = null;
						$output['ref_num']    = $RefNum;
						$output['payment_id'] = $payment['payment_id'];
					} else {
						$output['status']  = 0;
						$output['message'] = 'پرداخت تایید نشد‌';
					}
				} else {
					$output['status']  = 0;
					$output['message'] = 'مبلغ تراکنش صحیح نیست';
				}
			} else {
				$output['status']  = 0;
				$output['message'] = 'درخواست پرداخت شما در سیستم یافت نشد';
			}
		} else {
			$output['status']  = 0;
			$output['message'] = 'پرداخت توسط یک دو سه پی تایید نشد';
		}
	} else {
		$output['status']  = 0;
		$output['message'] = 'پرداخت توسط کاربر لغو شده است';
	}

	return $output;
}
