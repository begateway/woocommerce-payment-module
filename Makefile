all:
	if [[ -e woocommerce-begateway.zip ]]; then rm wc-begateway-payment.zip; fi
	zip -r wc-begateway-payment.zip wc-begateway-payment -x "*/test/*" -x "*/.git/*" -x "*/examples/*" -x "*.DS_Store*" -x "*.git*" -x "*.travis.yml*"
