<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

if (is_authenticated()) {
    redirect_to(authenticated_home_path());
}

render_header('Home', [
    'page_class' => 'landing-page',
    'description' => 'Women Shield with reports, contacts, AI guidance, agents, and emergency tools.',
]);
?>

<section class="hero-grid">
    <div class="hero-copy panel hero-panel">
        <span class="eyebrow">Women Shield</span>
        <h1>One local safety platform for reporting, monitoring, emergency response, and AI-guided support.</h1>
        <p>
            Built for XAMPP with PHP and MySQL, this website combines login, emergency contacts, report CRUD,
            a safety map, agent-based risk analysis, and an admin dashboard in one deployable app.
        </p>
        <div class="button-row">
            <a class="button button-primary" href="<?= e(route_url('register.php')) ?>">Create Account</a>
        </div>
        <p class="panel-note">Use the top menu for User Login or Admin Login. Email setup is available only after admin login.</p>
        <div class="pill-row">
            <span class="pill">AI Categorization</span>
            <span class="pill">Danger Score AI</span>
            <span class="pill">Night Risk Agent</span>
            <span class="pill">Emergency Mode</span>
        </div>
    </div>

    <div class="hero-side stack">
        <article class="panel stat-panel">
            <h3>Included Modules</h3>
            <ul class="feature-list compact">
                <li>Login system and secure session handling</li>
                <li>Emergency contact management</li>
                <li>Incident report create, read, update, delete</li>
                <li>Safety map with danger-aware markers</li>
                <li>AI assistant, safety tips, and alert logic</li>
                <li>Admin moderation and community insights</li>
            </ul>
        </article>
        
    </div>
</section>

<section class="section-block">
    <div class="section-heading">
        <span class="eyebrow">Feature Set</span>
        <h2>Everything requested is included in the site flow</h2>
    </div>

    <div class="card-grid three-up">
        <article class="panel feature-card">
            <h3>Core Safety</h3>
            <p>Login, contacts, report CRUD, Emergency Mode, and a live view of alert history.</p>
        </article>
        <article class="panel feature-card">
            <h3>AI Layer</h3>
            <p>Heuristic AI categorization, fake report scoring, danger scoring, safety tips, and chat help.</p>
        </article>
        <article class="panel feature-card">
            <h3>Agent Layer</h3>
            <p>Monitoring Agent, Alert Agent, Night Risk Agent, Fake Report Agent, and admin insights.</p>
        </article>
    </div>
</section>
