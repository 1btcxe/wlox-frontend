<?php
include '../cfg/cfg.php';

if (User::$info['locked'] == 'Y' || User::$info['deactivated'] == 'Y')
	Link::redirect('settings.php');
elseif (User::$awaiting_token)
	Link::redirect('verify-token.php');
elseif (!User::isLoggedIn())
	Link::redirect('login.php');

$order_id1 = ereg_replace("[^0-9]", "",$_REQUEST['order_id']);
$bypass = $_REQUEST['bypass'];

API::add('Orders','getRecord',array($order_id1));
API::add('FeeSchedule','getRecord',array(User::$info['fee_schedule']));
API::add('User','getAvailable');
$query = API::send();

$order_info = $query['Orders']['getRecord']['results'][0];
$user_fee = $query['FeeSchedule']['getRecord']['results'][0];
$user_available = $query['User']['getAvailable']['results'][0];

API::add('Currencies','getRecord',array(false,$order_info['currency']));
$query = API::send();
$currency_info = $query['Currencies']['getRecord']['results'][0];
$currency1 = strtolower($currency_info['currency']);

API::add('Orders','getCurrentBid',array($currency1));
API::add('Orders','getCurrentAsk',array($currency1));
API::add('Orders','get',array(false,false,10,$currency1,false,false,1));
API::add('Orders','get',array(false,false,10,$currency1,false,false,false,false,1));
$query = API::send();

$current_bid = $query['Orders']['getCurrentBid']['results'][0];
$current_ask = $query['Orders']['getCurrentAsk']['results'][0];
$bids = $query['Orders']['get']['results'][0];
$asks = $query['Orders']['get']['results'][1];

if ($order_info['site_user'] != $order_info['user_id'] || !($order_info['id'] > 0)) {
	Link::redirect('open-orders.php');
	exit;
}

if ($order_info['is_bid']) {
	$buy_amount1 = ($_REQUEST['buy_amount'] > 0) ? ereg_replace("[^0-9.]", "",$_REQUEST['buy_amount']) : $order_info['btc'];
	$buy_price1 = ($_REQUEST['buy_price'] > 0) ? ereg_replace("[^0-9.]", "",$_REQUEST['buy_price']) : $order_info['btc_price'];
	$buy_subtotal1 = $buy_amount1 * $buy_price1;
	$buy_fee_amount1 = ($user_fee['fee'] * 0.01) * $buy_subtotal1;
	$buy_total1 = $buy_subtotal1 + $buy_fee_amount1;
	$buy_market_price1 = ($_REQUEST['buy_market_price']) ? $_REQUEST['buy_market_price'] : ($order_info['market_price'] == 'Y');
	$pre_fiat_available = $user_available[strtoupper($currency1)];
	$user_available[strtoupper($currency1)] += ($order_info['btc'] * $order_info['btc_price']) + (($user_fee['fee'] * 0.01) * ($order_info['btc'] * $order_info['btc_price']));
}
else {
	$sell_amount1 = ($_REQUEST['sell_amount'] > 0) ? ereg_replace("[^0-9.]", "",$_REQUEST['sell_amount']) : $order_info['btc'];
	$sell_price1 = ($_REQUEST['sell_price'] > 0) ? ereg_replace("[^0-9.]", "",$_REQUEST['sell_price']) : $order_info['btc_price'];
	$sell_subtotal1 = $sell_amount1 * $sell_price1;
	$sell_fee_amount1 = ($user_fee['fee'] * 0.01) * $sell_subtotal1;
	$sell_total1 = $sell_subtotal1 - $sell_fee_amount1;
	$sell_market_price1 = ($_REQUEST['sell_market_price']) ? $_REQUEST['sell_market_price'] : ($order_info['market_price'] == 'Y');
	$pre_btc_available = $user_available['BTC'];
	$user_available['BTC'] += $order_info['btc'];
}

if ($_REQUEST['buy']) {
	$buy_market_price1 = ereg_replace("[^0-9]", "",$_REQUEST['buy_market_price']);

	if (!($buy_amount1 > 0))
		Errors::add(Lang::string('buy-errors-no-amount'));
	if (!($_REQUEST['buy_price'] > 0))
		Errors::add(Lang::string('buy-errors-no-price'));
	if ($buy_subtotal1 > $user_available[strtoupper($currency1)])
		Errors::add(Lang::string('buy-errors-balance-too-low'));
	
	if (!is_array(Errors::$errors)) {
		API::add('Orders','executeOrder',array(1,$buy_price1,$buy_amount1,$currency1,$user_fee['fee'],$buy_market_price1,$order_info['id']));
		$query = API::send();
		$operations = $query['Orders']['executeOrder']['results'][0];
		
		if ($operations['edit_order'] > 0) {
			Link::redirect('open-orders.php',array('transactions'=>$operations['transactions'],'edit_order'=>1));
			exit;
		}
		else {
			Link::redirect('transactions.php',array('transactions'=>$operations['transactions']));
			exit;
		}
	}
}

