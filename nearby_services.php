<?php
// nearby_services.php — redirects to dashboard pre-filtered to services
require_once 'config.php';
requireLogin();
header('Location: dashboard.php?category=plumbing');
exit;
