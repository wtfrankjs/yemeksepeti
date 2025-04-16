$(document).ready(function () {
    setInterval(function () {
        $.ajax({
            url: '/adminzone/inc/ajax.php',
            type: 'POST',
            data: JSON.stringify({ action: 'getAllStatistic' }),
            contentType: 'application/json',
            success: function (_0x2b6703) {
                _0x2b6703 = JSON.parse(_0x2b6703)
                _0x2b6703.status === true &&
                ($('#countBaskets').text(_0x2b6703.basket),
                    $('#countLogs').text(_0x2b6703.logs))
            },
        })
    }, 1000)
})