<?php

require 'includes/config.php';
require 'includes/functions.php';

requireLogin();

if (user()['role'] === 'admin') {
    redirect('admin_pest_assessment.php');
}

redirect('farmer_pest_assessment.php');
