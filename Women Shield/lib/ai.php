<?php

declare(strict_types=1);

function ai_category_catalog(): array
{
    return [
        'harassment' => ['harass', 'catcall', 'verbal', 'eve teasing', 'abuse', 'taunt'],
        'stalking' => ['follow', 'stalk', 'watching', 'shadowing', 'chased', 'pursue'],
        'transport' => ['bus', 'cab', 'taxi', 'rickshaw', 'ride', 'station', 'driver'],
        'assault' => ['attack', 'assault', 'hit', 'pushed', 'weapon', 'knife', 'gun', 'bleeding'],
        'domestic' => ['home', 'partner', 'husband', 'family', 'domestic', 'relative'],
        'medical' => ['fainted', 'injury', 'unconscious', 'medical', 'ambulance', 'panic'],
        'theft' => ['snatch', 'theft', 'stolen', 'robbed', 'robbery', 'pickpocket'],
        'suspicious' => ['suspicious', 'unknown', 'lurking', 'dark', 'isolated', 'unsafe'],
    ];
}

function infer_ai_category(string $title, string $description): array
{
    $text = mb_strtolower(trim($title . ' ' . $description));
    $bestCategory = 'suspicious';
    $bestMatches = 0;
    $bestKeywords = [];

    foreach (ai_category_catalog() as $category => $keywords) {
        $matches = [];

        foreach ($keywords as $keyword) {
            if ($keyword !== '' && str_contains($text, $keyword)) {
                $matches[] = $keyword;
            }
        }

        if (count($matches) > $bestMatches) {
            $bestCategory = $category;
            $bestMatches = count($matches);
            $bestKeywords = $matches;
        }
    }

    $confidence = min(0.98, 0.35 + ($bestMatches * 0.15));

    return [
        'category' => $bestCategory,
        'confidence' => round($confidence, 2),
        'keywords' => $bestKeywords,
    ];
}

function compute_fake_report_signal(array $report): array
{
    $title = trim((string) ($report['title'] ?? ''));
    $description = trim((string) ($report['description'] ?? ''));
    $location = trim((string) ($report['location_text'] ?? ''));

    $score = 0;
    $reasons = [];

    if (mb_strlen($description) < 18) {
        $score += 30;
        $reasons[] = 'Description is unusually short for a verified incident report.';
    }

    if ($title !== '' && mb_strtolower($title) === mb_strtolower($description)) {
        $score += 20;
        $reasons[] = 'Title and description are identical.';
    }

    if (preg_match('/(.)\\1{5,}/u', $description)) {
        $score += 15;
        $reasons[] = 'Repeated character patterns look suspicious.';
    }

    if (substr_count($description, '!') > 4 || substr_count($description, '?') > 4) {
        $score += 10;
        $reasons[] = 'Punctuation density is unusually high.';
    }

    if ($location === '') {
        $score += 10;
        $reasons[] = 'No location was supplied.';
    }

    if (!preg_match('/[a-zA-Z]/', $description)) {
        $score += 15;
        $reasons[] = 'Narrative text is missing meaningful words.';
    }

    return [
        'score' => min(100, $score),
        'flagged' => $score >= 60,
        'reasons' => $reasons,
    ];
}

function compute_night_risk(string $incidentTime, string $category): array
{
    $hour = (int) date('G', strtotime($incidentTime));
    $bonus = 0;
    $reason = 'Daytime conditions.';

    if ($hour >= 20 || $hour <= 4) {
        $bonus = 18;
        $reason = 'Late-night window raises isolation and response risk.';
    } elseif ($hour >= 5 && $hour <= 6) {
        $bonus = 8;
        $reason = 'Early-morning timing still carries limited foot traffic risk.';
    }

    if ($category === 'transport' && $bonus > 0) {
        $bonus += 5;
        $reason .= ' Public transport incidents at night are weighted higher.';
    }

    return [
        'score' => min(30, $bonus),
        'reason' => $reason,
    ];
}

