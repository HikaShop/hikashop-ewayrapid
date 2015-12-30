<div class="hikashop_eway_end" id="hikashop_eway_end">
<?php
	JRequest::setVar('noform',1);

	if(!$this->response->getErrors()) {
?>
	<span id="hikashop_eway_end_message" class="hikashop_eway_end_message">
		<?php echo JText::sprintf('PLEASE_WAIT_BEFORE_REDIRECTION_TO_X', $this->payment_name); ?><br/>
		<span id="hikashop_eway_button_message"><?php echo JText::_('CLICK_ON_BUTTON_IF_NOT_REDIRECTED'); ?></span>
	</span>

	<span id="hikashop_eway_end_spinner" class="hikashop_eway_end_spinner hikashop_checkout_end_spinner"></span>
	<br/>

	<form id="hikashop_eway_form" name="hikashop_eway_form" action="<?php echo $this->response->SharedPaymentUrl;?>" method="POST">
		<div id="hikashop_eway_end_image" class="hikashop_eway_end_image">
			<input id="hikashop_eway_button" type="submit" class="btn btn-primary" value="<?php echo JText::_('PAY_NOW');?>" name="" alt="<?php echo JText::_('PAY_NOW');?>" onclick="document.getElementById('hikashop_eway_form').submit(); this.disabled = true; return false;"/>
		</div>
	</form>

<script type="text/javascript">
<!--
	document.getElementById('hikashop_eway_form').submit();
//-->
</script>
<!--[if IE]>
<script type="text/javascript">
	document.getElementById('hikashop_eway_button').style.display = 'none';
	document.getElementById('hikashop_eway_button_message').innerHTML = '';
</script>
<![endif]-->
<?php
	}
?>
</div>