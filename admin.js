jQuery( function( $ ){

    function update_stock_widget(){
        var data = { action: 'msp_admin_sync_vendor', vendor: '', url: '' };
        let form = $('#msp_add_update_stock_form');
        let select = form.find( 'select[name="vendor"]' );
        let url = form.find( 'input[name="url"]' );

        
        form.on( 'change', select, function(){
            $('.feedback').html("");
            
            if( $(select).val() == 'portwest' ){
                data.url = 'http://www.portwest.us/downloads/sohUS.csv';
                $(url).val(data.url);
            } else {
                $('.feedback').html("Helly Hansen requires a url. <br>Please go to <a href='https://app.ivendix.com/'>iVendix</a> and enter the url emailed to you; above. Thanks!");
            }
            
        }).on( 'click', '#submit_update_vendor', function( e ){
            $('.feedback').html( 'Request Sent!, Thanks.<br>' );
            data.vendor = $(select).val();
            data.url = $(url).val();
            $.post( ajaxurl, data, function( response ){
                $('.feedback').html( response );
            });
        });
    }

    update_stock_widget();
});

