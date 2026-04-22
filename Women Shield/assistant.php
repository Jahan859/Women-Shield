<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require_login();

$user = current_user();
$metrics = get_dashboard_metrics((int) $user['id']);
$history = array_reverse(get_chat_history((int) $user['id']));

render_header('AI Assistant', [
    'description' => 'AI chat assistant for safety guidance.',
]);
?>

<section class="section-block">
    <div class="section-heading">
        <span class="eyebrow">AI Chat Assistant</span>
        <h1>Ask for safety guidance in plain language</h1>
        <p>Chat about emergency plans, route safety, reporting, or what to do next in a risky situation.</p>
    </div>
</section>

<section class="card-grid two-up">
    <article class="panel">
        <div class="panel-head">
            <div>
                <span class="eyebrow">Assistant</span>
                <h2>Conversation</h2>
            </div>
        </div>
        <div id="chat-log" class="chat-log">
            <?php if ($history): ?>
                <?php foreach ($history as $entry): ?>
                    <div class="chat-bubble chat-user"><?= e($entry['message']) ?></div>
                    <div class="chat-bubble chat-ai"><?= e($entry['response']) ?></div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="chat-bubble chat-ai">I can help with emergency steps, report filing, night travel safety, and contact readiness.</div>
            <?php endif; ?>
        </div>
        <form id="assistant-form" class="assistant-form">
            <?= csrf_field() ?>
            <textarea id="assistant-message" name="message" rows="4" placeholder="Example: I need to travel home after 10 PM. What precautions should I take?" required></textarea>
            <button class="button button-primary" type="submit">Send Message</button>
        </form>
    </article>

    <article class="panel">
        <div class="panel-head">
            <div>
                <span class="eyebrow">Context</span>
                <h2>Assistant awareness</h2>
            </div>
        </div>
        <div class="mini-stats vertical">
            <span>Saved contacts: <?= e((string) $metrics['total_contacts']) ?></span>
            <span>High risk reports: <?= e((string) $metrics['high_risk_count']) ?></span>
            <span>Emergency Mode: <?= $metrics['active_emergency'] ? 'Active' : 'Standby' ?></span>
        </div>
        <ul class="feature-list">
            <li>Ask how to respond in an emergency.</li>
            <li>Ask what details to include in a report.</li>
            <li>Ask which routes or timings may be riskier.</li>
            <li>Ask how to use the contacts and Emergency Mode tools.</li>
        </ul>
    </article>
</section>

<?php
$extraScripts = '
    <script>
        const assistantForm = document.getElementById("assistant-form");
        const assistantMessage = document.getElementById("assistant-message");
        const chatLog = document.getElementById("chat-log");

        assistantForm.addEventListener("submit", async (event) => {
            event.preventDefault();

            const message = assistantMessage.value.trim();
            if (!message) {
                return;
            }

            const userBubble = document.createElement("div");
            userBubble.className = "chat-bubble chat-user";
            userBubble.textContent = message;
            chatLog.appendChild(userBubble);

            const formData = new FormData(assistantForm);
            const response = await fetch("' . route_url('api/assistant.php') . '", {
                method: "POST",
                body: formData,
                headers: {
                    "X-Requested-With": "XMLHttpRequest"
                }
            });

            const payload = await response.json();
            const aiBubble = document.createElement("div");
            aiBubble.className = "chat-bubble chat-ai";
            aiBubble.textContent = payload.reply || "I could not generate a response just now.";
            chatLog.appendChild(aiBubble);
            chatLog.scrollTop = chatLog.scrollHeight;

            assistantMessage.value = "";
        });
    </script>
';

render_footer($extraScripts);
?>