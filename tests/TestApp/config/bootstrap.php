<?php
declare(strict_types=1);

use Cake\Core\Configure;

if (file_exists(CONFIG . 'app_test.php')) {
    Configure::write(require CONFIG . 'app_test.php');
}
