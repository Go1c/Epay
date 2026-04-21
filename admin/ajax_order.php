<?php
include("../includes/common.php");
if($islogin==1){}else exit("<script language='javascript'>window.location.href='./login.php';</script>");
$act=isset($_GET['act'])?daddslashes($_GET['act']):null;

if(!checkRefererHost())exit('{"code":403}');

@header('Content-Type: application/json; charset=UTF-8');

function buildAdminOrderWhere(&$params){
	$where = ['1=1'];
	$allowedColumns = ['trade_no', 'out_trade_no', 'api_trade_no', 'bill_mch_trade_no', 'bill_trade_no', 'name', 'money', 'realmoney', 'getmoney', 'domain', 'buyer', 'ip', 'mobile', 'param'];

	if(isset($_POST['uid']) && $_POST['uid'] !== '') {
		$where[] = "A.`uid`=:uid";
		$params[':uid'] = intval($_POST['uid']);
	}
	if(isset($_POST['type']) && $_POST['type'] !== '') {
		$where[] = "A.`type`=:type";
		$params[':type'] = intval($_POST['type']);
	}elseif(isset($_POST['channel']) && $_POST['channel'] !== '') {
		$where[] = "A.`channel`=:channel";
		$params[':channel'] = intval($_POST['channel']);
	}elseif(isset($_POST['subchannel']) && $_POST['subchannel'] !== '') {
		$where[] = "A.`subchannel`=:subchannel";
		$params[':subchannel'] = intval($_POST['subchannel']);
	}elseif(isset($_POST['applyid']) && $_POST['applyid'] !== '') {
		$where[] = "A.`subchannel` IN (SELECT id FROM pre_subchannel WHERE apply_id=:applyid)";
		$params[':applyid'] = intval($_POST['applyid']);
	}
	if(isset($_POST['dstatus']) && !isNullOrEmpty($_POST['dstatus'])) {
		if(substr($_POST['dstatus'], 0, 6) == 'settle'){
			$where[] = "A.`settle`=:settle_status";
			$params[':settle_status'] = intval(substr($_POST['dstatus'], 7));
		}else{
			$where[] = "A.`status`=:order_status";
			$params[':order_status'] = intval($_POST['dstatus']);
		}
	}
	if(!empty($_POST['starttime'])){
		$where[] = "A.`addtime`>=:starttime";
		$params[':starttime'] = $_POST['starttime'].' 00:00:00';
	}
	if(!empty($_POST['endtime'])){
		$where[] = "A.`addtime`<=:endtime";
		$params[':endtime'] = $_POST['endtime'].' 23:59:59';
	}
	if(isset($_POST['value']) && $_POST['value'] !== '' && isset($_POST['column']) && in_array($_POST['column'], $allowedColumns, true)) {
		$column = $_POST['column'];
		$value = trim($_POST['value']);
		if($column === 'name'){
			$where[] = "A.`name` LIKE :search_name";
			$params[':search_name'] = '%'.$value.'%';
		}elseif(in_array($column, ['money', 'realmoney', 'getmoney'], true) && strpos($value, '-') !== false){
			$money = explode('-', $value, 2);
			if(isset($money[0], $money[1]) && is_numeric(trim($money[0])) && is_numeric(trim($money[1]))){
				$where[] = "A.`{$column}`>=:{$column}_min AND A.`{$column}`<=:{$column}_max";
				$params[":{$column}_min"] = trim($money[0]);
				$params[":{$column}_max"] = trim($money[1]);
			}
		}else{
			$where[] = "A.`{$column}`=:search_value";
			$params[':search_value'] = $value;
		}
	}

	return implode(' AND ', $where);
}

function buildAdminRiskWhere(&$params){
	$where = ['1=1'];
	$allowedColumns = ['uid', 'type', 'url', 'content'];

	if(isset($_POST['value']) && $_POST['value'] !== '' && isset($_POST['column']) && in_array($_POST['column'], $allowedColumns, true)) {
		$where[] = "`{$_POST['column']}`=:risk_value";
		$params[':risk_value'] = $_POST['value'];
	}
	if(isset($_POST['type']) && $_POST['type']>-1) {
		$where[] = "`type`=:risk_type";
		$params[':risk_type'] = intval($_POST['type']);
	}

	return implode(' AND ', $where);
}

