<?php include('includes/header.php'); ?>

<div class="container-xxl">
    <div class="authentication-wrapper authentication-basic container-p-y">
        <div class="authentication-inner py-4">
            <!-- Login -->
            <div class="card">
                <div class="card-body">
                    <div class="app-brand text-center mb-4 mt-2">
                        <img src="<?= ADMIN_PANEL_LOGO ?>" alt="logo icon" style="width: 215px; display: block; margin: 0 auto;">
                    </div>

                    <h4 class="text-center mb-4 pt-2" style="font-size: 20px;">Welcome to <?= SITE_TITLE ?>! ❤️</h4>

                    <form id="formAuthentication" class="mb-3" method="post">
                        <div class="mb-3">
                            <label for="email" class="form-label">Email or Username</label>
                            <input
                                type="text"
                                class="form-control"
                                id="email"
                                name="email-username"
                                placeholder="Enter your email or username"
                                autofocus />
                        </div>

                        <div class="mb-3 form-password-toggle">
                            <div class="d-flex justify-content-between">
                                <label class="form-label" for="password">Password</label>
                            </div>
                            <div class="input-group input-group-merge">
                                <input
                                    type="password"
                                    id="password"
                                    class="form-control"
                                    name="password"
                                    placeholder="••••••••••••"
                                    aria-describedby="password" />
                                <span class="input-group-text cursor-pointer"><i class="ti ti-eye-off"></i></span>
                            </div>
                        </div>

                        <div class="mb-3">
                            <!-- <button type="submit" class="btn btn-primary d-grid w-100" id="login-btn">Sign in</button> -->
                            <button type="submit" id="login-btn" class="btn btn-primary d-grid w-100">Login</button>
                        </div>
                    </form>

                    <p class="text-center">
                        <span>Designed & Developed by</span>
                        <a href="https://www.oceaninfotech.co.in/" style="color:#16702a; font-weight:800; text-decoration:none;">
                            <span>Ocean Infotech</span>
                        </a>
                    </p>
                </div>
            </div>
            <!-- /Login -->
        </div>
    </div>
</div>

<?php include('includes/footer_js.php'); ?>

<script>
    $(document).ready(function() {
        $("#login-btn").click(function(e) {
            e.preventDefault();
            var loginUname = $("#email").val();
            var loginPassword = $("#password").val();
            if (loginUname == '') {
                alert("Please Enter Username!");
                $("#inputUsername").focus();
                return false;
            }
            if (loginPassword == '') {
                alert("Please Enter Password!");
                $("#inputPass").focus();
                return false;
            }
            $.ajax({
                type: "POST",
                url: "ajax.php",
                data: {
                    action: "login",
                    loginUname: loginUname,
                    loginPassword: loginPassword
                },
                success: function(data) {
                    if (data == "success") {
                        document.location.href = 'dashboard.php';
                    } else if (data == "not active") {
                        alert(
                            'Your account is not active. Please contact admin for that.'
                        );
                    } else {
                        alert('Wrong Username OR Password!');
                    }
                },
                error: function() {
                    alert('Error while contacting server, please try again');
                }
            });
        });
    });
</script>
