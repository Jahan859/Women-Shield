<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require_login();

$user = current_user();
$userId = (int) $user['id'];
$contacts = get_user_contacts($userId);
$reports = get_user_reports($userId);
$latestHighRisk = null;

foreach ($reports as $report) {
    if ((int) $report['danger_score'] >= 70) {
        $latestHighRisk = $report;
        break;
    }
}

if (is_post()) {
    verify_csrf_token();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'start') {
        $emailSummary = start_emergency_mode($userId, trim((string) ($_POST['location_summary'] ?? '')));
        $messages = ['Emergency Mode activated.'];

        if ($emailSummary['sent'] > 0) {
            $messages[] = $emailSummary['sent'] . ' emergency email(s) sent.';
        }

        if ($emailSummary['missing_email'] > 0) {
            $messages[] = $emailSummary['missing_email'] . ' contact(s) have no email address saved.';
        }

        if ($emailSummary['failed'] > 0) {
            $messages[] = $emailSummary['failed'] . ' email(s) failed.';
        }

        if (!empty($emailSummary['details'])) {
            $messages[] = $emailSummary['details'];
        }

        flash_message('success', implode(' ', $messages));
        redirect_to('emergency.php');
    }

    if ($action === 'stop') {
        stop_emergency_mode($userId);
        flash_message('success', 'Emergency Mode closed.');
        redirect_to('emergency.php');
    }
}

$activeEmergency = get_active_emergency_session($userId);
$alertActions = $latestHighRisk ? build_alert_agent_actions($latestHighRisk, $contacts) : null;
$shareMessage = 'Emergency alert from ' . $user['name'] . '. I may need help. Please contact me immediately and track my current location.';
$mailStatus = mail_setup_status($userId);

render_header('Emergency Mode', [
    'description' => 'Emergency mode and rapid response planning.',
]);
?>

<section class="section-block">
    <div class="section-heading">
        <span class="eyebrow">Emergency Mode</span>
        <h1>Fast Response Center For Urgent Situations</h1>
        <p>Use this page to activate a high-visibility response workflow and prepare outreach to trusted contacts.</p>
    </div>
</section>

<section class="card-grid two-up">
    <article class="panel emergency-panel">
        <div class="panel-head">
            <div>
                <span class="eyebrow">Status</span>
                <h2><?= $activeEmergency ? 'Emergency Mode Active' : 'Emergency Mode Standby' ?></h2>
            </div>
            <span class="badge <?= $activeEmergency ? 'badge-danger' : 'badge-safe' ?>">
                <?= $activeEmergency ? 'ACTIVE' : 'READY' ?>
            </span>
        </div>
        <p>
            <?= $activeEmergency
                ? 'Emergency support is active. Your current location can be shared with emergency contacts by email, so stay connected and contact emergency services if danger is immediate.'
                : 'Activate this mode to log an emergency session, raise alerts in the dashboard, and prioritize your top contacts.' ?>
        </p>

        <?php if (!$activeEmergency): ?>
            <form method="post" class="form-grid" data-emergency-start>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="start">
                <label>
                    <span>Current Location Summary</span>
                    <input type="text" id="location-summary" name="location_summary" placeholder="Street, landmark, or neighborhood">
                </label>
                <p class="panel-note" id="location-status">If browser location access is allowed, Women Shield will auto-fill your current coordinates and include them in the emergency email. If automatic location fails, enter your location manually before activating Emergency Mode.</p>
                <button class="button button-primary emergency-button" id="emergency-start-button" type="submit">Activate Emergency Mode</button>
            </form>
            <?php if (!$mailStatus['ready']): ?>
                <p class="panel-note">
                    Emergency emails are not fully configured yet.
                    <?php if (is_admin()): ?>
                        <a href="<?= e(route_url('mail_setup.php')) ?>">Open mail setup</a>
                    <?php else: ?>
                        Ask an admin to complete the email setup.
                    <?php endif; ?>
                </p>
            <?php endif; ?>
        <?php else: ?>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="stop">
                <button class="button button-ghost emergency-button" type="submit">Close Emergency Mode</button>
            </form>
        <?php endif; ?>
    </article>

    <article class="panel">
        <div class="panel-head">
            <div>
                <span class="eyebrow">Alert Agent</span>
                <h2>Immediate Action Checklist</h2>
            </div>
        </div>
        <?php if ($alertActions): ?>
            <p><strong><?= e($alertActions['level']) ?>:</strong> <?= e($alertActions['message']) ?></p>
        <?php else: ?>
            <p>No high-risk incident is on file right now, so this checklist shows the baseline emergency response path.</p>
        <?php endif; ?>
        <ul class="feature-list">
            <li>Call local emergency services if there is direct physical danger.</li>
            <li>Move toward a public, well-lit area and avoid isolated shortcuts.</li>
            <li>Share your current location and recent route with a trusted person if it is safe to do so.</li>
            <li>Keep your phone charged, unlocked, and easy to access.</li>
        </ul>
    </article>
</section>