function compute_danger_score(array $report, array $categoryInfo, array $nightRisk, array $fakeSignal): array
{
    $severity = (string) ($report['severity'] ?? 'medium');
    $location = mb_strtolower((string) ($report['location_text'] ?? ''));
    $description = mb_strtolower((string) ($report['description'] ?? ''));

    $severityBase = match ($severity) {
        'critical' => 82,
        'high' => 66,
        'medium' => 48,
        default => 30,
    };

    $categoryWeight = [
        'medical' => 22,
        'assault' => 20,
        'stalking' => 16,
        'domestic' => 14,
        'transport' => 12,
        'harassment' => 10,
        'theft' => 9,
        'suspicious' => 7,
    ];

    $score = $severityBase + ($categoryWeight[$categoryInfo['category']] ?? 0) + $nightRisk['score'];
    $reasons = ['Severity baseline applied.', 'Category risk profile applied.', $nightRisk['reason']];

    foreach (['isolated', 'alley', 'station', 'parking', 'bridge', 'dark', 'empty'] as $keyword) {
        if (str_contains($location, $keyword) || str_contains($description, $keyword)) {
            $score += 5;
            $reasons[] = 'Location context suggests reduced visibility or low support.';
            break;
        }
    }

    foreach (['weapon', 'knife', 'gun', 'bleeding', 'trapped', 'kidnap', 'forced'] as $keyword) {
        if (str_contains($description, $keyword)) {
            $score += 8;
            $reasons[] = 'Direct harm indicators were detected in the report narrative.';
            break;
        }
    }

    if ($fakeSignal['flagged']) {
        $score -= 10;
        $reasons[] = 'Confidence reduced because the fake report agent raised concerns.';
    }

    return [
        'score' => max(5, min(100, $score)),
        'reasons' => $reasons,
    ];
}

function safety_tip_library(): array
{
    return [
        'harassment' => [
            'Move toward a brighter, populated location and avoid staying isolated.',
            'Call or voice-message a trusted contact while keeping your phone visible.',
            'Document appearance, vehicle details, and direction of movement if safe.',
        ],
        'stalking' => [
            'Do not head directly home. Move toward a shop, police box, or busy point.',
            'Share your live location with an emergency contact immediately.',
            'Change direction or transport if you suspect someone is following you.',
        ],
        'transport' => [
            'Sit near the driver, conductor, or other passengers when possible.',
            'Share ride details and route screenshots with someone you trust.',
            'Exit at a busy stop if the driver or environment feels unsafe.',
        ],
        'assault' => [
            'Call emergency services now if there is immediate physical danger.',
            'Get to a safe public place first before gathering belongings.',
            'Seek medical support even if injuries look minor.',
        ],
        'domestic' => [
            'Move to a room with an exit and avoid kitchens or areas with sharp objects.',
            'Use a pre-agreed code word with trusted contacts if possible.',
            'Keep identification, medicine, and emergency cash ready if you need to leave fast.',
        ],
        'medical' => [
            'Contact ambulance or the nearest emergency medical response immediately.',
            'Keep breathing clear and stay seated or supported if dizzy or injured.',
            'Notify a trusted contact with your exact location.',
        ],
        'theft' => [
            'Move away from the scene and do not try to chase the suspect alone.',
            'Block payment methods and note device serial numbers if applicable.',
            'Record witness details and CCTV locations nearby.',
        ],
        'suspicious' => [
            'Stay alert, keep headphones off, and move toward brighter public areas.',
            'Let someone know where you are and when you expect to arrive.',
            'Trust your instincts and leave early if the situation feels wrong.',
        ],
    ];
}

function generate_safety_tips(array $report, string $category, int $dangerScore): array
{
    $tips = safety_tip_library()[$category] ?? safety_tip_library()['suspicious'];

    if ($dangerScore >= 85) {
        array_unshift($tips, 'Trigger Emergency Mode now and call local emergency support without waiting.');
    } elseif ($dangerScore >= 70) {
        array_unshift($tips, 'Notify your first two emergency contacts now and keep your phone unlocked.');
    }

    if (((int) date('G', strtotime((string) ($report['incident_time'] ?? 'now')))) >= 20) {
        $tips[] = 'Prefer verified transport or request accompaniment before moving again tonight.';
    }

    return array_values(array_unique($tips));
}

function build_report_ai_bundle(array $report): array
{
    $categoryInfo = infer_ai_category((string) ($report['title'] ?? ''), (string) ($report['description'] ?? ''));
    $fakeSignal = compute_fake_report_signal($report);
    $nightRisk = compute_night_risk((string) ($report['incident_time'] ?? date('Y-m-d H:i:s')), $categoryInfo['category']);
    $danger = compute_danger_score($report, $categoryInfo, $nightRisk, $fakeSignal);
    $tips = generate_safety_tips($report, $categoryInfo['category'], $danger['score']);

    return [
        'ai_category' => $categoryInfo['category'],
        'ai_confidence' => $categoryInfo['confidence'],
        'danger_score' => $danger['score'],
        'danger_reasons' => $danger['reasons'],
        'fake_score' => $fakeSignal['score'],
        'is_flagged_fake' => $fakeSignal['flagged'] ? 1 : 0,
        'fake_reasons' => $fakeSignal['reasons'],
        'night_risk' => $nightRisk['score'],
        'night_reason' => $nightRisk['reason'],
        'safety_tips' => $tips,
    ];
}

