<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require_login();

$user = current_user();
$userId = (int) $user['id'];
$editingContact = null;
$errors = [];
$formContact = [
    'id' => '',
    'name' => '',
    'relation' => '',
    'phone' => '',
    'email' => '',
    'priority_level' => '1',
];
$contacts = get_user_contacts($userId);

if (!empty($_GET['edit'])) {
    foreach ($contacts as $contact) {
        if ((int) $contact['id'] === (int) $_GET['edit']) {
            $editingContact = $contact;
            break;
        }
    }
}

if ($editingContact) {
    $formContact = [
        'id' => (string) ($editingContact['id'] ?? ''),
        'name' => (string) ($editingContact['name'] ?? ''),
        'relation' => (string) ($editingContact['relation'] ?? ''),
        'phone' => (string) ($editingContact['phone'] ?? ''),
        'email' => (string) ($editingContact['email'] ?? ''),
        'priority_level' => (string) ($editingContact['priority_level'] ?? '1'),
    ];
}

if (is_post()) {
    verify_csrf_token();
    $action = (string) ($_POST['action'] ?? 'save');

    if ($action === 'delete') {
        delete_contact($userId, (int) ($_POST['contact_id'] ?? 0));
        flash_message('success', 'Emergency contact removed.');
        redirect_to('contacts.php');
    }

    $errors = save_contact($userId, $_POST, !empty($_POST['contact_id']) ? (int) ($_POST['contact_id'] ?? 0) : null);

    if ($errors === []) {
        flash_message('success', 'Emergency contact saved.');
        redirect_to('contacts.php');
    }

    $formContact = [
        'id' => (string) ($_POST['contact_id'] ?? ''),
        'name' => trim((string) ($_POST['name'] ?? '')),
        'relation' => trim((string) ($_POST['relation'] ?? '')),
        'phone' => trim((string) ($_POST['phone'] ?? '')),
        'email' => trim((string) ($_POST['email'] ?? '')),
        'priority_level' => (string) ($_POST['priority_level'] ?? '1'),
    ];
}

render_header('Emergency Contacts', [
    'description' => 'Manage emergency contacts with priority order.',
]);
?>

<section class="section-block">
    <div class="section-heading">
        <span class="eyebrow">Emergency Contacts</span>
        <h1>Trusted People For Fast Response</h1>
        <p>Prioritize the people the alert agent should surface first during high-risk events. Save email addresses too if you want Emergency Mode to send mail alerts automatically.</p>
    </div>
</section>

<section class="card-grid two-up">
    <article class="panel">
        <div class="panel-head">
            <div>
                <span class="eyebrow"><?= $editingContact ? 'Update Contact' : 'Add Contact' ?></span>
                <h2><?= $editingContact ? 'Edit emergency contact' : 'Create Emergency Contact' ?></h2>
            </div>
        </div>

        <?php if ($errors): ?>
            <div class="inline-errors">
                <?php foreach ($errors as $error): ?>
                    <p><?= e($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="post" class="form-grid">
            <?= csrf_field() ?>
            <input type="hidden" name="contact_id" value="<?= e($formContact['id']) ?>">
            <label>
                <span>Name</span>
                <input type="text" name="name" value="<?= e($formContact['name']) ?>" required>
            </label>
            <label>
                <span>Relationship</span>
                <input type="text" name="relation" value="<?= e($formContact['relation']) ?>" placeholder="Mother, friend, sister">
            </label>
            <label>
                <span>Phone</span>
                <input type="text" name="phone" value="<?= e($formContact['phone']) ?>" placeholder="01712345678" inputmode="numeric" maxlength="11" pattern="01[0-9]{9}" title="Enter an 11-digit Bangladesh mobile number" required>
            </label>
            <label>
                <span>Email</span>
                <input type="email" name="email" value="<?= e($formContact['email']) ?>" placeholder="Required for email alerts">
            </label>
            <label>
                <span>Priority Level</span>
                <select name="priority_level">
                    <?php for ($priority = 1; $priority <= 5; $priority++): ?>
                        <option value="<?= $priority ?>" <?= selected_option((string) $priority, $formContact['priority_level']) ?>><?= $priority ?></option>
                    <?php endfor; ?>
                </select>
            </label>
            <p class="panel-note">Bangladesh mobile numbers only: 11 digits starting with <strong>01</strong>.</p>
            <button class="button button-primary" type="submit">Save Contact</button>
        </form>
    </article>

    <article class="panel">
        <div class="panel-head">
            <div>
                <span class="eyebrow">Saved List</span>
                <h2>Your Emergency Network</h2>
            </div>
        </div>

        <?php if ($contacts): ?>
            <div class="stack">
                <?php foreach ($contacts as $contact): ?>
                    <div class="list-card">
                        <div>
                            <strong><?= e($contact['name']) ?></strong>
                            <p><?= e($contact['relation']) ?> • <?= e($contact['phone']) ?></p>
                            <small><?= e($contact['email'] ?: 'No email saved') ?></small>
                            <small>Priority <?= e((string) $contact['priority_level']) ?></small>
                        </div>
                        <div class="button-row compact">
                            <a class="button button-secondary button-small" href="<?= e(route_url('contacts.php?edit=' . $contact['id'])) ?>">Edit</a>
                            <form method="post" class="inline-form" data-confirm="Delete this contact?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="contact_id" value="<?= e((string) $contact['id']) ?>">
                                <button class="button button-ghost button-small" type="submit">Delete</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p>No contacts saved yet. Add at least two trusted people so Emergency Mode has someone to prioritize.</p>
        <?php endif; ?>
    </article>
</section>


