$(document).ready(function () {
    setInterval(function () {
        $.ajax({
            url: '/adminzone/inc/ajax.php',
            type: 'POST',
            data: JSON.stringify({ action: 'getAllStatistic' }),
            contentType: 'application/json',
            success: function (data) {
                var response = JSON.parse(data);
                if(response.status === true) {
                    $('#count').text("(" + response.logs + ")");
                    $('#countsepet').text("(" + response.basket + ")");
                }
            },
        })
    }, 5000);
});
