<?php
require_once 'config/config.php';

// Kullanıcı zaten giriş yapmışsa ana sayfaya yönlendir
if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = false;

// Form gönderildiğinde
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = isset($_POST['first_name']) ? clean_input($_POST['first_name']) : '';
    $last_name = isset($_POST['last_name']) ? clean_input($_POST['last_name']) : '';
    $email = isset($_POST['email']) ? clean_input($_POST['email']) : '';
    $phone = isset($_POST['phone']) ? clean_input($_POST['phone']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $password_confirm = isset($_POST['password_confirm']) ? $_POST['password_confirm'] : '';
    
    // Temel doğrulama
    if (empty($first_name) || empty($last_name) || empty($email) || empty($phone) || empty($password) || empty($password_confirm)) {
        $error = 'Tüm alanları doldurunuz.';
    } 
    // E-posta formatı kontrolü
    else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Geçerli bir e-posta adresi giriniz.';
    }
    // Şifre eşleşme kontrolü
    else if ($password !== $password_confirm) {
        $error = 'Şifreler eşleşmiyor.';
    }
    // Şifre uzunluğu kontrolü
    else if (strlen($password) < 6) {
        $error = 'Şifre en az 6 karakter olmalıdır.';
    }
    // Kayıt işlemi
    else {
        try {
            // E-posta adresi kullanımda mı?
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
            $stmt->execute([$email]);
            
            if ($stmt->fetchColumn() > 0) {
                $error = 'Bu e-posta adresi zaten kullanımda.';
            } else {
                // Şifre hash'leme
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Kullanıcı kayıt
                $stmt = $pdo->prepare('INSERT INTO users (first_name, last_name, email, phone, password) VALUES (?, ?, ?, ?, ?)');
                $result = $stmt->execute([$first_name, $last_name, $email, $phone, $hashed_password]);
                
                if ($result) {
                    $success = true;
                    
                    // Kullanıcıyı otomatik giriş yap
                    $user_id = $pdo->lastInsertId();
                    $_SESSION['user_id'] = $user_id;
                    
                    // 3 saniye bekleyip ana sayfaya yönlendir
                    header('Refresh: 3; URL=index.php');
                } else {
                    $error = 'Kayıt işlemi sırasında bir hata oluştu.';
                }
            }
        } catch (PDOException $e) {
            $error = 'Veritabanı hatası: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kayıt Ol - Yemeksepeti</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/main.css">
    <style>
        .register-container {
            max-width: 550px;
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
        .social-register {
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
                
                <!-- Login Button -->
                <a href="login.php" class="btn btn-outline-light rounded-pill px-4 py-2 btn-hover">Giriş Yap</a>
            </div>
        </div>
    </header>

    <div class="container my-5">
        <div class="register-container">
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <h4>Kayıt İşlemi Başarılı!</h4>
                    <p>Hesabınız başarıyla oluşturuldu ve otomatik olarak giriş yapıldı.</p>
                    <p>Ana sayfaya yönlendiriliyorsunuz...</p>
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="card-header text-center">
                        <h3 class="mb-0">Kayıt Ol</h3>
                    </div>
                    <div class="card-body p-4">
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger">
                                <?php echo $error; ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="post" action="">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="first_name" class="form-label">Adınız</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" placeholder="Adınız" value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>" required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="last_name" class="form-label">Soyadınız</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" placeholder="Soyadınız" value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">E-posta Adresiniz</label>
                                <input type="email" class="form-control" id="email" name="email" placeholder="ornek@mail.com" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="phone" class="form-label">Telefon Numaranız</label>
                                <input type="number" class="form-control" id="phone" name="phone" placeholder="05XX XXX XX XX" value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="password" class="form-label">Şifreniz</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="password" name="password" placeholder="En az 6 karakter" required>
                                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="form-text">Şifreniz en az 6 karakter olmalıdır.</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="password_confirm" class="form-label">Şifrenizi Tekrar Girin</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="password_confirm" name="password_confirm" placeholder="Şifrenizi tekrar girin" required>
                                    <button class="btn btn-outline-secondary" type="button" id="togglePasswordConfirm">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="terms" name="terms" required>
                                <label class="form-check-label" for="terms">
                                    <small>Kişisel verilerimin işlenmesine ilişkin <a href="#">Aydınlatma Metni</a>'ni okudum, anladım ve <a href="#">Mesafeli Satış Sözleşmesi</a>'ni kabul ediyorum.</small>
                                </label>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-red py-2">Kayıt Ol</button>
                            </div>
                        </form>
                        
                        <div class="social-register">
                            <p class="text-center mb-3">veya şununla kayıt ol:</p>
                            
                            <button class="btn btn-social btn-google">
                                <i class="fab fa-google"></i> Google ile Kayıt Ol
                            </button>
                            
                            <button class="btn btn-social btn-facebook">
                                <i class="fab fa-facebook-f"></i> Facebook ile Kayıt Ol
                            </button>
                            
                            <button class="btn btn-social btn-apple">
                                <i class="fab fa-apple"></i> Apple ile Kayıt Ol
                            </button>
                        </div>
                        
                        <div class="text-center mt-3">
                            <p class="mb-0">Zaten hesabınız var mı? <a href="login.php" class="text-decoration-none">Giriş Yap</a></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
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
        
        // Şifre onay göster/gizle
        const togglePasswordConfirm = document.getElementById('togglePasswordConfirm');
        const passwordConfirm = document.getElementById('password_confirm');
        
        togglePasswordConfirm.addEventListener('click', function() {
            const type = passwordConfirm.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordConfirm.setAttribute('type', type);
            
            // İkon değişimi
            this.querySelector('i').classList.toggle('fa-eye');
            this.querySelector('i').classList.toggle('fa-eye-slash');
        });
        

    </script>
</body>
</html>