$(document).ready(function () {
    var CSRF_TOKEN = $('meta[name="csrf-token"]').attr('content');

  // Reset newrow form fields to defaults
    function reset_newrow()
    {
        $("#A_report").val(0);
        $("#A_field").empty();
        $("#A_field").val('--');
        $("#A_inst").val(1);
        $("#A_variance").val(null);
        $("#A_timespan").val(null);
    }

  // Fires when delete button is clicked
    $('[id^=destroy_]').click(function () {
       // console.log(e);
        var name = $(this).attr("id");
        var _id = name.split("_");
        $.ajax({
            type: 'POST',
            url:'/alertsettings/' + _id[1],
            data:{ _token:CSRF_TOKEN, _method:'DELETE' }
        });
    });

  // Report onchange action - (re)builds fields list
    $("#A_report").change(function () {
 // change function of listbox
        $("#A_field").empty(); // Clear the fields box
        if ( $('#A_report').val() != 0 ) {
            $.post(
                "/alertsettings-fields-refresh",
                {_token:CSRF_TOKEN, report_id:$('#A_report').val()},
                function (return_data,status) {
                    $("#A_field").append("<option value='--'>Choose a measurement</option>");
                    $.each(return_data.fields, function (key,value) {
                        $("#A_field").append("<option value=" + value.id + ">" + value.legend + "</option>");
                    });
                },
                "json"
            );
        }
    });

  // Reset whole Form
    $('#reset_form').click(function () {
        location.reload(true);
    });

  // Create-new button exposes hidden div newrow,
  // cancelling a newrow reverses the hide/show
    $('#newsetting').click(function () {
        $("#addrow_table").show();
        $("#buttons_row").hide();
        $('#create_save').hide();       // hide "Add" button until a field is set
    });
    $('#create_cancel').click(function () {
        $("#addrow_table").hide();
        $("#buttons_row").show();
        reset_newrow();
    });

  //  Metric onchange action - enable save button
    $("#A_field").change(function () {
 // change function of listbox
        if ( $('#A_field').val() == "--" ) {
            $('#create_save').hide();       // hide "Add" button until a field is set
        } else {
            $('#create_save').show();       // hide "Add" button until a field is set
        }
    });

  // When newrow Save button is clicked
    $('#create_save').click(function () {
       // Create and add a new row to data_table from the fields in the newrow div
        var row = '<tr>';
        row += '<td><input checked="checked" name="newcb[]" type="checkbox"></td>';
        row += '<td>' + $('#A_report :selected').text() + ' :: ' + $('#A_field :selected').text();
        row += '<input type="hidden" name="newmet[]" value="' + $('#A_field :selected').val() + '"></td>';
        row += '<td>' + $('#A_inst :selected').text();
        row += '<input type="hidden" name="newinst[]" value="' + $('#A_inst :selected').val() + '"></td>';
        row += '<td><input min="0" max="1000" class="form-control" name="newvar[]" type="number"';
        row += 'value="' + $('#A_variance').val() + '"> %</td>';
        row += '<td><input min="0" max="48" class="form-control" name="newts[]" type="number"';
        row += 'value="' + $('#A_timespan').val() + '"> months</td>';
        row += '<td><strong>New</strong></td></tr>';
       // Reset buttons and the newrow div to initial state
        $("#settingrows").append(row);
        $("#buttons_row").show();
        $("#addrow_table").hide();
        reset_newrow();
    });
});
