<?php
require_once 'config/config.php';

// Kullanıcı zaten giriş yapmışsa ana sayfaya yönlendir
if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

$error = '';

// Form gönderildiğinde
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = isset($_POST['email']) ? clean_input($_POST['email']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $remember = isset($_POST['remember']) ? true : false;
    
    // E-posta kontrolü
    if (empty($email)) {
        $error = 'E-posta adresi zorunludur.';
    } else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Geçerli bir e-posta adresi giriniz.';
    }
    
    // Şifre kontrolü
    else if (empty($password)) {
        $error = 'Şifre zorunludur.';
    } 
    
    // Veritabanında kullanıcıyı kontrol et
    else {
        try {
            $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            // Kullanıcı bulundu ve şifre doğru
            if ($user && password_verify($password, $user['password'])) {
                // Oturum oluştur
                $_SESSION['user_id'] = $user['id'];
                
                // Admin kontrolü
                if ($user['is_admin']) {
                    $_SESSION['is_admin'] = true;
                }
                
                // Beni hatırla
                if ($remember) {
                    $token = bin2hex(random_bytes(32));
                    $expires = time() + (30 * 24 * 60 * 60); // 30 gün
                    
                    // Token kaydı
                    $stmt = $pdo->prepare('INSERT INTO remember_tokens (user_id, token, expires_at) VALUES (?, ?, ?)');
                    $stmt->execute([$user['id'], $token, date('Y-m-d H:i:s', $expires)]);
                    
                    // Cookie
                    setcookie('remember_token', $token, $expires, '/', '', false, true);
                }
                
                // Yönlendirme
                if (isset($_SESSION['redirect_after_login'])) {
                    $redirect = $_SESSION['redirect_after_login'];
                    unset($_SESSION['redirect_after_login']);
                    header('Location: ' . $redirect);
                } else {
                    header('Location: index.php');
                }
                exit;
            } else {
                $error = 'E-posta adresi veya şifre hatalı.';
            }
        } catch (PDOException $e) {
            $error = 'Bir hata oluştu: ' . $e->getMessage();
        }
    }
}

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giriş Yap - Yemeksepeti</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/main.css">
    <style>
        .login-container {
            max-width: 450px;
            margin: 0 auto;
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        .card-header {
            background-color: var(--ys-red);
            color: white;
            border-radius: 10px 10px 0 0 !important;
            padding: 20px;
        }
        .social-login {
            border-top: 1px solid #eee;
            padding-top: 20px;
            margin-top: 20px;
        }
        .btn-social {
            width: 100%;
            padding: 10px;
            margin-bottom: 10px;
            border-radius: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .btn-social i {
            margin-right: 10px;
        }
        .btn-google {
            background-color: #DB4437;
            color: white;
        }
        .btn-facebook {
            background-color: #4267B2;
            color: white;
        }
        .btn-apple {
            background-color: #000;
            color: white;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="border-bottom shadow-sm">
        <div class="container py-3">
            <div class="d-flex justify-content-between align-items-center">
                <!-- Logo -->
                <a href="index.php" class="navbar-brand"><img class="logocss" src="images/logo.png" alt="Yemeksepeti"></a>
                
                <!-- Register Button -->
                <a href="register.php" class="btn btn-red rounded-pill px-4 py-2 btn-hover">Kayıt Ol</a>
            </div>
        </div>
    </header>

    <div class="container my-5">
        <div class="login-container">
            <div class="card">
                <div class="card-header text-center">
                    <h3 class="mb-0">Giriş Yap</h3>
                </div>
                <div class="card-body p-4">
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger">
                            <?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="post" action="">
                        <div class="mb-3">
                            <label for="email" class="form-label">E-posta Adresiniz</label>
                            <input type="email" class="form-control" id="email" name="email" placeholder="ornek@mail.com" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Şifreniz</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="password" name="password" placeholder="Şifreniz" required>
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="remember" name="remember">
                                <label class="form-check-label" for="remember">
                                    Beni Hatırla
                                </label>
                            </div>
                            <a href="forgot_password.php" class="text-decoration-none">Şifremi Unuttum</a>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-red py-2">Giriş Yap</button>
                        </div>
                    </form>
                    
                    <div class="social-login">
                        <p class="text-center mb-3">veya şununla giriş yap:</p>
                        
                        <button class="btn btn-social btn-google">
                            <i class="fab fa-google"></i> Google ile Giriş Yap
                        </button>
                        
                        <button class="btn btn-social btn-facebook">
                            <i class="fab fa-facebook-f"></i> Facebook ile Giriş Yap
                        </button>
                        
                        <button class="btn btn-social btn-apple">
                            <i class="fab fa-apple"></i> Apple ile Giriş Yap
                        </button>
                    </div>
                    
                    <div class="text-center mt-3">
                        <p class="mb-0">Hesabınız yok mu? <a href="register.php" class="text-decoration-none">Kayıt Ol</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <footer class="bg-light mt-5 py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <h5>Yemeksepeti</h5>
                    <p>Türkiye'nin online yemek ve market siparişi platformu</p>
                </div>
                <div class="col-md-4">
                    <h5>Hızlı Erişim</h5>
                    <ul class="list-unstyled">
                        <li><a href="index.php">Ana Sayfa</a></li>
                        <li><a href="#">Hakkımızda</a></li>
                        <li><a href="#">İletişim</a></li>
                        <li><a href="#">Yardım</a></li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h5>İletişim</h5>
                    <p>
                        <i class="fas fa-envelope me-2"></i> info@yemeksepeti.com<br>
                        <i class="fas fa-phone me-2"></i> 0850 123 4567
                    </p>
                    <div class="mt-3">
                        <a href="#" class="text-dark me-2"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="text-dark me-2"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="text-dark me-2"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
            </div>
            <hr>
            <div class="text-center">
                <p class="mb-0">&copy; <?php echo date('Y'); ?> Yemeksepeti. Tüm hakları saklıdır.</p>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Şifre göster/gizle
        const togglePassword = document.getElementById('togglePassword');
        const password = document.getElementById('password');
        
        togglePassword.addEventListener('click', function() {
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            
            // İkon değişimi
            this.querySelector('i').classList.toggle('fa-eye');
            this.querySelector('i').classList.toggle('fa-eye-slash');
        });
    </script>
</body>
</html>