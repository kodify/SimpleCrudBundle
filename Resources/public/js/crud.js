$(document).ready(function () {
    "use strict";
    $('#crud_search_button').on("click", function () {
        $("#crud_form_current_page").val(0);
        $("#crud_filter_form").submit();
    });

    $('#crud_reset_button').on("click", function () {
        window.location = resetFormUrl;
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
        if (e.which === 13) { //enter key
            $("#crud_form_current_page").val(0);
            $("#crud_filter_form").submit();
        }
    });

    $('.sort_index').on("click", function () {
        var key = $(this).data('sort-field');
        if ($("#sort_field").val() === key) {
            var curr_dir = $("#sort_dir").val();
            if (curr_dir === 'ASC') {
                $("#sort_dir").val('DESC');
            } else {
                $("#sort_dir").val('ASC');
            }
        } else {
            $("#sort_dir").val($("#sort_" + key + "_dir").val());
        }

        $("#sort_field").val(key);
        $("#crud_filter_form").submit();
    });

    $("body").delegate("a.ajax-action-button", "click", function (event) {
        event.preventDefault();
        var img , currentLink, rowId;
        img = $('<img>');
        img.attr('src', ajaxLoadingImgUrl);

        $('<div/>')
            .append(img)
            .appendTo($(this).parent());

        $(this).hide();
        currentLink = $(this);

        $.ajax({
            url: $(this).attr("href"),
            type: 'GET',
            cache: false,
            success: function (response) {
                img.hide();
                currentLink.show();
                if (currentLink.data('success-callback-function')) {
                    rowId = currentLink.data('row-id');
                    window[currentLink.data('success-callback-function')](rowId, response, currentLink);
                }
            },
            error: function (response) {
                img.hide();
                currentLink.show();
                if (currentLink.data('error-callback-function')) {
                    rowId = currentLink.data('row-id');
                    window[currentLink.data('error-callback-function')](rowId, response, currentLink);
                }

            }
        });
    });


    $('.dates').datepicker({ format: 'yyyy-mm-dd' })
        .on('changeDate', function (ev) {
            if (ev.viewMode === 'days') {
                $(this).datepicker('hide');
            }
        });

    $('select , input').on('focus', function () {
        $('.dates').each(function () {
            $(this).datepicker('hide');
        });
        if ($(this).hasClass('dates')) {
            $(this).datepicker('show');
        }
    });

});

    function displayErrorMessage(rowId, response, linkObject) {
        "use strict";
        $('<div style="margin-top:5px" class="alert alert-error" />')
            .html('Error: status: ' + response.status + '<br />statusText: ' + response.statusText)
            .appendTo(linkObject.parent());
    }

    function hideRow(rowId, response, linkObject) {
        "use strict";
        $("#row" + rowId).hide('slow');
    }

    function hideRowAndDisplayMessage(rowId, response, linkObject) {
        "use strict";
        hideRow(rowId, response, linkObject);

        $('<div style="margin-top:5px" class="alert alert-info" />')
            .html('Info: ' + response.message)
            .delay(3000)
            .fadeOut(300)
            .prependTo(".container");

    }