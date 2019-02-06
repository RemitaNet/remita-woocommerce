jQuery( function( $ ) {

    var remita_submit = false;
            
        jQuery('#remita-payment-button').click( function() {            
            return remitaFormHandler();            
        });
        
         jQuery('#remita_form form#order_review').submit( function() {
             
            return remitaFormHandler();
        });
        
        function remitaFormHandler(){
            
            if (remita_submit) {
            remita_submit = false;
            return true;
        }
        
            var $form      = $( 'form#payment-form, form#order_review' ),
            transactionId  = $form.find( 'input.transactionId' );
            transactionId.val( '' );
            
           var remita_callback = function( response ) {
            $form.append( '<input type="hidden" class="transactionId" name="transactionId" value="' + response.transactionId + '"/>' );
            $('#remita_form a').hide();
            remita_submit = true;
            $form.submit();           

        };
            
            var key = wc_remita_params.key
            var amount = wc_remita_params.amount
            var order_id = wc_remita_params.order_id
            var email = wc_remita_params.email
            var billing_phone = wc_remita_params.billing_phone
            var first_name = wc_remita_params.first_name
            var last_name = wc_remita_params.last_name
            var uniqueOrderId = wc_remita_params.uniqueOrderId
            
            
            var paymentEngine = RmPaymentEngine.init({
            key: key,
            customerId: order_id,
            firstName: first_name,
            lastName: last_name,
            narration: "bill pay",
            transactionId: uniqueOrderId,
            email: email,
            amount: amount,
            onSuccess: remita_callback,
            onError: function (response) {
                
                console.log('callback Error Response', response);
            },
            onClose: function () {
                console.log("closed");
            }
        });        
    
        paymentEngine.showPaymentWidget();        
                return false;
            
        }
        
            

} );
    