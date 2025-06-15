<?php
namespace block_teamdashboard\privacy;

use core_privacy\local\metadata
ull_provider;

class provider implements null_provider {
    public static function get_reason(): string {
        return 'privacy:metadata';
    }
}
