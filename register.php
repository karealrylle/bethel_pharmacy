<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bethel Pharmacy - Register</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <!-- Register Form -->
        <div class="form-container" id="registerForm">
            <div class="logo-container">
                <img src="assets/bethel_logo.png" alt="Bethel Pharmacy" class="logo">
            </div>
            
            <h1>CREATE ACCOUNT</h1>
            <p class="subtitle">Please fill in your details.</p>
            
            <form method="POST" action="includes/process.php">
                <div class="form-group">
                    <label for="reg-username">Username</label>
                    <input type="text" id="reg-username" name="username" placeholder="Enter your Username" required>
                </div>
                
                <div class="form-group">
                    <label for="reg-email">Email</label>
                    <input type="email" id="reg-email" name="email" placeholder="Enter your Email" required>
                </div>
                
                <div class="form-group">
                    <label for="reg-password">Password</label>
                    <input type="password" id="reg-password" name="password" placeholder="**********" required>
                </div>
                
                <div class="form-group">
                    <label for="reg-confirm">Confirm Password</label>
                    <input type="password" id="reg-confirm" name="confirm_password" placeholder="**********" required>
                </div>
                
                <button name="register" type="submit" class="btn-submit">Sign up</button>
                
                <p class="toggle-form">Already have an account? <a href="index.php">Sign in</a></p>
            </form>
        </div>
    </div>
</body>
</html>