<?php
/**
 * Admin — site settings (settings table) and plan limits/prices (plans table).
 * Only settings that the code actually reads are offered here.
 */

declare(strict_types=1);

set_page(['title' => 'Settings']);

$switches = [
    'maintenance_mode'      => ['Maintenance mode', 'Visitors see a "back soon" page and the API refuses work. Admins can still use the site.'],
    'registration_enabled'  => ['Allow new registrations', 'When off, the sign-up form is closed.'],
    'guest_uploads_enabled' => ['Allow guests to use tools', 'When off, visitors must log in before uploading files.'],
];
$numbers = [
    'guest_max_file_size_mb' => ['Guest max file size (MB)', 1, 500],
    'guest_daily_jobs_limit' => ['Guest tasks per 24 hours', 1, 10000],
];
$errors = [];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_verify();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'site') {
        $values = [];
        foreach ($switches as $key => $_) {
            $values[$key] = isset($_POST[$key]) ? '1' : '0';
        }
        foreach ($numbers as $key => [$label, $min, $max]) {
            $v = filter_var($_POST[$key] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => $min, 'max_range' => $max]]);
            if ($v === false) {
                $errors[] = "$label must be between $min and $max.";
            }
            $values[$key] = (string) $v;
        }
        $email = trim((string) ($_POST['contact_email'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Enter a valid contact email.';
        }
        $values['contact_email'] = $email;

        if (!$errors) {
            foreach ($values as $key => $value) {
                db_exec('INSERT INTO settings (setting_key, setting_value, updated_by) VALUES (?, ?, ?)
                         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)',
                    [$key, $value, $admin['id']]);
            }
            admin_audit('settings.updated', 'settings', null, $values);
            flash('success', 'Settings saved.');
            redirect(admin_url('settings'));
        }
    }

    if ($action === 'plan') {
        $id = (int) ($_POST['id'] ?? 0);
        $plan = db_one('SELECT * FROM plans WHERE id = ?', [$id]);
        $int = static fn(string $k, int $min, int $max): int|false => filter_var($_POST[$k] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => $min, 'max_range' => $max]]);
        $money = static function (string $k): int|false {
            $v = str_replace(',', '.', trim((string) ($_POST[$k] ?? '')));
            return preg_match('/^\d{1,6}(\.\d{1,2})?$/', $v) ? (int) round((float) $v * 100) : false;
        };
        $fields = [
            'max_file_size_mb'    => $int('max_file_size_mb', 1, 2048),
            'max_files_per_job'   => $int('max_files_per_job', 1, 200),
            'history_days'        => $int('history_days', 1, 3650),
            'price_monthly_cents' => $money('price_monthly'),
            'price_yearly_cents'  => $money('price_yearly'),
        ];
        $daily = trim((string) ($_POST['daily_jobs_limit'] ?? ''));
        $dailyValue = $daily === '' ? null : filter_var($daily, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000000]]);
        if ($plan === null || in_array(false, $fields, true) || $dailyValue === false) {
            $errors[] = 'Check the plan values: whole numbers for limits, prices like 9 or 9.99, daily tasks empty for unlimited.';
        } else {
            db_exec(
                'UPDATE plans SET max_file_size_mb = ?, max_files_per_job = ?, history_days = ?, price_monthly_cents = ?, price_yearly_cents = ?,
                        daily_jobs_limit = ?, batch_processing = ? WHERE id = ?',
                [$fields['max_file_size_mb'], $fields['max_files_per_job'], $fields['history_days'], $fields['price_monthly_cents'],
                 $fields['price_yearly_cents'], $dailyValue, $fields['max_files_per_job'] > 5 ? 1 : 0, $id]
            );
            admin_audit('plan.updated', 'plan', $id, $fields + ['daily_jobs_limit' => $dailyValue]);
            flash('success', $plan['name'] . ' plan updated.');
            redirect(admin_url('settings'));
        }
    }
}

$plans = db_all('SELECT * FROM plans ORDER BY sort_order');
$phpLimit = php_upload_limit_bytes();
?>
<div class="account-head"><div><h1>Settings</h1><p class="muted">Changes apply immediately and are recorded in the audit log.</p></div></div>

<?php foreach ($errors as $err): ?><div class="alert alert-error" role="alert"><?= icon('alert') ?><p><?= e($err) ?></p></div><?php endforeach; ?>

