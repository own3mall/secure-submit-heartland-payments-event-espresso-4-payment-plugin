/*global $, jQuery*/
var tokenGenerated = false;
var hps = (function ($) {
    "use strict";

    var HPS;

    HPS = {

        Tag: "SecureSubmit",

        Urls: {
            CERT: "https://cert.api2.heartlandportico.com/Hps.Exchange.PosGateway.Hpf.v1/api/token",
            PROD: "https://api2.heartlandportico.com/SecureSubmit.v1/api/token"
        },
        
        getToken: function(theForm){
			var data, i, cardType;

            // get data from storage
            data = theForm.data(HPS.Tag);

			// validate form - jQuery validate plugin
			if (typeof theForm.validate === 'function') {
				theForm.validate();
				// validation failed
				if (!theForm.valid()) {
					return;
				}
			}

            var d = new Date();
            if (parseInt($.trim($("exp_year"))) < d.getFullYear()) {
				HPS.error("The expiration year is in the past.");
				return;
            }

			HPS.tokenize({
				data: {
					public_key: data.public_key,
					number: $.trim($("#card_number").val()),
					cvc: $.trim($("#card_cvc").val()),
					exp_month: $.trim($("#exp_month").val()),
					exp_year: $.trim($("#exp_year").val())
				},
				success: function (response) {
					// create field and append to form
					$("input#securesubmit_token", theForm).val(response.token_value);
					tokenGenerated = true;
					$("#spco-go-to-step-finalize_registration-submit").trigger('click');
					
                },
				error: function (response) {
					if (typeof data.error === 'function') {
						data.error(response);
					}
				}
			});
		},

        tokenize: function (options) {
            var gateway_url, params, env;

            // add additional service parameters
            params = $.param({
                "api_key": options.data.public_key,
                "object": "token",
                "token_type": "supt",
                "_method": "post",
                "card[number]": $.trim(options.data.number),
                "card[cvc]": $.trim(options.data.cvc),
                "card[exp_month]": $.trim(options.data.exp_month),
                "card[exp_year]": $.trim(options.data.exp_year)
            });

            env = options.data.public_key.split("_")[1];

            if (env === "cert") {
                gateway_url = HPS.Urls.CERT;
            } else {
                gateway_url = HPS.Urls.PROD;
            }


            var d = new Date();
            if (parseInt($.trim(options.data.exp_year)) < d.getFullYear()) {
                options.error("The expiration year is in the past.");
                return;
            }

            // request token
            $.ajax({
                cache: false,
                url: gateway_url,
                data: params,
                dataType: "jsonp",
                async: false,
                success: function (response) {

                    // Request failed, handle error
                    if (typeof response.error === 'object') {
                        // call error handler if provided and valid
                        if (typeof options.error === 'function') {
                            options.error(response.error);
                        }
                        else {
                            // handle exception
                            HPS.error(response.error.message);
                        }
                    } else if (typeof options.success === 'function') {
                        options.success(response);
                    }
                }
            });
        },

        tokenize_swipe: function (options) {
            var gateway_url, params, env;

            params = $.param({
                "api_key": options.data.public_key,
                "object": "token",
                "token_type": "supt",
                "_method": "post",
                "card[track_method]": "swipe",
                "card[track]": $.trim(options.data.track)
            });

            env = options.data.public_key.split("_")[1];

            if (env === "cert") {
                gateway_url = HPS.Urls.CERT;
            } else {
                gateway_url = HPS.Urls.PROD;
            }

            // request token
            $.ajax({
                cache: false,
                url: gateway_url,
                data: params,
                dataType: "jsonp",
                success: function (response) {

                    // Request failed, handle error
                    if (typeof response.error === 'object') {
                        // call error handler if provided and valid
                        if (typeof options.error === 'function') {
                            options.error(response.error);
                        } else {
                            // handle exception
                            HPS.error(response.error.message);
                        }
                    } else if (typeof options.success === 'function') {
                        options.success(response);
                    }
                }
            });
        },

        trim: function (string) {

            if (string !== undefined && typeof string === "string" ) {

                string = string.toString().replace(/^\s\s*/, '').replace(/\s\s*$/, '');
            }

            return string;
        },

        empty: function (val) {
            return val === undefined || val.length === 0;
        },

        error: function (message) {
            $.error([HPS.Tag, ": ", message].join(""));
        },

        configureElement: function (options) {

            // set plugin data
            $(this).data(HPS.Tag, {
                public_key: options.public_key,
                success: options.success,
                error: options.error
            });
            
            var form = $(this);
            
            
            setTimeout(function(){ 
				$("#spco-go-to-step-finalize_registration-submit").click(function(e){
					if(!$("#card_number", form).val() && $("#securesubmit_giftcardnumber", form).val()){
						$("#card_number", form).val('4111111111111111');
					}
					
					if(!tokenGenerated){
						e.preventDefault();
						e.stopPropagation();
						HPS.getToken(form);
					}
					
				});
				
				$("#card_number, #exp_month, #exp_year, #card_cvc", form).on('change keyup', function(e){
					tokenGenerated = false;
				});
			}, 2000);
            
        }
    };

    $.fn.SecureSubmit = function (options) {

        return this.each(function () {
            if (!$(this).is("form") ||
                typeof options !== 'object'
                || $.hasData($(this))
                ) {

                return;
            }

            HPS.configureElement.apply(this, [options]);
        });
    };

    return HPS;
}(jQuery));
