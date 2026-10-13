<?php
/** Estimate customer/product activity from distinct non-cancelled order dates. */
function customerReorderEstimate(array $dates, DateTimeImmutable $today, $horizon = 30)
{
    $days = array();
    foreach ($dates as $value) {
        $value = substr((string)$value, 0, 10);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if ($date && $date->format('Y-m-d') === $value && $value >= '2000-01-01' && $date <= $today) {
            $days[$value] = $date;
        }
    }
    ksort($days);
    $days = array_values($days);
    $count = count($days);
    $result = array('purchases' => $count, 'last' => $count ? $days[$count - 1]->format('Y-m-d') : null,
        'interval' => null, 'gapCount' => 0, 'next' => null, 'daysUntil' => null, 'confidence' => 'Insufficient history',
        'timing' => 'insufficient', 'rank' => 4);
    if ($count < 2) return $result;
    $gaps = array();
    for ($i = 1; $i < $count; $i++) $gaps[] = (int)$days[$i - 1]->diff($days[$i])->days;
    // Keep the full lookup history, but estimate the current cadence from the
    // last five gaps. More recent gaps receive more weight (1 through 5).
    $gaps = array_slice($gaps, -5);
    $n = count($gaps);
    $weighted = array();
    foreach ($gaps as $i => $gap) $weighted[] = array('gap' => $gap, 'weight' => $i + 1);
    usort($weighted, function ($a, $b) { return $a['gap'] <=> $b['gap']; });
    $totalWeight = $n * ($n + 1) / 2;
    $cumulative = 0;
    $median = $gaps[$n - 1];
    foreach ($weighted as $sample) {
        $cumulative += $sample['weight'];
        if ($cumulative >= $totalWeight / 2) { $median = $sample['gap']; break; }
    }
    $interval = max(1, (int)round($median));
    $variation = 0;
    foreach ($gaps as $i => $gap) $variation += ($i + 1) * abs($gap - $median);
    $variation = $variation / $totalWeight / $median;
    $next = $days[$count - 1]->modify('+' . $interval . ' days');
    $until = (int)$today->diff($next)->format('%r%a');
    $result['interval'] = $interval;
    $result['gapCount'] = $n;
    $result['next'] = $next->format('Y-m-d');
    $result['daysUntil'] = $until;
    $result['confidence'] = ($count >= 4 && $variation <= .25) ? 'High' : (($count >= 3 && $variation <= .5) ? 'Medium' : 'Low');
    if ($until < -$interval) {
        $result['timing'] = 'dormant';
        $result['rank'] = 3;
        $result['confidence'] = 'Low';
    } elseif ($until <= 0) {
        $result['timing'] = 'due';
        $result['rank'] = 0;
    } elseif ($until <= $horizon) {
        $result['timing'] = 'soon';
        $result['rank'] = 1;
    } else {
        $result['timing'] = 'later';
        $result['rank'] = 2;
    }
    return $result;
}

function customerReorderIntervalLabel($days)
{
    if ($days === null) return 'Not enough history';
    if ($days < 14) return 'About every ' . $days . ($days === 1 ? ' day' : ' days');
    $divisor = $days < 60 ? 7 : ($days < 730 ? 30.4375 : 365.25);
    $unit = $days < 60 ? 'week' : ($days < 730 ? 'month' : 'year');
    $value = rtrim(rtrim(number_format($days / $divisor, 1, '.', ''), '0'), '.');
    return 'About every ' . $value . ' ' . $unit . ((float)$value == 1 ? '' : 's');
}