<form class="panel form" method="post" action="<?= e(admin_url('settings')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="site">
    <h2 class="h4">Site</h2>
    <?php foreach ($switches as $key => [$label, $help]): ?>
    <label class="check"><input type="checkbox" name="<?= e($key) ?>" value="1"<?= setting($key, '0') === '1' ? ' checked' : '' ?>><span><strong><?= e($label) ?></strong><br><span class="hint"><?= e($help) ?></span></span></label>
    <?php endforeach; ?>
    <div class="form-row form-row-2">
        <?php foreach ($numbers as $key => [$label, $min, $max]): ?>
        <div class="field"><label class="label" for="s-<?= e($key) ?>"><?= e($label) ?></label>
            <input class="input" id="s-<?= e($key) ?>" name="<?= e($key) ?>" type="number" min="<?= $min ?>" max="<?= $max ?>" value="<?= e((string) setting($key, '')) ?>"></div>
        <?php endforeach; ?>
    </div>
    <div class="field"><label class="label" for="s-contact">Contact email</label>
        <input class="input" id="s-contact" name="contact_email" type="email" value="<?= e((string) setting('contact_email', (string) config('contact.email'))) ?>">
        <p class="hint">Shown on the contact page and used as the reply-to address for emails.</p></div>
    <div><button class="btn btn-primary" type="submit">Save settings</button></div>
</form>

<div class="panel">
    <div class="panel-head"><h2>Plans</h2><span class="hint">The server accepts uploads up to <?= e(format_bytes($phpLimit)) ?> (PHP limit) — larger plan limits are capped to this.</span></div>
    <div class="stack-lg">
    <?php foreach ($plans as $p): ?>
        <form class="form plan-edit" method="post" action="<?= e(admin_url('settings')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="plan"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
            <h3 class="h4"><?= e($p['name']) ?></h3>
            <div class="plan-edit-grid">
                <div class="field"><label class="label" for="p<?= (int) $p['id'] ?>-mb">Max file (MB)</label><input class="input" id="p<?= (int) $p['id'] ?>-mb" name="max_file_size_mb" type="number" min="1" value="<?= (int) $p['max_file_size_mb'] ?>"></div>
                <div class="field"><label class="label" for="p<?= (int) $p['id'] ?>-files">Files per task</label><input class="input" id="p<?= (int) $p['id'] ?>-files" name="max_files_per_job" type="number" min="1" value="<?= (int) $p['max_files_per_job'] ?>"></div>
                <div class="field"><label class="label" for="p<?= (int) $p['id'] ?>-daily">Tasks / 24 h</label><input class="input" id="p<?= (int) $p['id'] ?>-daily" name="daily_jobs_limit" type="number" min="1" placeholder="Unlimited" value="<?= $p['daily_jobs_limit'] === null ? '' : (int) $p['daily_jobs_limit'] ?>"></div>
                <div class="field"><label class="label" for="p<?= (int) $p['id'] ?>-hist">History (days)</label><input class="input" id="p<?= (int) $p['id'] ?>-hist" name="history_days" type="number" min="1" value="<?= (int) $p['history_days'] ?>"></div>
                <div class="field"><label class="label" for="p<?= (int) $p['id'] ?>-pm">Price / month</label><input class="input" id="p<?= (int) $p['id'] ?>-pm" name="price_monthly" inputmode="decimal" value="<?= e(number_format((int) $p['price_monthly_cents'] / 100, 2, '.', '')) ?>"></div>
                <div class="field"><label class="label" for="p<?= (int) $p['id'] ?>-py">Price / year</label><input class="input" id="p<?= (int) $p['id'] ?>-py" name="price_yearly" inputmode="decimal" value="<?= e(number_format((int) $p['price_yearly_cents'] / 100, 2, '.', '')) ?>"></div>
            </div>
            <div><button class="btn btn-secondary btn-sm" type="submit">Save <?= e($p['name']) ?></button></div>
        </form>
    <?php endforeach; ?>
    </div>
</div>

<div class="panel">
    <h2 class="h4">Set in config.php (not editable here)</h2>
    <dl class="kv mt-4">
        <dt>Upload retention</dt><dd><?= (int) config('retention.upload_minutes') ?> minutes</dd>
        <dt>Result retention</dt><dd><?= (int) config('retention.result_minutes') ?> minutes</dd>
        <dt>Server file cap</dt><dd><?= (int) config('uploads.hard_max_mb') ?> MB</dd>
        <dt>Conversion service</dt><dd><?= e((string) config('converter.provider', 'none')) ?></dd>
        <dt>OCR service</dt><dd><?= e((string) config('ocr.provider', 'none')) ?></dd>
        <dt>Payments</dt><dd><?= e((string) config('payment.provider', 'none')) ?></dd>
        <dt>Environment</dt><dd><?= e((string) config('app.env')) ?><?= config('app.debug') ? ' · <strong>debug ON</strong>' : '' ?></dd>
    </dl>
</div>