if ($_REQUEST['sell']) {
	$sell_market_price1 = ereg_replace("[^0-9]", "",$_REQUEST['sell_market_price']);
	
	if (!($sell_amount1 > 0))
		Errors::add(Lang::string('sell-errors-no-amount'));
	if (!($_REQUEST['sell_price'] > 0))
		Errors::add(Lang::string('sell-errors-no-price'));
	if ($sell_amount1 > $user_available['BTC'])
		Errors::add(Lang::string('sell-errors-balance-too-low'));

	if (!is_array(Errors::$errors)) {
		API::add('Orders','executeOrder',array(0,$sell_price1,$sell_amount1,$currency1,$user_fee['fee'],$sell_market_price1,$order_info['id']));
		$query = API::send();
		$operations = $query['Orders']['executeOrder']['results'][0];
		
		if ($operations['edit_order'] > 0) {
			Link::redirect('open-orders.php',array('transactions'=>$operations['transactions'],'edit_order'=>1));
			exit;
		}
		else {
			Link::redirect('transactions.php',array('transactions'=>$operations['transactions']));
			exit;
		}
	}
}

$page_title = Lang::string('edit-order');
if (!$bypass) {
	include 'includes/head.php';
?>
<div class="page_title">
	<div class="container">
		<div class="title"><h1><?= $page_title ?></h1></div>
        <div class="pagenation">&nbsp;<a href="index.php"><?= Lang::string('home') ?></a> <i>/</i> <a href="account.php"><?= Lang::string('account') ?></a> <i>/</i> <a href="buy-sell.php"><?= $page_title ?></a></div>
	</div>
</div>
<div class="container">
	<? include 'includes/sidebar_account.php'; ?>
	<div class="content_right">
		<? Errors::display(); ?>
		<div class="testimonials-4">
			<input type="hidden" id="user_fee" value="<?= $user_fee['fee'] ?>" />
			<input type="hidden" id="is_bid" value="<?= $order_info['is_bid'] ?>" />
			<input type="hidden" id="edit_order" value="1" />
			<input type="hidden" id="pre_btc_available" value="<?= $pre_btc_available ?>" />
			<input type="hidden" id="pre_fiat_available" value="<?= $pre_fiat_available ?>" />
			<div class="one_half last" <?= ($order_info['is_bid']) ? '' : 'style="display:none;"' ?>>
				<div class="content">
					<h3 class="section_label">
						<span class="left"><i class="fa fa-btc fa-2x"></i></span>
						<span class="right"><?= $page_title ?></span>
					</h3>
					<div class="clear"></div>
					<form id="buy_form" action="edit-order.php" method="POST">
						<input type="hidden" name="order_id" value="<?= $order_info['id'] ?>" />
						<div class="buyform">
							<div class="spacer"></div>
							<div class="calc dotted">
								<div class="label"><?= str_replace('[currency]','<span class="sell_currency_label">'.$currency_info['currency'].'</span>',Lang::string('buy-fiat-available')) ?></div>
								<div class="value"><span class="buy_currency_char"><?= $currency_info['fa_symbol'] ?></span><span id="buy_user_available"><?= number_format($user_available[strtoupper($currency1)],2) ?></span></div>
								<div class="clear"></div>
							</div>
							<div class="spacer"></div>
							<div class="param">
								<label for="buy_amount"><?= Lang::string('buy-amount') ?></label>
								<input name="buy_amount" id="buy_amount" type="text" value="<?= $buy_amount1 ?>" />
								<div class="qualify">BTC</div>
								<div class="clear"></div>
							</div>
							<div class="param">
								<label for="buy_currency"><?= Lang::string('buy-with-currency') ?></label>
								<select id="buy_currency" name="currency" disabled="disabled">
								<?
								if ($CFG->currencies) {
									foreach ($CFG->currencies as $currency) {
										echo '<option '.(($currency['id'] == $order_info['currency']) ? 'selected="selected"' : '').' value="'.$currency['id'].'">'.$currency['currency'].'</option>';
									}
								}	
								?>
								</select>
								<div class="clear"></div>
							</div>
							<div class="param lessbottom">
								<input class="checkbox" name="buy_market_price" id="buy_market_price" type="checkbox" value="1" <?= ($buy_market_price1) ? 'checked="checked"' : '' ?> />
								<label for="buy_market_price"><?= Lang::string('buy-market-price') ?> <a title="<?= Lang::string('buy-market-rates-info') ?>" href="help.php#market_sale"><i class="fa fa-question-circle"></i></a></label>
								<div class="clear"></div>
							</div>
							<div class="param">
								<label for="buy_price"><?= Lang::string('buy-price') ?></label>
								<input name="buy_price" id="buy_price" type="text" value="<?= number_format($buy_price1,2) ?>" <?= ($buy_market_price1) ? 'readonly="readonly"' : '' ?> />
								<div class="qualify"><span class="buy_currency_label"><?= $currency_info['currency'] ?></span></div>
								<div class="clear"></div>
							</div>
							<div class="spacer"></div>
							<div class="calc">
								<div class="label"><?= Lang::string('buy-subtotal') ?></div>
								<div class="value"><span class="buy_currency_char"><?= $currency_info['fa_symbol'] ?></span><span id="buy_subtotal"><?= number_format($buy_subtotal1,2) ?></span></div>
								<div class="clear"></div>
							</div>
							<div class="calc">
								<div class="label"><?= Lang::string('buy-fee') ?> <a title="<?= Lang::string('account-view-fee-schedule') ?>" href="fee-schedule.php"><i class="fa fa-question-circle"></i></a></div>
								<div class="value"><span id="buy_user_fee"><?= $user_fee['fee'] ?></span>%</div>
								<div class="clear"></div>
							</div>
							<div class="calc bigger">
								<div class="label">
									<span id="buy_total_approx_label"><?= str_replace('[currency]','<span class="buy_currency_label">'.$currency_info['currency'].'</span>',Lang::string('buy-total-approx')) ?></span>
									<span id="buy_total_label" style="display:none;"><?= Lang::string('buy-total') ?></span>
								</div>
								<div class="value"><span class="buy_currency_char"><?= $currency_info['fa_symbol'] ?></span><span id="buy_total"><?= number_format($buy_total1,2) ?></span></div>
								<div class="clear"></div>
							</div>
							<input type="hidden" name="buy" value="1" />
							<input type="submit" name="submit" value="<?= $page_title ?>" class="but_user" />
						</div>
					</form>
				</div>
			</div>
			<div class="one_half last" <?= (!$order_info['is_bid']) ? '' : 'style="display:none;"' ?>>
				<div class="content">
					<h3 class="section_label">
						<span class="left"><i class="fa fa-usd fa-2x"></i></span>
						<span class="right"><?= $page_title ?></span>
					</h3>
					<div class="clear"></div>
					<form id="sell_form" action="edit-order.php" method="POST">
						<input type="hidden" name="order_id" value="<?= $order_info['id'] ?>" />
						<div class="buyform">
							<div class="spacer"></div>
							<div class="calc dotted">
								<div class="label"><?= Lang::string('sell-btc-available') ?></div>
								<div class="value"><span id="sell_user_available"><?= number_format($user_available['BTC'],8) ?></span> BTC</div>
								<div class="clear"></div>
							</div>
							<div class="spacer"></div>
							<div class="param">
								<label for="sell_amount"><?= Lang::string('sell-amount') ?></label>
								<input name="sell_amount" id="sell_amount" type="text" value="<?= $sell_amount1 ?>" />
								<div class="qualify">BTC</div>
								<div class="clear"></div>
							</div>
							<div class="param">
								<label for="sell_currency"><?= Lang::string('buy-with-currency') ?></label>
								<select id="sell_currency" name="currency" disabled="disabled">
								<?
								if ($CFG->currencies) {
									foreach ($CFG->currencies as $currency) {
										echo '<option '.(($currency['id'] == $order_info['currency']) ? 'selected="selected"' : '').' value="'.$currency['id'].'">'.$currency['currency'].'</option>';
									}
								}	
								?>
								</select>
								<div class="clear"></div>
							</div>
							<div class="param lessbottom">
								<input class="checkbox" name="sell_market_price" id="sell_market_price" type="checkbox" value="1" <?= ($sell_market_price1) ? 'checked="checked"' : '' ?> />
								<label for="sell_market_price"><?= Lang::string('sell-market-price') ?> <a title="<?= Lang::string('buy-market-rates-info') ?>" href="help.php#market_sale"><i class="fa fa-question-circle"></i></a></label>
								<div class="clear"></div>
							</div>
							<div class="param">
								<label for="sell_price"><?= Lang::string('buy-price') ?></label>
								<input name="sell_price" id="sell_price" type="text" value="<?= number_format($sell_price1,2) ?>" <?= ($sell_market_price1) ? 'readonly="readonly"' : '' ?> />
								<div class="qualify"><span class="sell_currency_label"><?= $currency_info['currency'] ?></span></div>
								<div class="clear"></div>
							</div>
							<div class="spacer"></div>
							<div class="calc">
								<div class="label"><?= Lang::string('buy-subtotal') ?></div>
								<div class="value"><span class="sell_currency_char"><?= $currency_info['fa_symbol'] ?></span><span id="sell_subtotal"><?= number_format($sell_subtotal1,2) ?></span></div>
								<div class="clear"></div>
							</div>
							<div class="calc">
								<div class="label"><?= Lang::string('buy-fee') ?> <a title="<?= Lang::string('account-view-fee-schedule') ?>" href="fee-schedule.php"><i class="fa fa-question-circle"></i></a></div>
								<div class="value"><span id="sell_user_fee"><?= $user_fee['fee'] ?></span>%</div>
								<div class="clear"></div>
							</div>
							<div class="calc bigger">
								<div class="label">
									<span id="sell_total_approx_label"><?= str_replace('[currency]','<span class="sell_currency_label">'.$currency_info['currency'].'</span>',Lang::string('sell-total-approx')) ?></span>
									<span id="sell_total_label" style="display:none;"><?= str_replace('[currency]','<span class="sell_currency_label">'.$currency_info['currency'].'</span>',Lang::string('sell-total')) ?></span>
								</div>
								<div class="value"><span class="sell_currency_char"><?= $currency_info['fa_symbol'] ?></span><span id="sell_total"><?= number_format($sell_total1,2) ?></span></div>
								<div class="clear"></div>
							</div>
							<input type="hidden" name="sell" value="1" />
							<input type="submit" name="submit" value="<?= $page_title ?>" class="but_user" />
						</div>
					</form>
				</div>
			</div>
		</div>
		<div class="mar_top3"></div>
		<div class="clear"></div>
		<div id="filters_area">
<? } ?>
			<div class="one_half">
				<h3><?= Lang::string('orders-bid-top-10') ?></h3>
	        	<div class="table-style">
	        		<table class="table-list trades" id="bids_list">
	        			<tr>
	        				<th><?= Lang::string('orders-price') ?></th>
	        				<th><?= Lang::string('orders-amount') ?></th>
	        				<th><?= Lang::string('orders-value') ?></th>
	        			</tr>
	        			<? 
	        			if ($bids) {
							foreach ($bids as $bid) {
								$mine = (User::$info['id'] == $bid['site_user']) ? '<a class="fa fa-user" href="javascript:return false;" title="'.Lang::string('home-your-order').'"></a>' : '';
								echo '
						<tr id="bid_'.$bid['id'].'" class="bid_tr">
							<td>'.$mine.$bid['fa_symbol'].'<span class="order_price">'.number_format($bid['fiat_price'],2).'</span></td>
							<td><span class="order_amount">'.number_format($bid['btc'],8).'</span></td>
							<td>'.$bid['fa_symbol'].'<span class="order_value">'.number_format($bid['fiat'],2).'</span></td>
						</tr>';
							}
						}
						echo '<tr id="no_bids" style="'.(is_array($bids) ? 'display:none;' : '').'"><td colspan="4">'.Lang::string('orders-no-bid').'</td></tr>';
	        			?>
	        		</table>
				</div>
			</div>
			<div class="one_half last">
				<h3><?= Lang::string('orders-ask-top-10') ?></h3>
				<div class="table-style">
					<table class="table-list trades" id="asks_list">
						<tr>
							<th><?= Lang::string('orders-price') ?></th>
	        				<th><?= Lang::string('orders-amount') ?></th>
	        				<th><?= Lang::string('orders-value') ?></th>
						</tr>
	        			<? 
	        			if ($asks) {
							foreach ($asks as $ask) {
								$mine = (User::$info['id'] == $ask['site_user']) ? '<a class="fa fa-user" href="javascript:return false;" title="'.Lang::string('home-your-order').'"></a>' : '';
								echo '
						<tr id="ask_'.$ask['id'].'" class="ask_tr">
							<td>'.$mine.$ask['fa_symbol'].'<span class="order_price">'.number_format($ask['fiat_price'],2).'</span></td>
							<td><span class="order_amount">'.number_format($ask['btc'],8).'</span></td>
							<td>'.$ask['fa_symbol'].'<span class="order_value">'.number_format($ask['fiat'],2).'</span></td>
						</tr>';
							}
						}
						echo '<tr id="no_asks" style="'.(is_array($asks) ? 'display:none;' : '').'"><td colspan="4">'.Lang::string('orders-no-ask').'</td></tr>';
	        			?>
					</table>
				</div>
				<div class="clear"></div>
			</div>
<? if (!$bypass) { ?>
		</div>
		<div class="mar_top5"></div>
	</div>
</div>
<? include 'includes/foot.php'; ?>
<? } ?>