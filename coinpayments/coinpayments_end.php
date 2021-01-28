<div class="hikashop_coinpayments_end" id="hikashop_coinpayments_end">
	<span id="hikashop_coinpayments_end_message" class="hikashop_coinpayments_end_message">
	</span>
	<span id="hikashop_coinpayments_end_spinner" class="hikashop_coinpayments_end_spinner">
		<img src="<?php echo HIKASHOP_IMAGES . 'spinner.gif'; ?>"/>
	</span>
	<br/>
	<form id="hikashop_coinpayments_form" name="hikashop_coinpayments_form"
		  action="<?php echo CoinpaymentsApi::CHECKOUT_URL; ?>/<?php echo CoinpaymentsApi::API_CHECKOUT_ACTION; ?>/"
		  method="get">
		<div id="hikashop_coinpayments_end_image" class="hikashop_coinpayments_end_image">
			<input id="hikashop_coinpayments_button" class="btn btn-primary" type="submit"
				   value="<?php echo JText::_('PAY_NOW'); ?>" name="" alt="<?php echo JText::_('PAY_NOW'); ?>"/>
		</div>
		<?php
		foreach ($this->vars as $name => $value) {
			echo '<input type="hidden" name="' . $name . '" value="' . htmlspecialchars((string)$value) . '" />';
		}
		$doc = JFactory::getDocument();
		$doc->addScriptDeclaration("window.addEvent('domready', function() {document.getElementById('hikashop_coinpayments_form').submit();});");
		JRequest::setVar('noform', 1);
		?>
	</form>
</div>
