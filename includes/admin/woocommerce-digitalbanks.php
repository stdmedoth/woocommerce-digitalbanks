<?php

function wc_digitalbanks_boleto_add_to_gateways( $gateways ) {

  $gateways[] = 'WC_DigitalBanks_Boleto_Gateway';

  return $gateways;
}

?>
