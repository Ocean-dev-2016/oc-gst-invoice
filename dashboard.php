<?php
require_once __DIR__ . '/root/config.php';

include ('includes/header.php');
?>

<body>
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">
            <?php include ('sidebar.php'); ?>

            <div class="layout-page">
                <?php include ('navbar.php'); ?>

                <div class="content-wrapper">
                    <div class="container-xxl flex-grow-1 container-p-y">
                        <div class="card border-0 shadow-sm mb-4" style="background: linear-gradient(72.47deg,#e77820 22.16%,#ffffff 50%,#146e29 76.47%); color: #fff;">
                            <div class="card-body py-4">
                                <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
                                    <div>
                                        <h3 class="mb-1 text-white">Welcome, <?= strtoupper(htmlspecialchars($_SESSION['username'])) ?></h3>
                                        <p class="mb-0 text-white">Welcome to your dashboard. Control panel is ready.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php include ('includes/footer.php'); ?>
                    <div class="content-backdrop fade"></div>
                </div>
            </div>
        </div>

        <div class="layout-overlay layout-menu-toggle"></div>
        <div class="drag-target"></div>
    </div>

    <?php include ('includes/footer_js.php'); ?>
</body>
</html>