function normalize_assistant_text(string $value): string
{
    $value = mb_strtolower(trim($value));
    $value = preg_replace('/[^\p{L}\p{N}\s,.-]+/u', ' ', $value) ?? $value;
    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

    return trim($value, " \t\n\r\0\x0B,.-");
}

function prompt_looks_like_location_lookup(string $prompt): bool
{
    $normalized = normalize_assistant_text($prompt);

    if ($normalized === '') {
        return false;
    }

    if (preg_match('/\b(hi|hello|hey|thanks|thank you|dashboard|status|alert|alerts|assistant)\b/u', $normalized)) {
        return false;
    }

    $wordCount = preg_match_all('/[\p{L}\p{N}]+/u', $normalized, $matches);

    if ($wordCount === false || $wordCount === 0 || $wordCount > 8) {
        return false;
    }

    return preg_match('/^[\p{L}\p{N}\s,.-]+$/u', $normalized) === 1;
}

function find_reports_matching_location(string $prompt, array $reports): array
{
    $normalizedPrompt = normalize_assistant_text($prompt);

    if ($normalizedPrompt === '') {
        return [];
    }

    $matches = [];

    foreach ($reports as $report) {
        $location = trim((string) ($report['location_text'] ?? ''));
        $normalizedLocation = normalize_assistant_text($location);

        if ($normalizedLocation === '') {
            continue;
        }

        if (
            $normalizedPrompt === $normalizedLocation
            || str_contains($normalizedLocation, $normalizedPrompt)
            || str_contains($normalizedPrompt, $normalizedLocation)
        ) {
            $matches[] = $report;
        }
    }

    return $matches;
}

function generate_assistant_reply(string $message, array $context = []): string
{
    $prompt = mb_strtolower(trim($message));
    $contactCount = (int) ($context['contact_count'] ?? 0);
    $highRiskCount = (int) ($context['high_risk_reports'] ?? 0);
    $activeEmergency = !empty($context['active_emergency']);
    $reports = array_values(array_filter(
        is_array($context['reports'] ?? null) ? $context['reports'] : [],
        static fn ($report): bool => is_array($report)
    ));

    if (preg_match('/(help|emergency|unsafe now|danger now|panic)/', $prompt)) {
        return 'If you are in immediate danger, open Emergency Mode right now, call local emergency services, and move toward a bright crowded place. I can also guide you to notify your emergency contacts and prepare a short alert message.';
    }

    if (preg_match('/(contact|notify|family|friend)/', $prompt)) {
        return $contactCount > 0
            ? "You currently have {$contactCount} emergency contact(s) saved. Open the contacts page to update priority order, then use Emergency Mode to prepare a fast share message."
            : 'You do not have emergency contacts saved yet. Add at least two trusted contacts so the alert agent can prioritize outreach during high-risk incidents.';
    }

    if (preg_match('/(report|incident|complaint|categor)/', $prompt)) {
        return 'Create a report with a clear title, exact location, and what happened in sequence. The AI layer will categorize it, estimate the danger score, flag suspicious submissions, and suggest next steps.';
    }

    if (preg_match('/(night|route|travel|transport|map)/', $prompt)) {
        return 'Use the Safety Map before travelling, especially after dark. Night Risk Agent increases caution for isolated zones and transport-related incidents, so prefer well-lit roads and verified rides.';
    }

    $matchingReports = find_reports_matching_location($message, $reports);

    if ($matchingReports !== []) {
        $matchedReport = $matchingReports[0];
        $location = trim((string) ($matchedReport['location_text'] ?? 'this location'));
        $status = str_replace('_', ' ', (string) ($matchedReport['status'] ?? 'new'));
        $dangerScore = (int) ($matchedReport['danger_score'] ?? 0);
        $matchCount = count($matchingReports);

        return "I found {$matchCount} saved report(s) for {$location}. Latest status: {$status}. Danger score: {$dangerScore}. If you are going there now, stay in well-lit public areas and keep a trusted contact updated.";
    }

    if ($reports !== [] && prompt_looks_like_location_lookup($message)) {
        $knownLocations = array_values(array_unique(array_values(array_filter(
            array_map(
                static fn (array $report): string => trim((string) ($report['location_text'] ?? '')),
                $reports
            ),
            static fn (string $location): bool => $location !== ''
        ))));

        $latestKnownLocation = $knownLocations[0] ?? '';

        if ($latestKnownLocation !== '') {
            return "I could not find any reports for {$message}.Your recorded report location is {$latestKnownLocation}.Stay alert and follow general safety precautions while traveling. If you notice anything concerning, please create a new report from the Reports page.";
        }
    }

    if ($activeEmergency) {
        return 'Emergency Mode is currently active on your account. Keep location sharing on, stay connected to a trusted person, and avoid moving into quieter streets unless you are heading directly to a verified safe point.';
    }

    if ($highRiskCount > 0) {
        return "You have {$highRiskCount} high-risk report(s) on record. I suggest reviewing their status, checking recent alerts, and refreshing your emergency contact priorities.";
    }

    return "I can assist with emergency planning, report filing, safety map guidance, and night travel precautions. How can I help you right now?";
}

