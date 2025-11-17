<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bethel Pharmacy - Login</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <!-- Login Form -->
        <div class="form-container" id="loginForm">
            <div class="logo-container">
                <img src="assets/bethel_logo.png" alt="Bethel Pharmacy" class="logo">
            </div>
            
            <h1>WELCOME BACK</h1>
            <p class="subtitle">Please enter your details.</p>
            
            <form method="POST" action="includes/process.php">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" placeholder="Enter your Username" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="**********" required>
                </div>
                
                <div class="form-options">
                    <label class="checkbox-container">
                        <input type="checkbox" name="remember">
                        <span>Remember me</span>
                    </label>
                    <a href="#" class="forgot-password">Forgot password</a>
                </div>
                
                <button name="login" type="submit" class="btn-submit">Log in</button>
                
                <p class="toggle-form">Don't have an account? <a href="register.php">Sign up</a></p>
            </form>
        </div>
    </div>
</body>
</html>