<section class="card-grid two-up">
    <article class="panel">
        <div class="panel-head">
            <div>
                <span class="eyebrow">Share Message</span>
                <h2>Copy-Ready Outreach Text</h2>
            </div>
        </div>
        <textarea rows="6" readonly><?= e($shareMessage) ?></textarea>
        <p class="panel-note">You can copy this text into WhatsApp, SMS, or a call note while using Emergency Mode.</p>
    </article>

    <article class="panel">
        <div class="panel-head">
            <div>
                <span class="eyebrow">Priority Contacts</span>
                <h2>Who To Notify First</h2>
            </div>
        </div>
        <?php if ($contacts): ?>
            <div class="stack">
                <?php foreach (array_slice($contacts, 0, 5) as $contact): ?>
                    <div class="list-card">
                        <div>
                            <strong><?= e($contact['name']) ?></strong>
                            <p><?= e($contact['relation']) ?> • <?= e($contact['phone']) ?></p>
                            <small><?= e($contact['email'] ?: 'No email saved for emergency mail alerts') ?></small>
                        </div>
                        <span class="badge badge-caution">Priority <?= e((string) $contact['priority_level']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p>No emergency contacts saved. Add trusted people from the contacts page first.</p>
        <?php endif; ?>
        <p class="panel-note">Automatic emergency emails are sent only to contacts with an email address saved. Current mail driver: <?= e(configured_mail_driver_label()) ?>.</p>
    </article>
</section>

<?php
$extraScripts = '
    <script>
        const LOCATION_STORAGE_KEY = "womenShieldCurrentLocation";
        const emergencyStartForm = document.querySelector("[data-emergency-start]");
        const locationField = document.getElementById("location-summary");
        const locationStatus = document.getElementById("location-status");
        const startButton = document.getElementById("emergency-start-button");

        const readStoredLocation = () => {
            try {
                const raw = localStorage.getItem(LOCATION_STORAGE_KEY);

                if (!raw) {
                    return null;
                }

                const parsed = JSON.parse(raw);

                return typeof parsed.summary === "string" && parsed.summary.trim() !== "" ? parsed : null;
            } catch (error) {
                return null;
            }
        };

        const setStatus = (message) => {
            if (locationStatus) {
                locationStatus.textContent = message;
            }
        };

        const setCurrentLocation = (position) => {
            if (!locationField) {
                return;
            }

            const summary = `${position.coords.latitude.toFixed(6)}, ${position.coords.longitude.toFixed(6)}`;
            locationField.value = summary;

            try {
                localStorage.setItem(LOCATION_STORAGE_KEY, JSON.stringify({
                    summary,
                    capturedAt: Date.now()
                }));
            } catch (error) {
                console.warn("Could not store current location locally.", error);
            }

            setStatus("Current location captured. Emergency email will include these coordinates.");
        };

        const applyStoredLocation = (storedLocation, message) => {
            if (!locationField || !storedLocation || typeof storedLocation.summary !== "string") {
                return false;
            }

            locationField.value = storedLocation.summary;
            setStatus(message);

            return true;
        };

        if (locationField) {
            const storedLocation = readStoredLocation();

            if (storedLocation) {
                applyStoredLocation(storedLocation, "Using your last captured location. Emergency email will include these coordinates unless a newer location is detected.");
            }
        }

        if (locationField && navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                setCurrentLocation,
                () => {
                    const storedLocation = readStoredLocation();

                    if (!applyStoredLocation(storedLocation, "Live location could not be read automatically, so Women Shield will use your last captured location.")) {
                        setStatus("Current location could not be read automatically. Allow location access or enter your location manually before activating Emergency Mode.");
                    }
                },
                {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 0
                }
            );
        }

        if (emergencyStartForm && locationField && navigator.geolocation) {
            let retrySubmitWithLocation = true;

            emergencyStartForm.addEventListener("submit", (event) => {
                if (!retrySubmitWithLocation || locationField.value.trim() !== "") {
                    return;
                }

                event.preventDefault();

                if (startButton) {
                    startButton.disabled = true;
                    startButton.textContent = "Getting Current Location...";
                }

                setStatus("Getting your current location before sending the emergency email...");

                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        setCurrentLocation(position);
                        retrySubmitWithLocation = false;
                        emergencyStartForm.submit();
                    },
                    () => {
                        const storedLocation = readStoredLocation();

                        if (applyStoredLocation(storedLocation, "Live location could not be read just now, so Women Shield will send the emergency email with your last captured location.")) {
                            retrySubmitWithLocation = false;
                            emergencyStartForm.submit();
                            return;
                        }

                        if (startButton) {
                            startButton.disabled = false;
                            startButton.textContent = "Activate Emergency Mode";
                        }

                        setStatus("Location is required for the emergency email. Allow location access or type your current location manually, then try again.");
                        locationField.focus();
                    },
                    {
                        enableHighAccuracy: true,
                        timeout: 15000,
                        maximumAge: 0
                    }
                );
            });
        } else if (emergencyStartForm && locationField) {
            emergencyStartForm.addEventListener("submit", (event) => {
                if (locationField.value.trim() !== "") {
                    return;
                }

                event.preventDefault();
                setStatus("Location is required for the emergency email. Enter your current location manually before activating Emergency Mode.");
                locationField.focus();
            });
        }
    </script>
';

render_footer($extraScripts);
?>
