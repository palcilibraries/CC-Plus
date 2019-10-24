$(document).ready(function () {
    var CSRF_TOKEN = $('meta[name="csrf-token"]').attr('content');
    $('#SaveSushi').hide();   // hide Button on page load
    $('#notice').hide();      // hide notice on page load

  // Provider change (re)builds sushi settings
    $("#Prov").change(function () {
 // change function of listbox
        $('#notice').hide();      // hide notice when Prov changes
        if ( $('#Prov').val() == 0 ) {
            $("[id^=Sushi_]").val("");
            $('#SaveSushi').hide();   // hide Button if Prov is reset
        } else {
            $('#SaveSushi').show();   // display Button when Prov is set
            $.get(
                "/sushisettings-refresh",
                {_token:CSRF_TOKEN, prov_id:$('#Prov').val(), inst_id:$('#INST').val()},
                function (return_data,status) {
                    if ( "count" in return_data) {
                      // alert('return_data:'+return_data['count']);
                        $("#notice").html('No settings found - creating new entry.');
                        $("#notice").show();
                    } else {
                        $("#Sushi_ReqID").val(return_data.settings.requestor_id);
                        $("#Sushi_CustID").val(return_data.settings.customer_id);
                        $("#Sushi_APIkey").val(return_data.settings.API_key);
                    }
                }
            );
        }
    });

  // When Save button is clicked
    $('#SaveSushi').click(function () {
        $('#notice').hide();   // hide notice div when button is clicked
        var form_data = $(this).closest('form').serialize();
        form_data['ajax'] = 1;
        $.ajax({
            url: "/sushisettings-update",
            type: 'POST',
            data: form_data,
            dataType: 'json'
        });
    });
});
