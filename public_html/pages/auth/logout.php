<?php
/**
 * /logout — POST only (with CSRF token), so links/images on other sites can't
 * log people out. A plain GET just goes home.
 */

declare(strict_types=1);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    redirect('/');
}
csrf_verify();
log_out_user();
flash('success', 'You have been logged out.');
redirect('/');
