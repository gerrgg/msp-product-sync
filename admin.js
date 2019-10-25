jQuery( function( $ ){

    function update_stock_widget(){
        var data = { action: 'msp_admin_sync_vendor', vendor: '', url: '' };
        let form = $('#msp_add_update_stock_form');
        let select = form.find( 'select[name="vendor"]' );
        let url = form.find( 'input[name="url"]' );
        let submit = $('#submit_update_vendor');

        
        form.on( 'change', select, function(){
            $('.feedback').html("");
            let vendor = $(select).val();
            
            if( vendor == 'portwest' ){
                data.url = 'http://www.portwest.us/downloads/sohUS.csv';
                $(url).val(data.url);
            } else if( vendor == 'helly_hansen' ) {
                $('.feedback').html("Helly Hansen requires a url. <br>Please go to <a href='https://app.ivendix.com/'>iVendix</a> and enter the url emailed to you; above. Thanks!");
            }
            
        }).on( 'click', '#dry_run', function( e ){
            // THIS IS FOR RUNNING DRY RUNS IN ADMIN POST INSTEAD OF AJAX
            // let dry_run = $(e.target);
            // let button_type = ( dry_run.is(':checked') ) ? 'submit' : 'button';
            // submit.attr('type', button_type);
        })
        .on( 'click', 'button', function(){
            if( submit.attr('type') == 'button' ){
                $('.feedback').html( 'Request Sent!, Thanks.<br>' );
                data.vendor = $(select).val();
                data.url = $(url).val();
                $.post( ajaxurl, data, function( response ){
                    $('.feedback').html( response );
                });
            }
        });
    }

    update_stock_widget();
});

