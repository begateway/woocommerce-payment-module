all:
	if [[ -e woocommerce-begateway.zip ]]; then rm woocommerce-begateway.zip; fi
	zip -r woocommerce-begateway.zip woocommerce-begateway -x "*/test/*" -x "*/.git/*" -x "*/examples/*" -x "*.DS_Store*"
