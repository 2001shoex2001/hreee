<?PHP
# Автоподгрузка классов
function __autoload($name){ include("classes/_class.".$name.".php");}

# Класс конфига 
$config = new config;

# Функции
$func = new func;

# База данных
$db = new db($config->HostDB, $config->UserDB, $config->PassDB, $config->BaseDB);

//extract($_POST);

$fk_merchant_id = '101110'; //merchant_id ID мазагина в free-kassa.ru (http://free-kassa.ru/merchant/cabinet/help/)
$fk_merchant_key = '11111'; //Секретное слово http://free-kassa.ru/merchant/cabinet/profile/tech.php
$fk_merchant_key2 = '1111'; //Секретное слово2 (result) http://free-kassa.ru/merchant/cabinet/profile/tech.php

$ik_payment_amount = round(floatval($_POST['AMOUNT']),2);
$user_id = $_POST['us_id'];
	
$hash = md5($fk_merchant_id.":".$_POST['AMOUNT'].":".$fk_merchant_key2.":".$_POST['MERCHANT_ORDER_ID']);

if ($hash != $_POST['SIGN']) die("SumError");
    
   
   	# Настройки
	$db->Query("SELECT * FROM db_config WHERE id = '1' LIMIT 1");
	$sonfig_site = $db->FetchArray();
   
   $db->Query("SELECT user, referer_id FROM db_users_a WHERE id = '{$user_id}' LIMIT 1");
   $user_ardata = $db->FetchArray();
   $user_name = $user_ardata["user"];
   $refid = $user_ardata["referer_id"];
   
  
   # Зачисляем баланс
   $serebro = sprintf("%.4f", floatval($sonfig_site["ser_per_wmr"] * $ik_payment_amount) );
   
   $db->Query("SELECT ref_proc FROM db_users_b WHERE id = '{$refid}' LIMIT 1");
   $ref_proc = $db->FetchRow();

   $db->Query("SELECT insert_sum FROM db_users_b WHERE id = '{$user_id}' LIMIT 1");
   $ins_sum = $db->FetchRow();


   /* ====== Рефералка 3 уровней ====== */
$db->Query("SELECT user, referer_id, referer_id2, referer_id3 FROM db_users_a WHERE id = '{$user_id}' LIMIT 1");
    $user_ardata = $db->FetchArray();
    $ref2 = $user_ardata["referer_id2"];
    $ref3 = $user_ardata["referer_id3"];

    # Задаем процент рефки
    $to_referer  = ($serebro * 0.07)+($serebro*$ref_proc); // Первый уровень - 10 процента далее растет за уровень.
    $to_referer2 = ($serebro * 0.02); // Второй уровень - 2 процента
    $to_referer3 = ($serebro * 0.01); // Третий уровень - 1 процент

    $db->Query("UPDATE db_users_b SET money_p = money_p + $to_referer2 WHERE id = '$ref2'");
    $db->Query("UPDATE db_users_b SET money_p = money_p + $to_referer3 WHERE id = '$ref3'");
    $db->Query("UPDATE db_users_a SET doxod2 = doxod2 + $to_referer2 WHERE id = '$user_id'");
    $db->Query("UPDATE db_users_a SET doxod3 = doxod3 + $to_referer3 WHERE id = '$user_id'");
    /* ====== /Рефералка 3 уровней ====== */

	if($ik_payment_amount < 100) {
		   $serebro = ($serebro + ($serebro * 0.05));
		                          }
		   elseif ($ik_payment_amount >= 100 and $ik_payment_amount < 300) {
			$serebro = ($serebro + ($serebro * 0.1));
		   }
		   
		   elseif ($ik_payment_amount >= 300 and $ik_payment_amount < 500) {
			$serebro = ($serebro + ($serebro * 0.15));
		   }
		   
		   elseif ($ik_payment_amount >= 500 and $ik_payment_amount < 1000) {
			$serebro = ($serebro + ($serebro * 0.2));
		   }
		   		   
		    elseif ($ik_payment_amount >= 1000) {
			$serebro = ($serebro + ($serebro * 0.25));
		   }

   $lsb = time();
   
   $db->Query("UPDATE db_users_b SET money_b = money_b + '$serebro', pay_points = pay_points + '$to_pay_points', a_t = a_t + '$add_tree', to_referer = to_referer + '$to_referer', last_sbor = '$lsb', insert_sum = insert_sum + '$ik_payment_amount' WHERE id = '{$user_id}'");

   # Зачисляем средства рефереру
   $db->Query("UPDATE db_users_b SET money_p = money_p + $to_referer, from_referals = from_referals + $to_referer WHERE id = '$refid'");

      
   # Статистика пополнений
   $da = time();
   $dd = $da + 60*60*24*15;
   $db->Query("INSERT INTO db_insert_money (user, user_id, money, serebro, date_add, date_del) 
   VALUES ('$user_name','$user_id','$ik_payment_amount','$serebro','$da','$dd')");
  
   # Конкурс инвесторов

   $db->Query("SELECT * FROM db_invcompetition_users WHERE user_id = '{$user_id}'");
$in = $db->FetchArray();

		
$a=$in["user_id"];
if($a > 0)
{
$usname = $user_name;
}
else
{
$usname = $user_name;
$db->Query("INSERT INTO db_invcompetition_users (user, user_id, points) VALUES ('$usname','$user_id','0')");
}

$db->Query("SELECT * FROM db_invcompetition WHERE status = '0' LIMIT 1");
$invcomp = $db->FetchArray();

$db->Query("SELECT COUNT(*) FROM db_invcompetition_users WHERE user_id = '{$user_id}'");
$rett = $db->FetchArray();

if ($invcomp["date_add"] >= 0 AND $invcomp["date_end"] > $da)
{
$db->Query("UPDATE db_invcompetition_users SET points = points + '$ik_payment_amount' WHERE user_id = '$user_id'");
}
else
{
$db->Query("UPDATE db_invcompetition_users SET points = points + '0' WHERE user_id = '$user_id'");
}
# Конкурс
$competition = new competition($db);
$competition->UpdatePoints($user_id, $ik_payment_amount);

# Платежные баллы
$pp = new pay_points($db);
$pp ->UpdatePayPoints($ik_payment_amount,$user_id);

	# Обновление статистики сайта
	$db->Query("UPDATE db_stats SET all_insert = all_insert + '$ik_payment_amount' WHERE id = '1'");

?>