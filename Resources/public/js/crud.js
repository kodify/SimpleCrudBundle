$(document).ready(function () {
    $('#crud_search_button').on("click", function () {
        $("#crud_form_current_page").val(0);
        $("#crud_filter_form").submit();
    });

    $('#crud_reset_button').on("click", function () {
        window.location = window.location.href;
    });

    $('#crud_page_size').on("change", function () {
        $("#crud_form_page_size").val($('#crud_page_size').val());
        $("#crud_form_current_page").val(0);
        $("#crud_filter_form").submit();
    });

    $('.pagination .page_link').on("click", function () {
        $("#crud_form_current_page").val($(this).data('page'));
        $("#crud_filter_form").submit();
    });

    $('.crud_form_field').on("keypress", function (e) {
        if(e.which == 13) { //enter key
            $("#crud_form_current_page").val(0);
            $("#crud_filter_form").submit();
        }
    });

    $('.sort_index').on("click", function () {
        var key = $(this).data('sort-field');
        if ($("#sort_field").val() == key) {
            curr_dir = $("#sort_dir").val();
            if (curr_dir == 'ASC')
                $("#sort_dir").val('DESC')
            else
                $("#sort_dir").val('ASC')
        } else {
            $("#sort_dir").val($("#sort_" + key + "_dir").val());
        }

        $("#sort_field").val(key);
        $("#crud_filter_form").submit();
    });

});