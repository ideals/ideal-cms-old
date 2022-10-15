$(document).ready(function () {
    $('#general_structure').change(function () {
        var templateID = $(this).val().toLowerCase();
        $('.general_template-controls input').each(function () {
            $(this).hide();
        });
        $('.general_template-controls a').each(function () {
            $(this).hide();
        });
        $('#general_template_' + templateID).siblings('.general_template_' + templateID).show();
    });
});
