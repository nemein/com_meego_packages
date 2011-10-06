/*
 * Initializes the rating radio buttons
 * to become a nice star rating widget
 */
$(document).ready(function() {
    var selector = 'input[name="rating"]';
    $(selector).addClass('star');
    var options = {
        style: 'big'
    };
    $(selector).rating(options);
});
