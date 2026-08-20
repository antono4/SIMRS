<?php
declare(strict_types=1);

auth_logout();
flash_set('success', 'Anda telah keluar dari sistem.');
redirect(url('login'));
