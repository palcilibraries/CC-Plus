$(document).ready(function () {
    var CSRF_TOKEN = $('meta[name="csrf-token"]').attr('content');
  // $('table').tablesorter();

  // Handy set-all button actions
  //
    $('#SilenceALL').click(function () {
        $("[id^=stat_]").each(function () {
            this.value = "Silent" });
    });
    $('#ActiveALL').click(function () {
        $("[id^=stat_]").each(function () {
            this.value = "Active" });
    });
    $('#DeleteALL').click(function () {
        $("[id^=stat_]").each(function () {
            this.value = "Delete" });
    });

  // Fires when alert-status-dropdowns are changed
    $('[name^=stat_]').on('change', function () {
       // console.log(e);
        var name = $(this).attr("name");
        var value = $(this).val();
       // alert('hello: '+name+' , status: '+value);
        $.ajax({
            type: 'POST',
            url:'/update-alert-status',
            data:{ _token:CSRF_TOKEN, name:name, status:value },
            dataType: 'JSON',
            success: function (data) {
                  $(".writeinfo").append(data.msg);
            }
        });
    });

  // Firest when filter-dropdowns are changed
    $("[name=filter_stat],[name=filter_prov]").change(function () {
        var stat_value = $('#filter_stat').val();
        var prov_value = $('#filter_prov').val();
        var $table = $('#data_table');
        var enum_stat = $('#enum_stat');
        var status_vals = enum_stat.val().split(":");
        $("#alertrows").empty();
      // $.tablesorter.clearTableBody( $table );
      // var form_data = $(this).closest('form').serialize();
      // form_data['ajax'] = 1;
        $.ajax({
            url: "/alert-dash-refresh",
            type: 'POST',
            data:{ _token:CSRF_TOKEN, filt_stat:stat_value, filt_prov:prov_value },
            dataType: 'JSON',
            success: function (return_data) {
                var adm = return_data.admin;
                var mgr = return_data.manager;
                var alertrows = "<tbody id='alertrows'>";
                $.each(return_data.records, function (key,value) {
                    //
                    // Build new table rows from function output
                    //
                    var row = "<tr>";
                    //
                    // Admins and Managers see a dropdown, otherwise just print the text
                    //
                    row += "<td>";
                    if ( adm || mgr ) {
                        row += "<select name='stat_" + value.id + "' class='form-control'>";
                        for (var i = 0, sm = status_vals.length; sm > i; i++) {
                            if ( status_vals[i] == "ALL") {
                                continue; }
                            row += "<option value='" + status_vals[i] + "'";
                            if ( status_vals[i] == value.status ) {
                                row += " selected "; }
                            row += ">" + status_vals[i] + "</option>";
                        }
                        row += "</select>";
                    } else {
                        row += value.status + "</td>";
                    }
              // Yearmon
              //
                    row += "<td align='center'>";
                    if ( value.yearmon != "" ) {
                        row += value.yearmon + "</td>";
                    } else {
                        row += " -- </td>";
                    }
           // Condition and Report name
           //
                    row += "<td align='center'>";
                    if ( value.detail != "" ) {
                        row += value.detail + "</td>";
                    } else {
                        row += " -- </td>";
                    }
                    row += "<td align='center'>" + value.report_name + "</td>";
           //
           // Institution column included as as link for admins, otherwise skip
           //
                    if ( adm ) {
                        row += "<td align='center'>";
                        row += "<a href='/institutions/" + value.inst_id + "'>";
                        row += value.inst_name + "</a></td>";
                    }
           //
           // Provider as link for admins, otherwise just the name
           //
                    row += "<td align='center'>";
                    if ( value.prov_id == 0 ) {
                        row += "--</td>";
                    } else {
                        if ( adm ) {
                            row += "<a href='/providers/" + value.prov_id + "'>";
                            row += value.prov_name + "</a></td>";
                        } else {
                            row += value.prov_name + "</td>";
                        }
                    }
           //
           // Timestamp, and modified-by
           //
                    row += "<td align='center'>" + value.updated_at + "</td>";
                    row += "<td align='center'>";
                    if ( value.modified_by == null ) {
                        row += "--</td>";
                    } else {
                        if ( value.modified_by == 0 ) {
                            row += "CC-Plus System";
                        } else {
                            row += value.user_name + "</td>";
                        }
                    }
                    alertrows += row;
                })   // loop through each record
                $("#alertrows").replaceWith(alertrows);
            }    // success function
        });  // ajax call
    });  // filter-change function
});
