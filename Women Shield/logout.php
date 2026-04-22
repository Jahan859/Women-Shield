<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

logout_account();
flash_message('success', 'You have been logged out.');
redirect_to('login.php');
