<?php

require 'includes/config.php';
require 'includes/functions.php';

requireLogin();
redirect(user()['role'] === 'admin' ? 'incidents.php' : 'farmer_pest_assessment.php');
