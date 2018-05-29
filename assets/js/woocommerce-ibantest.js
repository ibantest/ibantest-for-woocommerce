/* global ibantest_params */
jQuery(function ($) {
    // Check if we have params.
    if (typeof ibantest_params === 'undefined') {
        return false;
    }

    var wcibantest = {

        documentReady: function () {
            $(document).on('change keyup', '.transform-uppercase', function () {
                var value = $(this).val().replace(/ /g, '');
                value = value.replace(/[^\w\d]/g, "");
                $(this).val(value.toUpperCase());
            });

            $(document).on('focusout', '#ibantest-account-iban', function () {
                var value = $(this).val().replace(/ /g, '');
                if (value != '') {
                    wcibantest.validateIban();
                }
                return false;
            });

            $(document).on('click', '#show-sepa-mandate-trigger', function (e) {
                e.preventDefault();
                $('a#show-sepa-mandate-pretty').prettyPhoto({
                    social_tools: false,
                    theme: 'pp_woocommerce',
                    horizontal_padding: 20,
                    opacity: 0.8,
                    deeplinking: false
                });
                wcibantest.showSepaMandate();
                return false;
            });
        },

        validateIban: function () {
            $.ajax({
                type: 'POST',
                url: ibantest_params.validate_iban_url,
                data: {
                    iban: $('#ibantest-account-iban').val(),
                    nonce: ibantest_params.validate_iban_nonce
                },
                dataType: "json",
                cache: false,
                context: this,
                success: function (data) {
                    if (typeof data != 'undefined') {
                        if (
                            true == data.valid ||
                            true == data.error
                        ) {
                            $('.form-row.account-bic').show();
                            $('#ibantest-account-iban-error').text('').hide();
                            if (data.bic) {
                                $('#ibantest-account-bic').val(data.bic).prop('readonly', true);
                            } else {
                                $('#ibantest-account-bic').val('').prop('readonly', false);
                                $('#ibantest-account-bank').text('').hide();
                            }
                            if (data.bankName) {
                                $('#ibantest-account-bank').text(data.bankName).show();
                            }
                        } else if (false == data.valid) {
                            $('#ibantest-account-bank').text();
                            $('#ibantest-account-bic').val('').prop('readonly', false);
                            $('.form-row.account-bic').hide();
                            if (data.message) {
                                $('#ibantest-account-iban-error').text(data.message).show();
                            }
                        }
                    }
                },
                error: function (data) {
                },
                complete: function (data) {
                }
            });
        },

        showSepaMandate: function () {
            $.ajax({
                type: 'POST',
                url: ibantest_params.show_sepa_mandate_url,
                data: {
                    checkout: $('form.checkout').serialize(),
                    nonce: ibantest_params.show_sepa_mandate_nonce
                },
                dataType: "json",
                cache: false,
                context: this,
                success: function (data) {
                    $('#show-sepa-mandate-pretty-content').html(data);
                    $('#show-sepa-mandate-pretty').trigger( 'click' );
                },
                error: function (data) {
                },
                complete: function (data) {
                }
            });
        },

        init: function () {
            $(document).ready(wcibantest.documentReady);
        }
    };

    wcibantest.init();
});
