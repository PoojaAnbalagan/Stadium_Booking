
<?php
require_once 'config.php';

$error = '';
$success = '';



if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    file_put_contents('debug_log.txt', "POST Request:\n" . print_r($_POST, true) . "\n", FILE_APPEND);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'login') {
    $email = trim($_POST['email']); // Simple trim
    $password = $_POST['password'];
    
    // Use prepared statement
    $stmt = mysqli_prepare($conn, "SELECT id, full_name, password FROM users WHERE email = ?");
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($user = mysqli_fetch_assoc($result)) {
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['full_name'];
            
            $redirect = isset($_GET['redirect']) ? $_GET['redirect'] : 'index.php';
            redirect($redirect);
        } else {
            $error = 'Invalid email or password';
        }
    } else {
        $error = 'Invalid email or password';
    }
    mysqli_stmt_close($stmt);
}

// Handle Registration
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'register') {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    
    // Check if email already exists
    $check_stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ?");
    mysqli_stmt_bind_param($check_stmt, "s", $email);
    mysqli_stmt_execute($check_stmt);
    mysqli_stmt_store_result($check_stmt);
    
    if (mysqli_stmt_num_rows($check_stmt) > 0) {
        $error = 'Email already registered';
    } else {
        // Insert new user
        $insert_stmt = mysqli_prepare($conn, "INSERT INTO users (full_name, email, phone, password) VALUES (?, ?, ?, ?)");
        mysqli_stmt_bind_param($insert_stmt, "ssss", $full_name, $email, $phone, $password);
        
        if (mysqli_stmt_execute($insert_stmt)) {
            $success = 'Registration successful! Please login.';
        } else {
            $error = 'Registration failed. Please try again.';
            file_put_contents('debug_log.txt', "DB Error: " . mysqli_error($conn) . "\n", FILE_APPEND);
        }
        mysqli_stmt_close($insert_stmt);
    }
    mysqli_stmt_close($check_stmt);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login / Register - InBook</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css?v=1.2">
</head>
<body>
    <!-- 3D Animated Background -->
    <div class="bg-3d"></div>
    <div class="bg-overlay"></div>

    <div class="auth-container">
        <div class="auth-card">
            <!-- Logo -->
            <div class="logo-3d">
                <h1 class="logo-text">INBOOK</h1>
                <p class="logo-subtitle">Book your perfect court</p>
            </div>

            <!-- Alerts -->
            <?php if ($error): ?>
                <div class="alert alert-error"><?= $error ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?= $success ?></div>
            <?php endif; ?>

            <!-- Tabs -->
            <div class="tabs">
                <button class="tab-btn active" onclick="switchTab('login', this)">LOGIN</button>
                <button class="tab-btn" onclick="switchTab('register', this)">REGISTER</button>
            </div>

            <!-- Form Container -->
            <div id="form-container">
            <!-- Login Form -->
            <div id="login-form" class="form-content active">
                <form action="login.php" method="POST" onsubmit="return handleSubmit(event)">
                    <input type="hidden" name="action" value="login">
                    <div class="form-group">
                        <label class="form-label">EMAIL ADDRESS</label>
                        <div class="input-wrapper">
                            <span class="input-icon"><i class="fas fa-envelope green-icon"></i></span>
                            <input type="email" name="email" class="form-input" 
                                   placeholder="your@email.com" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">PASSWORD</label>
                        <div class="input-wrapper">
                            <span class="input-icon"><i class="fas fa-lock green-icon"></i></span>
                            <input type="password" name="password" class="form-input" 
                                   placeholder="••••••••" required>
                        </div>
                    </div>

                    <button type="submit" class="btn-submit">
                        <span>LOGIN</span>
                    </button>

                    <div class="separator">
                        <span>OR</span>
                    </div>

                    <div class="social-options">
                        <button type="button" class="btn-social btn-google" onclick="window.location.href='social_auth.php?provider=google'">
                            <i class="fab fa-google"></i> Sign in with Google
                        </button>
                        <button type="button" class="btn-social btn-apple" onclick="window.location.href='social_auth.php?provider=apple'">
                            <i class="fab fa-apple"></i> Sign in with Apple
                        </button>
                    </div>
                </form>
            </div>

            <!-- Register Form -->
            <div id="register-form" class="form-content">
                <form action="login.php" method="POST" onsubmit="return handleSubmit(event)">
                    <input type="hidden" name="action" value="register">
                    <div class="form-group">
                        <label class="form-label">FULL NAME</label>
                        <div class="input-wrapper">
                            <span class="input-icon"><i class="fas fa-user green-icon"></i></span>
                            <input type="text" name="full_name" class="form-input" 
                                   placeholder="John Doe" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">EMAIL ADDRESS</label>
                        <div class="input-wrapper">
                            <span class="input-icon"><i class="fas fa-envelope green-icon"></i></span>
                            <input type="email" name="email" class="form-input" 
                                   placeholder="your@email.com" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">PHONE NUMBER</label>
                        <div class="input-wrapper">
                            <span class="input-icon"><i class="fas fa-phone green-icon"></i></span>
                            <input type="tel" name="phone" class="form-input" 
                                   placeholder="098 123 4567" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">PASSWORD</label>
                        <div class="input-wrapper">
                            <span class="input-icon"><i class="fas fa-lock green-icon"></i></span>
                            <input type="password" name="password" class="form-input" 
                                   placeholder="••••••••" minlength="6" required>
                        </div>
                    </div>

                    <button type="submit" class="btn-submit">
                        <span>REGISTER</span>
                    </button>

                    <div class="separator">
                        <span>OR</span>
                    </div>

                    <div class="social-options">
                        <button type="button" class="btn-social btn-google" onclick="window.location.href='social_auth.php?provider=google'">
                            <i class="fab fa-google"></i> Sign up with Google
                        </button>
                        <button type="button" class="btn-social btn-apple" onclick="window.location.href='social_auth.php?provider=apple'">
                            <i class="fab fa-apple"></i> Sign up with Apple
                        </button>
                    </div>
                </form>
            </div>
            </div> <!-- End Form Container -->

            <!-- Back Link -->
            <div class="back-link">
                <a href="index.php">
                    <span>Back to Home</span>
                </a>
            </div>
        </div>
    </div>

    <script>
        // Switch between login and register tabs
        // Switch between login and register tabs
        function switchTab(tab, btn) {
            const loginForm = document.getElementById('login-form');
            const registerForm = document.getElementById('register-form');
            const container = document.getElementById('form-container');
            
            // Check if already active
            if ((tab === 'login' && loginForm.classList.contains('active')) ||
                (tab === 'register' && registerForm.classList.contains('active'))) {
                return;
            }

            // Update tab buttons
            document.querySelectorAll('.tab-btn').forEach(b => {
                b.classList.remove('active');
            });
            
            if(btn) {
                btn.classList.add('active');
            } else {
                const targetBtn = Array.from(document.querySelectorAll('.tab-btn'))
                    .find(b => b.innerText.toLowerCase().includes(tab));
                if(targetBtn) targetBtn.classList.add('active');
            }

            // Determine direction: Register is "right", Login is "left"
            // If going to Register: Login slides out Left, Register slides in Right
            // If going to Login: Register slides out Right, Login slides in Left
            
            const isLogin = tab === 'login';
            const outgoing = isLogin ? registerForm : loginForm;
            const incoming = isLogin ? loginForm : registerForm;
            
            // Lock height
            container.style.height = container.offsetHeight + 'px';
            
            // Animation classes
            const outClass = isLogin ? 'slide-out-right' : 'slide-out-left';
            const inClass = isLogin ? 'slide-in-left' : 'slide-in-right';

            // Reset classes
            outgoing.classList.remove('active', 'slide-in-left', 'slide-in-right', 'slide-out-left', 'slide-out-right');
            incoming.classList.remove('active', 'slide-in-left', 'slide-in-right', 'slide-out-left', 'slide-out-right');

            // Start animation
            outgoing.classList.add('animating', outClass);
            incoming.classList.add('animating', inClass);
            
            // Cleanup
            setTimeout(() => {
                outgoing.classList.remove('animating', outClass);
                outgoing.style.display = 'none';
                
                incoming.classList.remove('animating', inClass);
                incoming.classList.add('active');
                incoming.style.display = 'block'; // Reset to block
                
                // Animate height to new content
                // We need to briefly set height to auto to get the target height, then animate
                const newHeight = incoming.offsetHeight;
                container.style.height = newHeight + 'px';
                
                // Allow height to remain dynamic after transition
                setTimeout(() => {
                    container.style.height = 'auto';
                }, 400);
                
            }, 500); // Wait for animation
        }

        // Handle form submission with loading state
        function handleSubmit(e) {
            const button = e.target.querySelector('.btn-submit');
            const originalText = button.innerHTML;
            button.innerHTML = '<div class="loading"></div>';
            // button.disabled = true; // Preventing submission in some cases?
            
            // Let the form submit normally
            return true;
        }

        // Add parallax effect to shapes
        document.addEventListener('mousemove', (e) => {
            const shapes = document.querySelectorAll('.shape');
            const x = e.clientX / window.innerWidth;
            const y = e.clientY / window.innerHeight;
            
            shapes.forEach((shape, index) => {
                const speed = (index + 1) * 20;
                const xMove = (x - 0.5) * speed;
                const yMove = (y - 0.5) * speed;
                
                shape.style.transform = `translate(${xMove}px, ${yMove}px)`;
            });
        });

        // Auto-focus first input on page load
        window.addEventListener('load', () => {
            const firstInput = document.querySelector('.form-content.active .form-input');
            if (firstInput) {
                firstInput.focus();
            }
            
            // Allow body to fade in
            document.body.classList.add('loaded');
        });

        // Link Transition
        document.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', function(e) {
                if(this.getAttribute('href').startsWith('#') || this.target === '_blank') return;
                e.preventDefault();
                document.body.classList.remove('loaded');
                setTimeout(() => window.location.href = this.href, 500);
            });
        });

    </script>
</body>
</html>