<?php

// Start the session
session_start();

// If the user is logged in
if (isset($_SESSION['user'])) {
    // Redirect to the admin panel
    header('Location: dashboard.php');
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<!-- Meta Pixel Code -->
<script>
!function(f,b,e,v,n,t,s)
{if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};
if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];
s.parentNode.insertBefore(t,s)}(window, document,'script',
'https://connect.facebook.net/en_US/fbevents.js');
fbq('init', '364533106078493');
fbq('track', 'PageView');
</script>
<noscript><img height="1" width="1" style="display:none"
src="https://www.facebook.com/tr?id=364533106078493&ev=PageView&noscript=1"
/></noscript>
<!-- End Meta Pixel Code -->
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>cat /etc/passwd</title>
    <!-- TailwindCSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- JQuery -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.3/jquery.min.js"></script>
    <!-- Toastr JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <!-- Toastr CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css"/>
    <!-- Custom JS -->
    <script src="src/js/login.js"></script>
    <!-- Favicon -->
    <link rel="icon" href="src/icon/favicon.ico" type="image/x-icon"/>
</head>
<body class="bg-gray-200"
      style="background-image: url('src/img/bg.webp'); background-size: cover; background-repeat: no-repeat; background-attachment: fixed;">
<div class="flex flex-col justify-center items-center min-h-screen">
    <div class="w-full max-w-md bg-gray-100 rounded-md shadow-lg">
        <div class="px-12 py-8">
            <form class="mb-0" id="login-form">
                <div class="mb-4">
                    <label
                            class="block text-gray-700 text-sm font-bold mb-2"
                            for="username"
                    >
                        Username
                    </label>
                    <input
                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                            id="username"
                            type="text"
                            placeholder="vertigo"
                    />
                </div>
                <div class="mb-6">
                    <label
                            class="block text-gray-700 text-sm font-bold mb-2"
                            for="password"
                    >
                        Password
                    </label>
                    <input
                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 mb-3 leading-tight focus:outline-none focus:shadow-outline"
                            id="password"
                            type="password"
                            placeholder="****"
                    />
                </div>
                <div class="flex justify-center">
                    <button
                            class="bg-gray-600 hover:bg-gray-800 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition duration-300 ease-in-out"
                            type="submit"
                    >
                        Login
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
</body>
</html>