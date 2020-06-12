<?PHP
##################################################
# ������ "�������������� ���������� ������� �����#
# ��������� � �������� ��������� ����"           #
# MegaKassa API 1.1                              #
# �����: ������������� PSWeb.ru                  #
# ����: psweb.ru                                 #
# email: i@psweb.ru                              #
##################################################
# ������������� �������
function __autoload($name){ include("classes/_class.".$name.".php");}

# ����� ������� 
$config = new config;

# �������
$func = new func;

# ���� ������
$db = new db($config->HostDB, $config->UserDB, $config->PassDB, $config->BaseDB);

// �������� IP-������ ������� ���������� MegaKassa
	$ip_checked = false;
	
	foreach(array(
		'HTTP_X_CLUSTER_CLIENT_IP',
		'HTTP_X_FORWARDED_FOR',
		'HTTP_X_FORWARDED',
		'HTTP_FORWARDED_FOR',
		'HTTP_FORWARDED',
		'HTTP_CLIENT_IP',
		'REMOTE_ADDR'
	) as $param) {
		if(!empty($_SERVER[$param]) && $_SERVER[$param] === '5.196.121.217') {
			$ip_checked = true;
			break;
		}
	}
	if(!$ip_checked) {
		die('error IP');
	}
	
	// �������� �� ������� ������������ �����
	// ���� $payment_time � $debug ����� ���� true ��� empty() ������� �� ��� � ��������
	foreach(array(
		'uid',
		'amount',
		'amount_shop',
		'amount_client',
		'currency',
		'order_id',
		'payment_method_id',
		'payment_method_title',
		'creation_time',
		'client_email',
		'status',
		'signature'
	) as $field) {
		if(empty($_POST[$field])) {
			die('error DATA');
		}
	}
	
	// ��� ��������� ����
	$secret_key	= $config->MKsecret;
	
	// ������������ ������
	$uid					= (int)$_POST['uid'];
	$amount					= (double)$_POST['amount'];
	$amount_shop			= (double)$_POST['amount_shop'];
	$amount_client			= (double)$_POST['amount_client'];
	$currency				= $_POST['currency'];
	$order_id				= $_POST['order_id'];
	$payment_method_id		= (int)$_POST['payment_method_id'];
	$payment_method_title	= $_POST['payment_method_title'];
	$creation_time			= $_POST['creation_time'];
	$payment_time			= $_POST['payment_time'];
	$client_email			= $_POST['client_email'];
	$status					= $_POST['status'];
	$debug					= (!empty($_POST['debug'])) ? '1' : '0';
	$signature				= $_POST['signature'];
		
	// �������� ������
	if(!in_array($currency, array('RUB', 'USD', 'EUR'), true)) {
		die('error CURRENCY');
	}
	
	// �������� ������� �������
	if(!in_array($status, array('success', 'fail'), true)) {
		die('error STATUS');
	}
	
	// �������� ������� ���������
	if(!preg_match('/^[0-9a-f]{32}$/', $signature)) {
		die('error SIGNATURE');
	}
	
	// �������� �������� ���������
	$signature_calc = md5(join(':', array(
		$uid, $amount, $amount_shop, $amount_client, $currency, $order_id,
		$payment_method_id, $payment_method_title, $creation_time, $payment_time,
		$client_email, $status, $debug, $secret_key
	)));

	if($signature_calc !== $signature) {
		die('error CALC SIGNATURE');
	}
	
	$db->Query("SELECT * FROM db_payeer_insert WHERE id = '".$order_id."'");
	if($db->NumRows() == 0){ // ��������� ������� ����� �� ������
		die('error ORDER');
	}

	$megakassa_row = $db->FetchArray();
	if($megakassa_row['status'] == 2){ // ���������, ��� ���� �� �������� ��� ����������
		die('ok 1');
	}
	
	if($megakassa_row['sum'] != $amount){ // ��������� ������������ ����� � ���� � ������ ������
		die('error SUM');
	}
	
	// ��������� �������
	switch($status) {
		case 'success':
		
			// ����� ���������� ������� � Unix timestamp (���� �����)
			$payment_time_ts = strtotime($payment_time);
			$user_id = $megakassa_row['user_id']; // id ������������
			# ���������
			$db->Query("SELECT * FROM db_config WHERE id = '1' LIMIT 1");
			$config_site = $db->FetchArray();
		   
			$db->Query("SELECT user, referer_id FROM db_users_a WHERE id = '".$user_id."' LIMIT 1");
			$user_data = $db->FetchArray();
			$user_name = $user_data['user'];
			$refid = $user_data['referer_id'];

			#---------------------------------------------------------------------------------------------------------
			# ��������� ������ �� ������ ����� ����������
			#---------------------------------------------------------------------------------------------------------
			$serebro = sprintf("%.4f", floatval($config_site['ser_per_wmr'] * $amount) );
			$db->Query("SELECT insert_sum FROM db_users_b WHERE id = '".$user_id."' LIMIT 1");
			$ins_sum = $db->FetchRow();
			$serebro = intval($ins_sum <= 0.01) ? ($serebro + ($serebro * 0.30) ) : ($serebro+($serebro * 0.1)); // ����� ����� ����������
			$add_tree = ( $amount >= 499.99) ? 0 : 0;
			$lsb = time();
			$to_referer = ($serebro * 0.10); // ����� ������ �� ���� ���������� ������ ��������
		   
		   
			# ������ ��� �������
			$default_bill = floor($amount / 300); // 1 ����� �� ������ 300 ������
			if ($amount > 3000) $default_bill += 5;
			elseif ($amount > 1500) $default_bill += 2;
		   
		   
			$db->Query("UPDATE db_users_b SET money_b = money_b + '$serebro', e_t = e_t + '$add_tree', to_referer = to_referer + '$to_referer', last_sbor = '$lsb', insert_sum = insert_sum + '$amount', billet = billet + '$default_bill' WHERE id = '{$user_id}'");
			$db->Query("UPDATE db_payeer_insert SET status = '2' WHERE id = '".$order_id."'");
			// $db->Query("UPDATE db_users_b SET money_b = money_b + '$serebro', e_t = e_t + '$add_tree', to_referer = to_referer + '$to_referer', last_sbor = '$lsb', insert_sum = insert_sum + '$amount' WHERE id = '{$user_id}'");
		   
		   
		   
			# ��������� �������� �������� � ������
			$add_tree_referer = ($ins_sum <= 0.01) ? ", a_t = a_t + 0" : "";
			$db->Query("UPDATE db_users_b SET money_p = money_p + $to_referer, from_referals = from_referals + '$to_referer' {$add_tree_referer} WHERE id = '$refid'");
		   
			# ���������� ����������
			$da = time();
			$dd = $da + 60*60*24*15;
			$db->Query("INSERT INTO db_insert_money (user, user_id, money, serebro, date_add, date_del) 
			VALUES ('$user_name','$user_id','$amount','$serebro','$da','$dd')");
		   
				# �������
				 $competition = new competition($db);
				 $competition->UpdatePoints($user_id, $amount);
				#--------

			# ���������� ���������� �����
				# ��������� �����
$pp = new pay_points($db);
$pp ->UpdatePayPoints($ik_payment_amount,$user_id);
			$db->Query("UPDATE db_stats SET all_insert = all_insert + '$amount' WHERE id = '1'");
			// �������� ����� ��� ��������� � ���������� �������
			die('ok');
		case 'fail':
			die('fail');
	}
?>