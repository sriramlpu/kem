<?php
require_once("header.php");
?>
<style>
    #login {
        padding: 80px 0;
        background-color: #f8f9fa;
    }

    .btn-primary {
        padding: 10px 30px;
        border-radius: 8px;
        font-size: 16px;
    }

    .card {
        border-radius: 12px;
        box-shadow: 0 4px 25px rgba(0, 0, 0, 0.1);
    }
</style>
<?php require_once("nav.php"); ?>

<section id="login" class="contact section">
   
    <div class="container">
       
        <div class="row justify-content-center align-items-center">
            
            <div class="col-md-6 col-lg-5">
                 
                <div class="card">
                    <div class="card-body p-4">
                        <!-- Message placeholder -->
                        <div id="loginMessage" class="alert d-none"></div>

                        <form id="loginForm" method="post" class="php-email-form" >
                            <div class="mb-3">
                                <label for="username" class="form-label">Email</label>
                                <input type="text" name="username" id="username" class="form-control" placeholder="Enter username or email" required>
                            </div>

                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" name="password" id="password" class="form-control" placeholder="Enter password" required>
                            </div>

                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="flexCheckChecked">
                                <label class="form-check-label" for="flexCheckChecked">Remember Me</label>
                            </div>

                            <div class="text-start">
                                <button type="submit" class="btn btn-primary w-100" id="loginBtn">
                                    <span class="btn-text">Login</span>
                                    <span id="loginLoader" class="spinner-border spinner-border-sm d-none ms-2" role="status" aria-hidden="true"></span>
                                </button>
                            </div>
                        </form>

                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- jQuery required -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
$(function() {
    $('#loginForm').on('submit', function(e) {
        e.preventDefault();
        $('#loginMessage').addClass('d-none').removeClass('alert-success alert-danger').text('');

        const rememberMe = $('#flexCheckChecked').is(':checked');
        const username = $('#username').val().trim();
        const password = $('#password').val().trim();
        const $loginBtn = $('#loginBtn');
        const $btnText = $loginBtn.find('.btn-text');
        const $loader = $('#loginLoader');

        if (username === '' || password === '') {
            $('#loginMessage')
                .removeClass('d-none alert-success')
                .addClass('alert alert-danger')
                .text('Please enter both username and password.');
            return;
        }

        // Animate loader
        $btnText.text('Logging in...');
        $loader.removeClass('d-none');
        $loginBtn.prop('disabled', true);

        $.ajax({
            url: 'login_api',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                username,
                password,
                rememberMe
            }),
            dataType: 'json',
            success: function(response) {
                $btnText.text('Login');
                $loader.addClass('d-none');
                $loginBtn.prop('disabled', false);
{console.log(response)}
                if (response.user) {
                    if (rememberMe) {
                        localStorage.setItem('rememberedUsername', username);
                        localStorage.setItem('rememberedPassword', password);
                    } else {
                        localStorage.removeItem('rememberedUsername');
                        localStorage.removeItem('rememberedPassword');
                    }

                    $('#loginMessage')
                        .removeClass('d-none alert-danger')
                        .addClass('alert alert-success')
                        .text('Login successful! Redirecting...');

                    setTimeout(() => {
                        window.location.href = response.user.redirect;
                    }, 1500);

                } else if (response.error) {
                    $('#loginMessage')
                        .removeClass('d-none alert-success')
                        .addClass('alert alert-danger')
                        .text(response.error);
                }
            },
            error: function() {
                $btnText.text('Login');
                $loader.addClass('d-none');
                $loginBtn.prop('disabled', false);
                $('#loginMessage')
                    .removeClass('d-none alert-success')
                    .addClass('alert alert-danger')
                    .text('Login failed. Please try again.');
            }
        });
    });

    // Prefill remembered credentials
    const rememberedUsername = localStorage.getItem('rememberedUsername');
    const rememberedPassword = localStorage.getItem('rememberedPassword');
    if (rememberedUsername) {
        $('#username').val(rememberedUsername);
        $('#password').val(rememberedPassword);
        $('#flexCheckChecked').prop('checked', true);
    }
});
</script>

<?php
require_once("footer.php");
?>
