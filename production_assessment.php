<?php

require 'includes/config.php';
require 'includes/functions.php';

requireLogin();

if (user()['role'] === 'admin') {
    redirect('admin_production_assessment.php');
}

redirect('farmer_production_assessment.php');