function build_monitoring_agent_summary(array $reports): array
{
    if ($reports === []) {
        return [
            'headline' => 'Monitoring agent is ready.',
            'summary' => 'No reports are available yet, so the monitoring queue is clear.',
            'hotspot' => 'No hotspot detected',
            'watch_count' => 0,
            'flagged_count' => 0,
        ];
    }

    $locationCounts = [];
    $watchCount = 0;
    $flaggedCount = 0;

    foreach ($reports as $report) {
        $location = trim((string) ($report['location_text'] ?? ''));
        $location = $location !== '' ? $location : 'Unknown';
        $locationCounts[$location] = ($locationCounts[$location] ?? 0) + 1;

        if ((int) ($report['danger_score'] ?? 0) >= 70 && ($report['status'] ?? '') !== 'resolved') {
            $watchCount++;
        }

        if (!empty($report['is_flagged_fake'])) {
            $flaggedCount++;
        }
    }

    arsort($locationCounts);
    $hotspot = array_key_first($locationCounts) ?: 'Unknown';

    return [
        'headline' => $watchCount > 0 ? 'Monitoring agent sees active risk.' : 'Monitoring agent sees stable conditions.',
        'summary' => "Hotspot activity is currently concentrated around {$hotspot}. {$watchCount} case(s) need close attention and {$flaggedCount} report(s) need trust review.",
        'hotspot' => $hotspot,
        'watch_count' => $watchCount,
        'flagged_count' => $flaggedCount,
    ];
}

function build_alert_agent_actions(array $report, array $contacts): array
{
    $dangerScore = (int) ($report['danger_score'] ?? 0);
    $contactNames = array_slice(array_map(fn ($contact) => $contact['name'], $contacts), 0, 3);
    $message = 'Stay alert and keep monitoring the situation.';
    $level = 'Observe';

    if ($dangerScore >= 85) {
        $level = 'Immediate SOS';
        $message = 'High danger detected. Contact emergency services now, share live location, and notify your top emergency contacts immediately.';
    } elseif ($dangerScore >= 70) {
        $level = 'Rapid Response';
        $message = 'Elevated danger detected. Notify trusted contacts, move to a safer area, and prepare for emergency escalation.';
    } elseif ($dangerScore >= 50) {
        $level = 'Protective Action';
        $message = 'Moderate risk detected. Stay in public view, document details, and keep a contact on standby.';
    }

    return [
        'level' => $level,
        'message' => $message,
        'contacts' => $contactNames,
    ];
}

function build_admin_insights(array $reports): array
{
    if ($reports === []) {
        return [
            'peak_hour' => 'N/A',
            'top_category' => 'N/A',
            'resolution_rate' => 0,
            'community_summary' => 'No reports available for insight generation yet.',
        ];
    }

    $hours = [];
    $categories = [];
    $resolved = 0;

    foreach ($reports as $report) {
        $hour = date('H:00', strtotime((string) ($report['incident_time'] ?? 'now')));
        $hours[$hour] = ($hours[$hour] ?? 0) + 1;

        $category = (string) ($report['ai_category'] ?? 'uncategorized');
        $categories[$category] = ($categories[$category] ?? 0) + 1;

        if (($report['status'] ?? '') === 'resolved') {
            $resolved++;
        }
    }

    arsort($hours);
    arsort($categories);

    $resolutionRate = (int) round(($resolved / max(count($reports), 1)) * 100);
    $peakHour = array_key_first($hours) ?: 'N/A';
    $topCategory = array_key_first($categories) ?: 'N/A';

    return [
        'peak_hour' => $peakHour,
        'top_category' => ucfirst((string) $topCategory),
        'resolution_rate' => $resolutionRate,
        'community_summary' => "{$peakHour} is the strongest risk window, with {$topCategory} reports appearing most often. Resolution rate currently sits at {$resolutionRate}%.",
    ];
}