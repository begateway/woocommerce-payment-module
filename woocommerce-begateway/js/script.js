function woocommerce_start_begateway_payment() {
  var params = {
    checkout_url: begateway_wc_checkout_vars.checkout_url,
    token: begateway_wc_checkout_vars.token,
    closeWidget: function(status) {
      if (status == null) {
        window.location.replace(begateway_wc_checkout_vars.cancel_url);
      }
    }
  };

  new BeGateway(params).createWidget();
};

window.addEventListener('load',function(event){
  woocommerce_start_begateway_payment();
},false);
