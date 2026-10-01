<?php

require 'includes/config.php';
require 'includes/functions.php';

requireLogin();

if (user()['role'] === 'admin') {
    redirect('admin_dashboard.php');
}

redirect('farmer_dashboard.php');
