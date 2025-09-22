<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IMS - Login Page</title>
    <link rel="icon" href="" type="images/png">
    <link rel="stylesheet" href="css/login.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

</head>

<body>

    <div class="container">
        <div class="left">
            <div class="overlay"></div>
            <div class="top-text">IMS</div>
            <div class="quote">
                <h2>Inventory in Sales Management</h2>
                <p>You can get everything you want if you work hard, trust the process, and stick to the plan.</p>
            </div>
        </div>

        <div class="right" id="rightPanel">
            <div class="logo">IMS</div>
            <div class="form-container" id="signin">
                <h1>Welcome Back</h1>
                <p>Enter your email and password to access your account</p>
                <form>
                    <div class="form-group"><input type="email" placeholder="Enter your email" required></div>
                    <div class="form-group"><input type="password" placeholder="Enter your password" required></div>

                    <div class="options">
                        <label><input type="checkbox"> Remember me</label>
                        <a href="#">Forgot Password</a>
                    </div>

                    <button class="btn btn-primary" type="submit">Sign In</button>
                    <div class="toggle-link">Don’t have an account? <a id="showSignup">Sign Up</a></div>
                </form>
            </div>

            <div class="form-container" id="signup">
                <h1>Create Account</h1>
                <p>Fill in the details below to create your account</p>
                <form>
                    <div class="input-group">
                        <div class="form-group"><input type="text" placeholder="Last Name" required></div>
                        <div class="form-group"><input type="text" placeholder="First Name" required></div>
                    </div>
                    <div class="input-group">
                        <div class="form-group"><input type="text" placeholder="Contact" required></div>
                        <div class="form-group"><input type="email" placeholder="Email" required></div>
                    </div>
                    <div class="form-group"><input type="text" placeholder="Username" required></div>
                    <div class="input-group">
                        <div class="form-group"><input type="password" placeholder="Password" required></div>
                        <div class="form-group">
                            <select required>
                                <option value="">Select Type</option>
                                <option value="admin">Admin</option>
                                <option value="staff">Staff</option>
                                <option value="bodegero">Bodegero</option>
                            </select>
                        </div>
                    </div>
                    <button class="btn btn-primary" type="submit">Sign Up</button>
                    <div class="toggle-link">Already have an account? <a id="showSignin">Sign In</a></div>
                </form>
            </div>
        </div>
    </div>

    <script>
        const rightPanel = document.getElementById('rightPanel');
        const showSignup = document.getElementById('showSignup');
        const showSignin = document.getElementById('showSignin');

        showSignup.addEventListener('click', () => rightPanel.classList.add('active'));
        showSignin.addEventListener('click', () => rightPanel.classList.remove('active'));
    </script>
</body>

</html>