switch($act){
case 'orderList':
	$paytype = [];
	$paytypes = [];
	$rs = $DB->getAll("SELECT * FROM pre_type");
	foreach($rs as $row){
		$paytype[$row['id']] = $row['showname'];
		$paytypes[$row['id']] = $row['name'];
	}
	unset($rs);

	$params = [];
	$sql = buildAdminOrderWhere($params);
	$offset = intval($_POST['offset']);
	$limit = intval($_POST['limit']);
	$total = $DB->getColumn("SELECT count(*) from pre_order A WHERE {$sql}", $params);
	$list = $DB->getAll("SELECT A.*,B.plugin,B.name channelname FROM pre_order A LEFT JOIN pre_channel B ON A.channel=B.id WHERE {$sql} order by trade_no desc limit {$offset},{$limit}", $params);
	$list2 = [];
	foreach($list as $row){
		$row['typename'] = $paytypes[$row['type']];
		$row['typeshowname'] = $paytype[$row['type']];
		$list2[] = $row;
	}

	exit(json_encode(['total'=>$total, 'rows'=>$list2]));
break;

case 'statistics':
    $params = [];
	$sql = buildAdminOrderWhere($params);
    // 统计数据
    $resultMoneyData = $DB->getRow("SELECT 
    SUM(money) AS totalMoney,
    SUM(CASE WHEN A.status = 1 THEN money ELSE 0 END) AS successMoney,
    SUM(CASE WHEN A.status = 0 THEN money ELSE 0 END) AS unpaidMoney,
    SUM(CASE WHEN A.status = 2 THEN refundmoney ELSE 0 END) AS refundMoney
    FROM pre_order A LEFT JOIN pre_channel B ON A.channel=B.id WHERE {$sql}", $params);

    $resultCount = $DB->getRow("SELECT 
    COUNT(*) AS totalCount,
    SUM(CASE WHEN A.status = 1 THEN 1 ELSE 0 END) AS successCount,
    SUM(CASE WHEN A.status = 0 THEN 1 ELSE 0 END) AS unpaidCount,
    SUM(CASE WHEN A.status = 2 THEN 1 ELSE 0 END) AS refundCount
    FROM pre_order A LEFT JOIN pre_channel B ON A.channel=B.id WHERE {$sql}", $params);

    // 获取平台总收入利润
    $platformProfit = $DB->getColumn("SELECT SUM(A.profitmoney) FROM pre_order A LEFT JOIN pre_channel B ON A.channel=B.id WHERE {$sql} AND A.status = 1", $params);

	$result = [
        'totalMoney' => number_format($resultMoneyData['totalMoney'], 2, '.', '') ?? 0.00,
        'successMoney' => number_format($resultMoneyData['successMoney'], 2, '.', '') ?? 0.00,
        'unpaidMoney' => number_format($resultMoneyData['unpaidMoney'], 2, '.', '') ?? 0.00,
        'refundMoney' => number_format($resultMoneyData['refundMoney'], 2, '.', '') ?? 0.00,
        'totalCount' => $resultCount['totalCount'] ?? '0',
        'successCount' => $resultCount['successCount'] ?? '0',
        'unpaidCount' => $resultCount['unpaidCount'] ?? '0',
        'refundCount' => $resultCount['refundCount'] ?? '0',
        'platformProfit' => number_format($platformProfit, 2, '.', '') ?? 0.00
    ];
	$result['successRate'] = $result['totalCount'] > 0 ? round(($result['totalCount']-$result['unpaidCount']) / $result['totalCount'] * 100, 2) : 0;
	exit(json_encode(['code'=>0, 'data'=>$result]));
break;

case 'riskList':
	$params = [];
	$sql = buildAdminRiskWhere($params);
	$offset = intval($_POST['offset']);
	$limit = intval($_POST['limit']);
	$total = $DB->getColumn("SELECT count(*) from pre_risk WHERE {$sql}", $params);
	$list = $DB->getAll("SELECT * FROM pre_risk WHERE {$sql} order by id desc limit {$offset},{$limit}", $params);

	exit(json_encode(['total'=>$total, 'rows'=>$list]));
break;

case 'setStatus': //改变订单状态
	$trade_no=trim($_GET['trade_no']);
	$status=is_numeric($_GET['status'])?intval($_GET['status']):exit('{"code":200}');
	if($status==5){
		if($DB->exec("DELETE FROM pre_order WHERE trade_no=:trade_no", [':trade_no'=>$trade_no]))
			exit('{"code":200}');
		else
			exit('{"code":400,"msg":"删除订单失败！['.$DB->error().']"}');
	}else{
		if($DB->exec("update pre_order set status=:status where trade_no=:trade_no", [':status'=>$status, ':trade_no'=>$trade_no])!==false)
			exit('{"code":200}');
		else
			exit('{"code":400,"msg":"修改订单失败！['.$DB->error().']"}');
	}
break;
case 'order': //订单详情
	$trade_no=trim($_GET['trade_no']);
	$row=$DB->getRow("select A.*,B.showname typename,C.name channelname from pre_order A,pre_type B,pre_channel C where trade_no=:trade_no and A.type=B.id and A.channel=C.id limit 1", [':trade_no'=>$trade_no]);
	if(!$row)
		exit('{"code":-1,"msg":"当前订单不存在或未成功选择支付通道！"}');
	$row['subchannelname'] = $row['subchannel'] > 0 ? $DB->findColumn('subchannel', 'name', ['id'=>$row['subchannel']]) : '';
	if($row['status']==2){
		$row['refundtime'] = $DB->findColumn('refundorder', 'addtime', ['trade_no'=>$trade_no], 'refund_no DESC');
	}
	$result=array("code"=>0,"msg"=>"succ","data"=>$row);
	exit(json_encode($result));
break;
case 'subOrders':
	$trade_no=trim($_GET['trade_no']);
	$list = \lib\Payment::getSubOrders($trade_no);
	exit(json_encode(['code'=>0, 'data'=>$list, 'settle'=>$DB->findColumn('order', 'settle', ['trade_no'=>$trade_no])]));
break;
case 'operation': //批量操作订单
	$status=is_numeric($_POST['status'])?intval($_POST['status']):exit('{"code":-1,"msg":"请选择操作"}');
	$checkbox=$_POST['checkbox'];
	$i=0;
	foreach($checkbox as $trade_no){
		if($status==4)$DB->exec("DELETE FROM pre_order WHERE trade_no=:trade_no", [':trade_no'=>$trade_no]);
		elseif($status==3){
			\lib\Order::unfreeze($trade_no);
		}
		elseif($status==2){
			\lib\Order::freeze($trade_no);
		}
		else $DB->exec("update pre_order set status=:status where trade_no=:trade_no limit 1", [':status'=>$status, ':trade_no'=>$trade_no]);
		$i++;
	}
	exit('{"code":0,"msg":"成功改变'.$i.'条订单状态"}');
break;
case 'getmoney': //退款查询
	if(!$conf['admin_paypwd'])exit('{"code":-1,"msg":"你还未设置支付密码"}');
	$trade_no=trim($_POST['trade_no']);
	$api=isset($_POST['api'])?intval($_POST['api']):0;
	$result = \lib\Order::refund_info($trade_no, $api);
	exit(json_encode($result));
break;
case 'refund': //退款操作
	$trade_no=trim($_POST['trade_no']);
	$money = trim($_POST['money']);
	if(!is_numeric($money) || !preg_match('/^[0-9.]+$/', $money))exit('{"code":-1,"msg":"金额输入错误"}');

	$refund_no = date("YmdHis").rand(11111,99999);
	$result = \lib\Order::refund($refund_no, $trade_no, $money);
	if($result['code'] == 0){
		$result['msg'] = '已成功从UID:'.$result['uid'].'扣除'.$result['reducemoney'].'元余额';
	}
	exit(json_encode($result));
break;
case 'apirefund': //API退款操作
	$trade_no=trim($_POST['trade_no']);
	$paypwd=trim($_POST['paypwd']);
	$money = trim($_POST['money']);
	if(!is_numeric($money) || !preg_match('/^[0-9.]+$/', $money))exit('{"code":-1,"msg":"金额输入错误"}');
	if(!verifyConfigSecret($paypwd, $conf['admin_paypwd'], 'admin_paypwd'))
		exit('{"code":-1,"msg":"支付密码输入错误！"}');
	
	$refund_no = date("YmdHis").rand(11111,99999);
	$result = \lib\Order::refund($refund_no, $trade_no, $money, 1);
	if($result['code'] == 0){
		$result['msg'] = '退款成功！退款金额¥'.$result['money'];
		if($result['reducemoney']>0){
			$result['msg'] .= '，并成功从UID:'.$result['uid'].'扣除'.$result['reducemoney'].'元余额';
		}
	}
	exit(json_encode($result));
break;
case 'freeze': //冻结订单
	$trade_no=trim($_POST['trade_no']);
	$result = \lib\Order::freeze($trade_no);
	exit(json_encode($result));
break;
case 'unfreeze': //解冻订单
	$trade_no=trim($_POST['trade_no']);
	$result = \lib\Order::unfreeze($trade_no);
	exit(json_encode($result));
break;
case 'notify': //获取回调地址
	$trade_no=trim($_POST['trade_no']);
	$row=$DB->getRow("select * from pre_order where trade_no='$trade_no' limit 1");
	if(!$row)
		exit('{"code":-1,"msg":"当前订单不存在！"}');
	$url=creat_callback($row);
	if($_POST['isget'] == 1){
		if(do_notify($url['notify'])){
			$DB->exec("UPDATE pre_order SET notify=0 WHERE trade_no='$trade_no'");
			exit('{"code":0}');
		}
		exit('{"code":-1}');
	}
	if($row['notify']>0)
		$DB->exec("update pre_order set notify=0,notifytime=NULL where trade_no='$trade_no'");
	exit('{"code":0,"url":"'.($_POST['isreturn']==1?$url['return']:$url['notify']).'"}');
break;
case 'fillorder': //手动补单
	$trade_no=trim($_POST['trade_no']);
	$row=$DB->getRow("SELECT A.*,B.name typename,B.showname typeshowname FROM pre_order A left join pre_type B on A.type=B.id WHERE trade_no=:trade_no limit 1", [':trade_no'=>$trade_no]);
	if(!$row)
		exit('{"code":-1,"msg":"当前订单不存在！"}');
	if($row['status']>0)exit('{"code":-1,"msg":"当前订单不是未完成状态！"}');
	if($DB->exec("update `pre_order` set `status` ='1' where `trade_no`='$trade_no'")){
		$DB->exec("update `pre_order` set `endtime` ='$date',`date` =NOW() where `trade_no`='$trade_no'");
		$channel=\lib\Channel::get($row['channel']);
		processOrder($row);
	}
	exit('{"code":0,"msg":"补单成功"}');
break;
case 'alipaydSettle': //支付宝直付通确认结算
	$trade_no=trim($_POST['trade_no']);
	$row=$DB->getRow("select * from pre_order where trade_no='$trade_no' limit 1");
	if(!$row)
		exit('{"code":-1,"msg":"当前订单不存在！"}');
	if($row['status']==0)exit('{"code":-1,"msg":"当前订单状态是未支付"}');
	$channel = $row['subchannel'] > 0 ? \lib\Channel::getSub($row['subchannel']) : \lib\Channel::get($row['channel'], $DB->findColumn('user', 'channelinfo', ['uid'=>$row['uid']]));
	if(!$channel){
		exit('{"code":-1,"msg":"当前支付通道信息不存在"}');
	}
	try{
		if($channel['plugin'] == 'alipayd'){
			\lib\Payment::alipaydSettle($channel, $row);
		}elseif($channel['plugin'] == 'wxpaynp'){
			\lib\Payment::wxpaynpSettle($channel, $row);
		}else{
			exit('{"code":-1,"msg":"支付插件不支持该操作"}');
		}
		$DB->exec("update `pre_order` set `settle`=2 where `trade_no`='$trade_no'");
		exit('{"code":0,"msg":"结算成功！"}');
	}catch(Exception $e){
		$DB->exec("update `pre_order` set `settle`=3 where `trade_no`='$trade_no'");
		exit('{"code":-1,"msg":"结算失败,'.$e->getMessage().'"}');
	}
break;
case 'alipayPreAuthPay': //支付宝授权资金支付
	$trade_no=trim($_POST['trade_no']);
	$order=$DB->getRow("select * from pre_order where trade_no='$trade_no' limit 1");
	if(!$order)
		exit('{"code":-1,"msg":"当前订单不存在！"}');
	$channel = $order['subchannel'] > 0 ? \lib\Channel::getSub($order['subchannel']) : \lib\Channel::get($order['channel'], $DB->findColumn('user', 'channelinfo', ['uid'=>$row['uid']]));
	if(!$channel){
		exit('{"code":-1,"msg":"当前支付通道信息不存在"}');
	}
	try{
		$result = \lib\Payment::alipayPreAuthPay($channel, $order);

		$api_trade_no = $result['trade_no'];
		$buyer_id = $result['buyer_user_id'];
		$total_amount = $result['total_amount'];
		processNotify($order, $api_trade_no, $buyer_id);

		exit('{"code":0,"msg":"授权资金支付成功！"}');
	}catch(Exception $e){
		$errmsg = $e->getMessage();
		exit('{"code":-1,"msg":"授权资金支付失败,'.$errmsg.'"}');
	}
break;
case 'alipayUnfreeze': //支付宝授权资金解冻
	$trade_no=trim($_POST['trade_no']);
	$order=$DB->getRow("select * from pre_order where trade_no='$trade_no' limit 1");
	if(!$order)
		exit('{"code":-1,"msg":"当前订单不存在！"}');
	$channel = $order['subchannel'] > 0 ? \lib\Channel::getSub($order['subchannel']) : \lib\Channel::get($order['channel'], $DB->findColumn('user', 'channelinfo', ['uid'=>$row['uid']]));
	if(!$channel){
		exit('{"code":-1,"msg":"当前支付通道信息不存在"}');
	}
	try{
		\lib\Payment::alipayUnfreeze($channel, $order);
		$DB->exec("update `pre_order` set `status`=0 where `trade_no`='$trade_no'");
		exit('{"code":0,"msg":"授权资金解冻成功！"}');
	}catch(Exception $e){
		$errmsg = $e->getMessage();
		exit('{"code":-1,"msg":"授权资金解冻失败,'.$errmsg.'"}');
	}
break;
case 'alipayRedPacketTansfer': //支付宝红包转账重试
	$trade_no=trim($_POST['trade_no']);
	$order=$DB->getRow("select * from pre_order where trade_no='$trade_no' limit 1");
	if(!$order)
		exit('{"code":-1,"msg":"当前订单不存在！"}');
	$channel = $order['subchannel'] > 0 ? \lib\Channel::getSub($order['subchannel']) : \lib\Channel::get($order['channel'], $DB->findColumn('user', 'channelinfo', ['uid'=>$row['uid']]));
	if(!$channel){
		exit('{"code":-1,"msg":"当前支付通道信息不存在"}');
	}
	if(!empty($channel['appmchid'])) $payee_user_id = $channel['appmchid'];
	else $payee_user_id = $DB->findColumn('user', 'alipay_uid', ['uid'=>$order['uid']]);
	if(!$payee_user_id) exit('{"code":-1,"msg":"当前商户未绑定支付宝账号"}');
	try{
		\lib\Payment::alipayRedPacketTransfer($channel, $payee_user_id, $order['money'], $order['api_trade_no']);
		$DB->exec("update `pre_order` set `settle`=2 where `trade_no`='$trade_no'");
		exit('{"code":0,"msg":"红包打款成功！"}');
	}catch(Exception $e){
		$errmsg = $e->getMessage();
		exit('{"code":-1,"msg":"红包打款失败,'.$errmsg.'"}');
	}
break;
default:
	exit('{"code":-4,"msg":"No Act"}');
break;
}
