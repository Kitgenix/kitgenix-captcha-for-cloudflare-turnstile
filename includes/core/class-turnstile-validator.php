<?php
namespace KitgenixCaptchaForCloudflareTurnstile\Core;

defined('ABSPATH') || exit;

use function wp_verify_nonce;
use function get_option;
use function sanitize_text_field;
use function wp_remote_post;
use function is_wp_error;
use function wp_remote_retrieve_body;
use function wp_unslash;
use function apply_filters;
use function esc_html;
use function __;

class Turnstile_Validator {
    private const RECENT_EVENT_LOG_OPTION = 'kitgenix_turnstile_recent_event_log';
    private const RECENT_EVENT_LOG_LIMIT  = 50;

    /** WP option holding the configured log auto-delete window (in days). */
    private const LOG_RETENTION_OPTION  = 'kitgenix_turnstile_log_retention_days';
    private const LOG_RETENTION_DEFAULT = 30;
    private const LOG_RETENTION_ALLOWED = [7, 14, 30];

    /**
     * Daily, per-integration challenge performance aggregates (counts, siteverify
     * response times, Cloudflare error-code tallies). Holds no tokens, IPs, user
     * agents or submitted form values – only integration keys, dates and numbers.
     */
    private const PERFORMANCE_OPTION = 'kitgenix_turnstile_performance';

    /** Days of performance history kept (twice the longest report period, for comparisons). */
    private const PERFORMANCE_RETENTION_DAYS = 60;

    /** Report periods offered on the Log tab. */
    public const PERFORMANCE_PERIODS = [7, 14, 30];

    /** Upper bounds (ms) of the response-time histogram buckets; anything slower lands in 'gt'. */
    private const PERFORMANCE_LATENCY_BUCKETS = [250, 500, 1000, 2000, 5000];

    /** A siteverify round trip slower than this (ms) is reported as slow. Must be one of the bucket bounds. */
    public const PERFORMANCE_SLOW_MS = 2000;

    /** Max distinct error codes tallied per integration per day (bounds option size). */
    private const PERFORMANCE_MAX_CODES = 10;

    /** Store a small diagnostic snapshot of the last siteverify outcome. */
    private static function record_last_verify(bool $ok, array $codes = []): void {
        $payload = [
            'time'       => time(), // stored as epoch for consistency; display with gmdate() when needed
            'success'    => $ok ? 1 : 0,
            'codes'      => array_values(array_map('strval', $codes)),
            'latency_ms' => self::$last_latency_ms,
        ];
        \update_option('kitgenix_turnstile_last_verify', $payload, false);
    }

    /** @var array<string,bool> In-request cache of tokens already counted for metrics. */
    private static $counted_tokens = [];

    /** @var array<string,bool> In-request cache of recent diagnostic events already stored. */
    private static $logged_events = [];

    /**
     * Max diagnostic writes (record_metrics + record_recent_event) allowed per
     * integration+outcome per rolling minute. Protects wp_options from unbounded
     * write load during a bot flood; see flood_guard_allow().
     */
    private const FLOOD_GUARD_MAX_PER_MINUTE           = 20;
    private const FLOOD_GUARD_HONEYPOT_MAX_PER_MINUTE  = 1;

    /**
     * Return normalized stored metrics.
     *
     * @return array<string,mixed>
     */
    private static function get_metrics_store(): array {
        $metrics = get_option('kitgenix_captcha_for_cloudflare_turnstile_metrics', []);
        if ( ! is_array($metrics) ) {
            $metrics = [];
        }

        return self::normalize_metrics($metrics);
    }

    /**
     * @param array<string,mixed> $metrics
     * @return array<string,mixed>
     */
    private static function normalize_metrics(array $metrics): array {
        $normalized = [
            'checks_total'   => max(0, (int) ($metrics['checks_total'] ?? 0)),
            'checks_passed'  => max(0, (int) ($metrics['checks_passed'] ?? 0)),
            'checks_failed'  => max(0, (int) ($metrics['checks_failed'] ?? 0)),
            'checks_retries' => max(0, (int) ($metrics['checks_retries'] ?? 0)),
            'integrations'   => [],
        ];

        $integrations = isset($metrics['integrations']) && is_array($metrics['integrations'])
            ? $metrics['integrations']
            : [];

        foreach ($integrations as $integration => $integration_metrics) {
            $key = self::normalize_integration((string) $integration);
            if ( ! is_array($integration_metrics) ) {
                $integration_metrics = [];
            }

            $normalized['integrations'][ $key ] = self::normalize_integration_metrics($integration_metrics);
        }

        return $normalized;
    }

    /**
     * @param array<string,mixed> $metrics
     * @return array<string,mixed>
     */
    private static function normalize_integration_metrics(array $metrics): array {
        $last_outcome = (string) ($metrics['last_outcome'] ?? 'failure');
        if ( ! in_array($last_outcome, ['success', 'failure'], true) ) {
            $last_outcome = 'failure';
        }

        $last_codes = isset($metrics['last_codes']) && is_array($metrics['last_codes'])
            ? array_values(array_map('strval', $metrics['last_codes']))
            : [];

        return [
            'checks_total'   => max(0, (int) ($metrics['checks_total'] ?? 0)),
            'checks_passed'  => max(0, (int) ($metrics['checks_passed'] ?? 0)),
            'checks_failed'  => max(0, (int) ($metrics['checks_failed'] ?? 0)),
            'checks_retries' => max(0, (int) ($metrics['checks_retries'] ?? 0)),
            'last_checked'   => max(0, (int) ($metrics['last_checked'] ?? 0)),
            'last_outcome'   => $last_outcome,
            'last_codes'     => $last_codes,
        ];
    }

    /**
     * Persist privacy-safe metrics about Turnstile checks.
     *
     * @param string $token
     * @param bool   $ok
     */
    private static function record_metrics(string $token, bool $ok, string $integration = 'general', array $codes = []): void {
        $integration = self::normalize_integration($integration);
        $codes       = array_values(array_unique(array_map('strval', $codes)));
        $cache_key   = $token !== ''
            ? 'token:' . $token
            : 'event:' . $integration . '|' . ($ok ? '1' : '0') . '|' . implode(',', $codes);

        if (array_key_exists($cache_key, self::$counted_tokens)) {
            return;
        }
        self::$counted_tokens[$cache_key] = true;

        // Retry-classified codes (stale nonce, widget not loaded yet, replayed
        // token) are recoverable friction, not a security signal – they count
        // toward checks_retries only, never checks_failed, so "Blocked
        // attempts" / the failure-spike alert reflect genuine blocks rather
        // than routine cache/session hiccups. See is_retry_codes().
        $is_retry = ! $ok && self::is_retry_codes($codes);

        $metrics = self::get_metrics_store();
        $metrics['checks_total'] = max(0, (int) $metrics['checks_total'] + 1);
        if ($ok) {
            $metrics['checks_passed'] = max(0, (int) $metrics['checks_passed'] + 1);
        } elseif ($is_retry) {
            $metrics['checks_retries'] = max(0, (int) $metrics['checks_retries'] + 1);
        } else {
            $metrics['checks_failed'] = max(0, (int) $metrics['checks_failed'] + 1);
        }

        $integration_metrics = isset($metrics['integrations'][ $integration ]) && is_array($metrics['integrations'][ $integration ])
            ? self::normalize_integration_metrics($metrics['integrations'][ $integration ])
            : self::normalize_integration_metrics([]);

        $integration_metrics['checks_total'] = max(0, (int) $integration_metrics['checks_total'] + 1);
        if ($ok) {
            $integration_metrics['checks_passed'] = max(0, (int) $integration_metrics['checks_passed'] + 1);
        } elseif ($is_retry) {
            $integration_metrics['checks_retries'] = max(0, (int) $integration_metrics['checks_retries'] + 1);
        } else {
            $integration_metrics['checks_failed'] = max(0, (int) $integration_metrics['checks_failed'] + 1);
        }

        $integration_metrics['last_checked'] = time();
        $integration_metrics['last_outcome'] = $ok ? 'success' : 'failure';
        $integration_metrics['last_codes']   = $codes;

        $metrics['integrations'][ $integration ] = $integration_metrics;

        \update_option('kitgenix_captcha_for_cloudflare_turnstile_metrics', $metrics, false);

        self::record_performance($integration, $ok, $is_retry, $codes);
    }

    /**
     * Rate-limit a bucket of diagnostic writes to at most $max_per_minute per rolling
     * minute. Uses wp_cache_incr() (no DB write at all) when an external object cache
     * is present, otherwise a capped transient (at most $max_per_minute DB writes per
     * minute per bucket, instead of one write per request) so a bot flood can't turn
     * every failed check into an options write. See T2 in ROADMAP.md.
     */
    private static function flood_guard_allow(string $bucket, int $max_per_minute): bool {
        $minute = (int) floor(time() / 60);
        // Must start with 'kitgenix_turnstile_' – uninstall.php's transient cleanup
        // matches on that prefix, so a different prefix here would leave orphaned
        // wp_options rows behind after uninstall.
        $key    = 'kitgenix_turnstile_flood_' . md5($bucket) . '_' . $minute;

        if (function_exists('wp_using_ext_object_cache') && \wp_using_ext_object_cache() && function_exists('wp_cache_add')) {
            $group = 'kitgenix_turnstile';
            if (\wp_cache_add($key, 1, $group, 90)) {
                return true; // first write this minute for this bucket
            }
            $count = (int) \wp_cache_incr($key, 1, $group);
            return $count <= $max_per_minute;
        }

        $count = (int) \get_transient($key);
        if ($count >= $max_per_minute) {
            return false;
        }
        \set_transient($key, $count + 1, 90);
        return true;
    }

    /**
     * Single entry point for persisting a verification outcome (last-verify snapshot +
     * metrics/performance + recent-event log). Centralizing it here is what lets a bot
     * flood be rate-limited in one place: failed checks beyond the per-minute cap for
     * their integration are skipped entirely (no options write at all), and a tripped
     * honeypot is capped even tighter since a flood trips it identically every time.
     * Successful checks are never rate-limited.
     */
    private static function record_outcome(string $integration, bool $ok, array $codes = [], string $token = ''): void {
        if ( ! $ok ) {
            $bucket = in_array('honeypot_tripped', $codes, true) ? 'honeypot:' . $integration : 'fail:' . $integration;
            $cap    = in_array('honeypot_tripped', $codes, true)
                ? (int) apply_filters('kitgenix_turnstile_flood_guard_honeypot_max_per_minute', self::FLOOD_GUARD_HONEYPOT_MAX_PER_MINUTE)
                : (int) apply_filters('kitgenix_turnstile_flood_guard_max_per_minute', self::FLOOD_GUARD_MAX_PER_MINUTE);

            if ( ! self::flood_guard_allow($bucket, max(1, $cap)) ) {
                return;
            }
        }

        self::record_last_verify($ok, $codes);
        self::record_metrics($token, $ok, $integration, $codes);
        self::record_recent_event($integration, $ok, $codes, $token);
    }

    /* =========================
     * Challenge performance reporting
     * ========================= */

    /**
     * Add one check to today's performance aggregates. Called from record_metrics(),
     * so it inherits that method's once-per-token/event de-duplication.
     *
     * self::$last_latency_ms is only non-zero when verify_with_site() actually
     * reached out to Cloudflare this request, so honeypot/nonce/missing-token
     * short-circuits and failsafe passes count as checks without skewing the
     * response-time figures.
     *
     * @param string   $integration Normalized integration key.
     * @param string[] $codes
     */
    private static function record_performance(string $integration, bool $ok, bool $is_retry, array $codes): void {
        $store = self::get_performance_store();
        $day   = self::performance_day_key(time());

        if ( ! isset($store[ $day ]) ) {
            $store[ $day ] = [ 'buckets' => [], 'integrations' => [] ];
        }

        $row = $store[ $day ]['integrations'][ $integration ] ?? [];
        $row = self::normalize_performance_row(is_array($row) ? $row : []);

        $row['checks']++;
        if ($ok) {
            $row['passed']++;
        } elseif ($is_retry) {
            $row['retries']++;
        } else {
            $row['failed']++;
        }

        if ( ! $ok ) {
            foreach ($codes as $code) {
                $code = substr(\sanitize_key((string) $code), 0, 64);
                if ($code === '') {
                    continue;
                }
                if ( ! isset($row['codes'][ $code ]) && count($row['codes']) >= self::PERFORMANCE_MAX_CODES ) {
                    continue;
                }
                $row['codes'][ $code ] = (int) ($row['codes'][ $code ] ?? 0) + 1;
            }
        }

        $latency = max(0, (int) self::$last_latency_ms);
        if ($latency > 0) {
            $row['lat_sum'] += $latency;
            $row['lat_n']++;
            $row['lat_max'] = max($row['lat_max'], $latency);

            $bucket = self::performance_bucket_key($latency);
            $store[ $day ]['buckets'][ $bucket ] = (int) ($store[ $day ]['buckets'][ $bucket ] ?? 0) + 1;
        }

        $store[ $day ]['integrations'][ $integration ] = $row;

        \update_option(self::PERFORMANCE_OPTION, self::filter_performance_by_retention($store), false);
    }

    /**
     * @return array<string,array<string,mixed>> Day key (Y-m-d, site timezone) => day aggregates.
     */
    private static function get_performance_store(): array {
        $store = get_option(self::PERFORMANCE_OPTION, []);
        if ( ! is_array($store) ) {
            return [];
        }

        $clean = [];
        foreach ($store as $day => $data) {
            if ( ! is_string($day) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) || ! is_array($data) ) {
                continue;
            }
            $clean[ $day ] = [
                'buckets'      => isset($data['buckets']) && is_array($data['buckets']) ? array_map('intval', $data['buckets']) : [],
                'integrations' => isset($data['integrations']) && is_array($data['integrations']) ? $data['integrations'] : [],
            ];
        }

        return $clean;
    }

    /**
     * @param array<string,mixed> $row
     * @return array{checks:int,passed:int,failed:int,retries:int,lat_sum:int,lat_n:int,lat_max:int,codes:array<string,int>}
     */
    private static function normalize_performance_row(array $row): array {
        $codes = isset($row['codes']) && is_array($row['codes']) ? $row['codes'] : [];

        return [
            'checks'  => max(0, (int) ($row['checks'] ?? 0)),
            'passed'  => max(0, (int) ($row['passed'] ?? 0)),
            'failed'  => max(0, (int) ($row['failed'] ?? 0)),
            'retries' => max(0, (int) ($row['retries'] ?? 0)),
            'lat_sum' => max(0, (int) ($row['lat_sum'] ?? 0)),
            'lat_n'   => max(0, (int) ($row['lat_n'] ?? 0)),
            'lat_max' => max(0, (int) ($row['lat_max'] ?? 0)),
            'codes'   => array_map('intval', $codes),
        ];
    }

    private static function performance_day_key(int $timestamp): string {
        return function_exists('wp_date') ? (string) \wp_date('Y-m-d', $timestamp) : gmdate('Y-m-d', $timestamp);
    }

    private static function performance_bucket_key(int $latency_ms): string {
        foreach (self::PERFORMANCE_LATENCY_BUCKETS as $bound) {
            if ($latency_ms <= $bound) {
                return 'le' . $bound;
            }
        }
        return 'gt';
    }

    /**
     * @param array<string,array<string,mixed>> $store
     * @return array<string,array<string,mixed>>
     */
    private static function filter_performance_by_retention(array $store): array {
        $cutoff = self::performance_day_key(time() - ((self::PERFORMANCE_RETENTION_DAYS - 1) * DAY_IN_SECONDS));

        foreach (array_keys($store) as $day) {
            if ( strcmp((string) $day, $cutoff) < 0 ) {
                unset($store[ $day ]);
            }
        }
        ksort($store);

        return $store;
    }

    /** Drop performance days older than the retention window (daily cron, see prune_event_log()). */
    public static function prune_performance(): void {
        $store  = self::get_performance_store();
        $pruned = self::filter_performance_by_retention($store);
        if ( count($pruned) !== count($store) ) {
            \update_option(self::PERFORMANCE_OPTION, $pruned, false);
        }
    }

    /** Delete all stored performance aggregates (manual "Reset performance data" action). */
    public static function clear_performance(): void {
        \delete_option(self::PERFORMANCE_OPTION);
    }

    /**
     * Build the challenge performance report for the last $days days (today included),
     * compared against the $days days before that.
     *
     * @return array{
     *     days:int,
     *     summary:array<string,mixed>,
     *     daily:array<int,array<string,mixed>>,
     *     integrations:array<int,array<string,mixed>>,
     *     buckets:array<string,int>
     * }
     */
    public static function get_performance_report(int $days = 7): array {
        $days  = in_array($days, self::PERFORMANCE_PERIODS, true) ? $days : self::PERFORMANCE_PERIODS[0];
        $store = self::get_performance_store();
        $now   = time();

        $current_days  = [];
        $previous_days = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $current_days[] = self::performance_day_key($now - ($i * DAY_IN_SECONDS));
        }
        for ($i = (2 * $days) - 1; $i >= $days; $i--) {
            $previous_days[] = self::performance_day_key($now - ($i * DAY_IN_SECONDS));
        }

        $buckets = [];
        foreach (self::PERFORMANCE_LATENCY_BUCKETS as $bound) {
            $buckets[ 'le' . $bound ] = 0;
        }
        $buckets['gt'] = 0;

        $totals   = self::normalize_performance_row([]);
        $per_form = [];
        $daily    = [];
        $slow     = 0;

        foreach ($current_days as $day) {
            $day_data  = $store[ $day ] ?? [ 'buckets' => [], 'integrations' => [] ];
            $day_total = self::normalize_performance_row([]);

            foreach ((array) $day_data['buckets'] as $bucket => $count) {
                if (isset($buckets[ $bucket ])) {
                    $buckets[ $bucket ] += max(0, (int) $count);
                }
            }

            foreach ((array) $day_data['integrations'] as $integration => $row) {
                $integration = self::normalize_integration((string) $integration);
                $row         = self::normalize_performance_row(is_array($row) ? $row : []);

                if ( ! isset($per_form[ $integration ]) ) {
                    $per_form[ $integration ] = self::normalize_performance_row([]);
                }
                self::merge_performance_row($per_form[ $integration ], $row);

                self::merge_performance_row($day_total, $row);
            }

            self::merge_performance_row($totals, $day_total);

            $daily[] = [
                'date'         => $day,
                'checks'       => $day_total['checks'],
                'failed'       => $day_total['failed'],
                'retries'      => $day_total['retries'],
                'failure_rate' => self::calculate_rate($day_total['failed'] + $day_total['retries'], $day_total['checks']),
                'avg_ms'       => $day_total['lat_n'] > 0 ? (int) round($day_total['lat_sum'] / $day_total['lat_n']) : 0,
                'max_ms'       => $day_total['lat_max'],
                'samples'      => $day_total['lat_n'],
            ];
        }

        foreach ($buckets as $bucket => $count) {
            if ('gt' === $bucket || (int) substr($bucket, 2) > self::PERFORMANCE_SLOW_MS) {
                $slow += $count;
            }
        }

        $previous       = self::normalize_performance_row([]);
        $previous_forms = [];
        foreach ($previous_days as $day) {
            foreach ((array) ($store[ $day ]['integrations'] ?? []) as $integration => $row) {
                $integration = self::normalize_integration((string) $integration);
                $row         = self::normalize_performance_row(is_array($row) ? $row : []);
                if ( ! isset($previous_forms[ $integration ]) ) {
                    $previous_forms[ $integration ] = self::normalize_performance_row([]);
                }
                self::merge_performance_row($previous_forms[ $integration ], $row);
                self::merge_performance_row($previous, $row);
            }
        }

        $integrations = [];
        foreach ($per_form as $integration => $row) {
            if ($row['checks'] <= 0) {
                continue;
            }

            $prev         = $previous_forms[ $integration ] ?? self::normalize_performance_row([]);
            $failure_rate = self::calculate_rate($row['failed'] + $row['retries'], $row['checks']);
            $prev_rate    = $prev['checks'] > 0 ? self::calculate_rate($prev['failed'] + $prev['retries'], $prev['checks']) : null;

            arsort($row['codes']);

            $integrations[] = [
                'integration'       => $integration,
                'label'             => self::get_integration_label($integration),
                'checks'            => $row['checks'],
                'passed'            => $row['passed'],
                'failed'            => $row['failed'],
                'retries'           => $row['retries'],
                'failure_rate'      => $failure_rate,
                'prev_checks'       => $prev['checks'],
                'prev_failure_rate' => $prev_rate,
                'trend'             => self::performance_trend($failure_rate, $prev_rate),
                'avg_ms'            => $row['lat_n'] > 0 ? (int) round($row['lat_sum'] / $row['lat_n']) : 0,
                'max_ms'            => $row['lat_max'],
                'samples'           => $row['lat_n'],
                'top_codes'         => array_slice($row['codes'], 0, 3, true),
            ];
        }

        usort($integrations, static function (array $left, array $right): int {
            if ($left['failure_rate'] !== $right['failure_rate']) {
                return $right['failure_rate'] <=> $left['failure_rate'];
            }
            return $right['checks'] <=> $left['checks'];
        });

        $failure_rate = self::calculate_rate($totals['failed'] + $totals['retries'], $totals['checks']);
        $prev_rate    = $previous['checks'] > 0 ? self::calculate_rate($previous['failed'] + $previous['retries'], $previous['checks']) : null;

        return [
            'days'    => $days,
            'summary' => [
                'checks'            => $totals['checks'],
                'passed'            => $totals['passed'],
                'failed'            => $totals['failed'],
                'retries'           => $totals['retries'],
                'samples'           => $totals['lat_n'],
                'avg_ms'            => $totals['lat_n'] > 0 ? (int) round($totals['lat_sum'] / $totals['lat_n']) : 0,
                'max_ms'            => $totals['lat_max'],
                'p95'               => self::performance_percentile_bucket($buckets, 0.95),
                'slow'              => $slow,
                'slow_rate'         => self::calculate_rate($slow, $totals['lat_n']),
                'failure_rate'      => $failure_rate,
                'prev_checks'       => $previous['checks'],
                'prev_failure_rate' => $prev_rate,
                'trend'             => self::performance_trend($failure_rate, $prev_rate),
            ],
            'daily'        => $daily,
            'integrations' => $integrations,
            'buckets'      => $buckets,
        ];
    }

    /**
     * @param array<string,mixed> $into
     * @param array<string,mixed> $row Normalized performance row.
     */
    private static function merge_performance_row(array &$into, array $row): void {
        foreach (['checks', 'passed', 'failed', 'retries', 'lat_sum', 'lat_n'] as $field) {
            $into[ $field ] += (int) $row[ $field ];
        }
        $into['lat_max'] = max((int) $into['lat_max'], (int) $row['lat_max']);
        foreach ((array) $row['codes'] as $code => $count) {
            $into['codes'][ $code ] = (int) ($into['codes'][ $code ] ?? 0) + (int) $count;
        }
    }

    /**
     * Failure-rate direction versus the previous period. A change of under five
     * percentage points is treated as noise ('steady').
     *
     * @return string 'worse' | 'better' | 'steady' | 'new'
     */
    private static function performance_trend(float $current, ?float $previous): string {
        if (null === $previous) {
            return 'new';
        }
        $delta = $current - $previous;
        if ($delta >= 5.0) {
            return 'worse';
        }
        if ($delta <= -5.0) {
            return 'better';
        }
        return 'steady';
    }

    /**
     * Resolve which histogram bucket a percentile falls in. Only bucket counts are
     * stored (never individual timings), so the result is an upper bound.
     *
     * @param array<string,int> $buckets
     * @return array{upper_ms:int,over:bool}|null upper_ms is the bucket's bound; over=true means slower than the largest bound.
     */
    private static function performance_percentile_bucket(array $buckets, float $percentile): ?array {
        $total = array_sum($buckets);
        if ($total <= 0) {
            return null;
        }

        $target  = (int) ceil($total * $percentile);
        $running = 0;
        foreach ($buckets as $bucket => $count) {
            $running += $count;
            if ($running >= $target) {
                return 'gt' === $bucket
                    ? [ 'upper_ms' => (int) max(self::PERFORMANCE_LATENCY_BUCKETS), 'over' => true ]
                    : [ 'upper_ms' => (int) substr($bucket, 2), 'over' => false ];
            }
        }

        return null;
    }

    /** @var array<string,bool> In-request cache of verified tokens to avoid double verification */
    private static $verified_tokens = [];

    /** @var array Last raw response from siteverify */
    private static $last_response = [];

    /** @var array Last error codes array from siteverify (if any) */
    private static $last_error_codes = [];

    /** @var string Last error message (if any) */
    private static $last_error_msg = '';

    /**
     * @var int Round-trip latency (ms) of the most recent siteverify HTTP call.
     * Reset to 0 at the start of each public validation call and only set when
     * verify_with_site() actually reaches out to Cloudflare, so 0 reliably means
     * "no siteverify call was made this request" (e.g. honeypot/nonce/token-missing
     * short-circuits), not "Cloudflare responded instantly".
     */
    private static $last_latency_ms = 0;

    /**
     * @var string[] Non-blocking notice codes (e.g. 'action_mismatch', 'hostname_mismatch')
     * found on the most recent *successful* siteverify response. See evaluate_action_hostname().
     */
    private static $last_notice_codes = [];

    /* =========================
     * Dev mode (warn-only)
     * ========================= */

    /**
     * Whether a failed check should be treated as warn-only (allow the action through
     * while still logging/recording the failure) rather than a hard block.
     *
     * True when the site-wide "Development Mode (Warn-only)" setting is on, OR when the
     * given integration is individually listed in Settings → test_mode_integrations (Test
     * Mode per Integration) – letting an admin test one integration without putting the
     * whole site's Turnstile protection into warn-only mode.
     */
    private static function dev_mode_enabled(string $integration = ''): bool {
        $settings = get_option('kitgenix_captcha_for_cloudflare_turnstile_settings', []);
        if ( ! empty( $settings['dev_mode_warn_only'] ) ) {
            return true;
        }

        if ( '' === $integration ) {
            return false;
        }

        $test_mode_integrations = isset( $settings['test_mode_integrations'] ) && is_array( $settings['test_mode_integrations'] )
            ? array_map( 'strval', $settings['test_mode_integrations'] )
            : [];

        return in_array( self::normalize_integration( $integration ), $test_mode_integrations, true );
    }

    private static function log_dev(string $what, array $data = []): void {
        if ( self::dev_mode_enabled() && defined('WP_DEBUG') && WP_DEBUG ) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log('[Kitgenix Turnstile DEV] ' . $what . ' :: ' . \wp_json_encode($data));
        }
        \do_action('kitgenix_turnstile_dev_log', $what, $data);
    }

    /**
     * Integrations where a stale/missing WP nonce is kept as a hard requirement even
     * on a page-cached site, because they gate an authenticated action or account
     * security (login, registration, password reset, account changes). Everything
     * else (comments, checkout, newsletter/contact forms…) is a "public form" where
     * the roadmap's T3 fix lets the Turnstile token stand on its own instead. See
     * is_valid_submission()'s nonce block and page_caching_detected().
     *
     * @return bool
     */
    private static function nonce_is_soft_checked(string $integration): bool {
        $critical = (array) apply_filters('kitgenix_turnstile_nonce_critical_integrations', [
            'wordpress-login',
            'wordpress-register',
            'wordpress-lostpassword',
            'wordpress-resetpassword',
            'woocommerce-login',
            'woocommerce-account',
            'memberpress',
            'memberpress-signup',
            'paid-memberships-pro',
            'paid-memberships-pro-checkout',
            'ultimate-member',
            'buddypress',
            'setup-check',
        ]);

        return ! in_array(self::normalize_integration($integration), array_map([ self::class, 'normalize_integration' ], $critical), true);
    }

    /**
     * Whether this request is likely served from a page cache (so a WP nonce embedded
     * in the cached HTML can be stale for a real visitor even though nothing is wrong).
     * Deliberately simple/conservative: only the `WP_CACHE` constant most page-cache
     * plugins define, so non-cached sites see no behavior change at all.
     */
    private static function page_caching_detected(): bool {
        return (bool) apply_filters('kitgenix_turnstile_page_caching_detected', defined('WP_CACHE') && WP_CACHE);
    }

    /**
     * Let a submission through under the Cloudflare outage failsafe, without ever
     * contacting Cloudflare or requiring a token. Recorded distinctly (not as a
     * normal pass) so admins can tell "Cloudflare verified this" apart from
     * "verification was bypassed because Cloudflare was unreachable" in analytics
     * and the recent-events log – see Cloudflare_Health::failsafe_active() and
     * get_active_alerts(), which also surfaces this as a live, current-state alert
     * (not just a historical log entry) while it's happening.
     */
    private static function allow_via_failsafe(string $integration): bool {
        self::$last_error_codes = ['cloudflare_failsafe_bypass'];
        self::$last_error_msg   = '';
        self::log_dev('cloudflare_failsafe_bypass', ['integration' => $integration]);
        self::record_metrics('', true, $integration, self::$last_error_codes);
        self::record_recent_event($integration, true, self::$last_error_codes);
        return true;
    }

    /* =========================
     * Replay protection
     * ========================= */

    private static function flag_replay_frontend(): void {
        if ( headers_sent() ) { return; }
        $path   = defined('COOKIEPATH')    ? COOKIEPATH    : '/';
        $domain = defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : '';
        setcookie('kitgenix_captcha_for_cloudflare_turnstile_ts_replay', '1', time() + 120, $path, $domain, \is_ssl(), false);
    }

    private static function replay_enabled(): bool {
        $settings = get_option('kitgenix_captcha_for_cloudflare_turnstile_settings', []);
        return array_key_exists('replay_protection', $settings) ? !empty($settings['replay_protection']) : true;
    }

    /**
     * Whether our own replay-protection transient is worth storing. Without an external
     * object cache, set_transient()/get_transient() fall back to a wp_options row per
     * successful check, which duplicates Cloudflare's own single-use enforcement
     * (a reused token already fails siteverify with timeout-or-duplicate) for a DB
     * write that mostly just restates what Cloudflare already guarantees. With an
     * object cache present, the same transient costs nothing extra, so it stays on –
     * it still catches a reused token faster (no round trip to Cloudflare needed) and
     * covers the edge case of a token replayed after its own TTL but within ours.
     * See T5 in ROADMAP.md.
     */
    private static function replay_storage_available(): bool {
        return (bool) apply_filters(
            'kitgenix_turnstile_replay_storage_available',
            function_exists('wp_using_ext_object_cache') && \wp_using_ext_object_cache()
        );
    }

    private static function replay_ttl(): int {
        $ttl = (int) apply_filters('kitgenix_turnstile_replay_ttl', 10 * MINUTE_IN_SECONDS);
        return $ttl > 0 ? $ttl : 10 * MINUTE_IN_SECONDS;
    }

    private static function token_hash(string $token): string {
        return hash('sha256', $token . '|' . \wp_salt('auth'));
    }

    private static function replay_key(string $hash): string {
        return 'kitgenix_captcha_for_cloudflare_turnstile_ts_' . $hash;
    }

    private static function is_replay(string $token): bool {
        if ( ! self::replay_enabled() || ! self::replay_storage_available() ) {
            return false;
        }
        $h = self::token_hash($token);
        return (bool) \get_transient( self::replay_key($h) );
    }

    private static function mark_used(string $token): void {
        if ( ! self::replay_enabled() || ! self::replay_storage_available() ) {
            return;
        }
        $h   = self::token_hash($token);
        $key = self::replay_key($h);
        \set_transient( $key, 1, self::replay_ttl() );
    }

    /* =========================
     * Honeypot (zero-JS fallback)
     * ========================= */

    /**
     * The field name rendered by Script_Handler::render_honeypot_field(). Real visitors
     * never see or fill it (CSS-hidden, tabindex="-1"); a filled value means whatever
     * submitted the form skipped rendering/JS entirely and blindly filled every field.
     */
    public static function honeypot_field_name(): string {
        return 'kitgenix_captcha_for_cloudflare_turnstile_hp_field';
    }

    private static function honeypot_enabled(): bool {
        $settings = get_option('kitgenix_captcha_for_cloudflare_turnstile_settings', []);
        return !empty($settings['honeypot_enabled']);
    }

    private static function is_honeypot_tripped(): bool {
        if ( ! self::honeypot_enabled() ) {
            return false;
        }

        $field = self::honeypot_field_name();
        if ( ! isset( $_POST[ $field ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- honeypot is a pre-nonce bot filter, not a security boundary by itself.
            return false;
        }

        $value = sanitize_text_field( wp_unslash( $_POST[ $field ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
        return $value !== '';
    }

    /* =========================
     * Public API
     * ========================= */

    /**
     * Validate Turnstile challenge (nonce + token from request).
     *
     * @param bool $require_nonce
     * @param string $integration Integration context
     * @param bool $check_replay Whether to check for replayed tokens (skip for checkout forms that may retry)
     * @return bool
     */
    public static function is_valid_submission($require_nonce = true, string $integration = 'general', bool $check_replay = true): bool {
        self::$last_error_codes = [];
        self::$last_error_msg   = '';
        self::$last_response    = [];
        self::$last_latency_ms  = 0;

        // Outage failsafe – independently, server-side confirmed Cloudflare is
        // unreachable, and the admin has opted in to not let that permanently
        // block every protected form on the site. See Cloudflare_Health.
        if ( Cloudflare_Health::failsafe_active() ) {
            return self::allow_via_failsafe($integration);
        }

        // Honeypot (zero-JS fallback) – check first so a tripped trap skips the
        // Cloudflare API call entirely, not just the rest of this method's checks.
        if ( self::is_honeypot_tripped() ) {
            self::$last_error_codes[] = 'honeypot_tripped';
            self::$last_error_msg     = __('Submission blocked.', 'kitgenix-captcha-for-cloudflare-turnstile');
            self::log_dev('honeypot_tripped');
            self::record_outcome($integration, false, self::$last_error_codes);
            return self::dev_mode_enabled($integration);
        }

        // Nonce. The Turnstile token itself already proves freshness (short-lived) and
        // single use (Cloudflare + our own replay check), so a stale/missing WP nonce
        // alone is not treated as fatal on a page-caching site for integrations that
        // aren't login/account-security sensitive – see nonce_is_soft_checked() and T3
        // in ROADMAP.md. Authenticated/security-sensitive integrations always keep the
        // hard nonce requirement, on cached pages or not.
        $nonce_soft = $require_nonce && self::nonce_is_soft_checked($integration) && self::page_caching_detected();
        $nonce_ok   = true;
        if ( $require_nonce ) {
            $nonce = isset($_POST['kitgenix_captcha_for_cloudflare_turnstile_nonce'])
                ? sanitize_text_field( wp_unslash( $_POST['kitgenix_captcha_for_cloudflare_turnstile_nonce'] ) )
                : '';
            $nonce_ok = '' !== $nonce && wp_verify_nonce( $nonce, 'kitgenix_captcha_for_cloudflare_turnstile_action' );

            if ( ! $nonce_ok ) {
                if ( ! $nonce_soft ) {
                    self::$last_error_codes[] = 'nonce_invalid';
                    self::$last_error_msg     = __('Security check failed. Please refresh and try again.', 'kitgenix-captcha-for-cloudflare-turnstile');
                    self::log_dev('nonce_invalid');
                    self::record_outcome($integration, false, self::$last_error_codes);
                    return self::dev_mode_enabled($integration);
                }

                // Soft path: page caching detected on a non-sensitive integration – log
                // and fall through to let the Turnstile token stand on its own.
                self::log_dev('nonce_invalid_soft_bypass', ['integration' => $integration]);
            }
        }

        // Token - get directly from POST since nonce was already verified above
        // FIXED: Use direct POST access after nonce verification to avoid double-check issues
        $response = self::get_token_from_request();
        if ( $response === '' ) {
            self::$last_error_codes[] = 'token_missing';
            self::$last_error_msg     = __('Please complete the Turnstile challenge.', 'kitgenix-captcha-for-cloudflare-turnstile');
            self::log_dev('token_missing');
            self::record_outcome($integration, false, self::$last_error_codes);
            return self::dev_mode_enabled($integration);
        }

        // Cloudflare verify (memoized)
        $ok = self::verify_with_site($response, $integration);

        if ( ! $ok ) {
            if (self::$last_error_msg === '') {
                // Map codes to friendly message
                self::$last_error_msg = self::message_from_error_codes(self::$last_error_codes, '');
            }
            self::log_dev('verification_failed', [
                'codes'   => self::$last_error_codes,
                'message' => self::$last_error_msg,
                'resp'    => self::$last_response,
            ]);
            self::record_outcome($integration, false, self::$last_error_codes, $response);
            return self::dev_mode_enabled($integration);
        }

        // SUCCESS → replay check (skip for checkout forms to allow retries)
        if ( $check_replay && self::is_replay( $response ) ) {
            self::$last_error_codes[] = 'replay_detected';
            self::$last_error_msg     = __('Verification expired. Please try again.', 'kitgenix-captcha-for-cloudflare-turnstile');
            self::log_dev('replay_detected');
            self::flag_replay_frontend();
            self::record_outcome($integration, false, self::$last_error_codes, $response);
            return self::dev_mode_enabled($integration);
        }

        // Only mark token as used if we're checking replay protection
        // For checkout (check_replay=false), token will be marked after successful order creation
        if ( $check_replay ) {
            self::mark_used( $response );
        }

        $notice_codes = $nonce_soft && ! $nonce_ok ? array_merge(self::$last_notice_codes, ['nonce_invalid_soft_bypass']) : self::$last_notice_codes;
        self::record_outcome($integration, true, $notice_codes, $response);
        return true;
    }

    /**
     * Validate a token directly (for integrations that only have the token).
     *
     * On failure, returns dev_mode_enabled() – matching is_valid_submission() – so the
     * site-wide Developer/Warn-only Mode setting applies uniformly no matter which entry
     * point an integration uses. In normal enforcement mode (the default) this is
     * unchanged: a `false` return is authoritative and the caller MUST block.
     *
     * @param string $token
     * @param string $integration Integration context
     * @param bool $check_replay Whether to check for replayed tokens
     * @return bool
     */
    public static function validate_token($token, string $integration = 'general', bool $check_replay = true): bool {
        self::$last_error_codes = [];
        self::$last_error_msg   = '';
        self::$last_response    = [];
        self::$last_latency_ms  = 0;

        if ( Cloudflare_Health::failsafe_active() ) {
            return self::allow_via_failsafe($integration);
        }

        if ( self::is_honeypot_tripped() ) {
            self::$last_error_codes = ['honeypot_tripped'];
            self::$last_error_msg   = __('Submission blocked.', 'kitgenix-captcha-for-cloudflare-turnstile');
            self::log_dev('honeypot_tripped');
            self::record_outcome($integration, false, self::$last_error_codes);
            return self::dev_mode_enabled($integration);
        }

        $token = sanitize_text_field( (string) $token );
        if ($token === '') {
            self::$last_error_codes = ['missing-input-response'];
            self::$last_error_msg   = __('Please complete the Turnstile challenge.', 'kitgenix-captcha-for-cloudflare-turnstile');
            self::record_outcome($integration, false, self::$last_error_codes);
            return self::dev_mode_enabled($integration);
        }

        $ok = self::verify_with_site($token, $integration);
        if ( ! $ok ) {
            if (self::$last_error_msg === '') {
                self::$last_error_msg = self::message_from_error_codes(self::$last_error_codes, '');
            }
            self::record_outcome($integration, false, self::$last_error_codes, $token);
            return self::dev_mode_enabled($integration);
        }

        if ( $check_replay && self::is_replay( $token ) ) {
            self::$last_error_codes[] = 'replay_detected';
            self::$last_error_msg     = __('Verification expired. Please try again.', 'kitgenix-captcha-for-cloudflare-turnstile');
            self::log_dev('replay_detected');
            self::flag_replay_frontend();
            self::record_outcome($integration, false, self::$last_error_codes, $token);
            return self::dev_mode_enabled($integration);
        }

        // Only mark token as used if we're checking replay protection
        if ( $check_replay ) {
            self::mark_used( $token );
        }

        self::record_outcome($integration, true, self::$last_notice_codes, $token);
        return true;
    }

    /**
     * Mark a token from the current request as used (for post-submission actions like order creation).
     * This prevents the same token from being used again after a successful submission.
     *
     * @return void
     */
    public static function mark_submission_token_used(): void {
        $token = self::get_token_from_request();
        if ($token !== '') {
            self::mark_used($token);
        }
    }

    /**
     * Mark a specific token as used (for direct token validation in APIs/blocks).
     * This prevents the same token from being used again after a successful submission.
     *
     * @param string $token The Turnstile token to mark as used
     * @return void
     */
    public static function mark_token_used(string $token): void {
        $token = sanitize_text_field( (string) $token );
        if ($token !== '') {
            self::mark_used($token);
        }
    }

    /**
     * Perform an end-to-end setup verification using a real Turnstile token from the admin test widget.
     *
     * @param string $token Turnstile token generated by the settings page test widget.
     * @return array<string,mixed>
     */
    public static function perform_setup_check(string $token): array {
        self::$last_error_codes = [];
        self::$last_error_msg   = '';
        self::$last_response    = [];
        self::$last_latency_ms  = 0;

        $token = sanitize_text_field( (string) $token );
        if ($token === '') {
            self::$last_error_codes = ['missing-input-response'];
            self::$last_error_msg   = __('Complete the Turnstile challenge to verify your setup.', 'kitgenix-captcha-for-cloudflare-turnstile');
            self::record_last_verify(false, self::$last_error_codes);
            self::record_recent_event('setup-check', false, self::$last_error_codes);

            return [
                'success'          => false,
                'message'          => self::$last_error_msg,
                'codes'            => self::$last_error_codes,
                'response'         => self::$last_response,
                'outbound_http_ok' => false,
                'secret_valid'     => false,
            ];
        }

        $ok = self::verify_with_site($token, 'setup-check');
        if ( ! $ok ) {
            if (self::$last_error_msg === '') {
                self::$last_error_msg = self::message_from_error_codes(self::$last_error_codes, 'setup_check');
            }

            self::record_last_verify(false, self::$last_error_codes);
            self::record_recent_event('setup-check', false, self::$last_error_codes, $token);

            return [
                'success'          => false,
                'message'          => self::$last_error_msg,
                'codes'            => self::$last_error_codes,
                'response'         => self::$last_response,
                'outbound_http_ok' => ! \in_array('http_error', self::$last_error_codes, true),
                'secret_valid'     => ! \array_intersect(['missing-input-secret', 'invalid-input-secret', 'sitekey-secret-mismatch'], self::$last_error_codes),
            ];
        }

        self::record_last_verify(true, []);
    self::record_recent_event('setup-check', true, [], $token);

        return [
            'success'          => true,
            'message'          => __('Server-side verification succeeded. Login-sensitive protections can now load with the current key pair.', 'kitgenix-captcha-for-cloudflare-turnstile'),
            'codes'            => [],
            'response'         => self::$last_response,
            'outbound_http_ok' => true,
            'secret_valid'     => true,
        ];
    }

    /**
     * Extract a Turnstile token from a REST request.
     *
     * Checks the canonical header first, then one or more extensions namespaces,
     * then falls back to the standard request param.
     *
     * @param \WP_REST_Request $request
     * @param array            $extension_namespaces
     * @return string
     */
    public static function get_rest_request_token($request, array $extension_namespaces = []): string {
        if ( ! ( $request instanceof \WP_REST_Request ) ) {
            return '';
        }

        $header_token = (string) $request->get_header('X-Turnstile-Token');
        if ( $header_token !== '' ) {
            return sanitize_text_field($header_token);
        }

        $params     = (array) $request->get_json_params();
        $extensions = isset($params['extensions']) && is_array($params['extensions'])
            ? $params['extensions']
            : [];

        foreach ( $extension_namespaces as $namespace ) {
            if ( isset($extensions[ $namespace ]['token']) ) {
                return sanitize_text_field( (string) $extensions[ $namespace ]['token'] );
            }
        }

        $request_token = (string) $request->get_param('cf-turnstile-response');
        if ( $request_token !== '' ) {
            return sanitize_text_field($request_token);
        }

        return '';
    }

    /**
     * Get the error message for Turnstile validation (shared utility).
     *
     * @param string $context Optional context for filter hook (e.g., 'wpforms')
     * @return string
     */
    public static function get_error_message($context = ''): string {
        $settings = get_option('kitgenix_captcha_for_cloudflare_turnstile_settings', []);

        // Replay gets a specific message
        if ( in_array('replay_detected', self::$last_error_codes, true) ) {
            $replay = __('Your verification expired. Please complete the Turnstile challenge.', 'kitgenix-captcha-for-cloudflare-turnstile');
            $replay = apply_filters('kitgenix_turnstile_replay_message', $replay, $context);
            $message = $replay;
        } else {
            // Map codes → message; fall back to generic
            $mapped  = self::message_from_error_codes(self::$last_error_codes, $context);
            $generic = __('Please complete the Turnstile challenge.', 'kitgenix-captcha-for-cloudflare-turnstile');
            $message = $mapped ?: $generic;
        }

        // Admin custom message overrides
        if (!empty($settings['error_message'])) {
            $message = $settings['error_message'];
        }

        if ($context) {
            $filter  = 'kitgenix_captcha_for_cloudflare_turnstile_' . $context . '_turnstile_error_message';
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- dynamic, context-specific filter name
            $message = apply_filters($filter, $message);
        }

        return esc_html($message);
    }

    /* ======= Introspection ======= */

    public static function get_last_error_codes(): array {
        return self::$last_error_codes;
    }

    public static function get_last_error_message(): string {
        return self::$last_error_msg ?: self::get_error_message();
    }

    public static function get_last_response(): array {
        return self::$last_response;
    }

    /**
     * Round-trip latency (ms) of the most recent siteverify call, or 0 when no
     * call was made this request (e.g. honeypot/nonce/token-missing short-circuit).
     */
    public static function get_last_latency_ms(): int {
        return self::$last_latency_ms;
    }

    /**
     * Return normalized metrics for totals and per-integration analytics.
     *
     * @return array<string,mixed>
     */
    public static function get_metrics_snapshot(): array {
        return self::get_metrics_store();
    }

    /**
     * Return per-integration analytics rows sorted by activity.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function get_integration_analytics(): array {
        $metrics = self::get_metrics_store();
        $rows    = [];

        foreach ((array) $metrics['integrations'] as $integration => $integration_metrics) {
            if ( ! is_array($integration_metrics) ) {
                continue;
            }

            $normalized = self::normalize_integration_metrics($integration_metrics);
            if ( (int) $normalized['checks_total'] <= 0 ) {
                continue;
            }

            $rows[] = [
                'integration'   => $integration,
                'label'         => self::get_integration_label($integration),
                'checks_total'  => (int) $normalized['checks_total'],
                'checks_passed' => (int) $normalized['checks_passed'],
                'checks_failed' => (int) $normalized['checks_failed'],
                'checks_retries'=> (int) $normalized['checks_retries'],
                'friction_rate' => self::calculate_rate((int) $normalized['checks_retries'], (int) $normalized['checks_total']),
                'last_checked'  => (int) $normalized['last_checked'],
                'last_outcome'  => (string) $normalized['last_outcome'],
                'last_codes'    => (array) $normalized['last_codes'],
            ];
        }

        usort($rows, static function (array $left, array $right): int {
            if ((int) $left['checks_total'] !== (int) $right['checks_total']) {
                return (int) $right['checks_total'] <=> (int) $left['checks_total'];
            }

            if ((int) $left['checks_failed'] !== (int) $right['checks_failed']) {
                return (int) $right['checks_failed'] <=> (int) $left['checks_failed'];
            }

            return strcmp((string) $left['label'], (string) $right['label']);
        });

        return $rows;
    }

    /**
     * Return active operational alerts derived from recent verification activity.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function get_active_alerts(): array {
        $alerts = [];

        // Live state, not a historical log scan: this reflects whether the
        // failsafe is bypassing verification right now, for as long as
        // Cloudflare_Health's cached probe keeps reporting Cloudflare down.
        if ( Cloudflare_Health::failsafe_active() ) {
            $alerts[] = [
                'key'      => 'cloudflare-failsafe-active',
                'severity' => 'error',
                'title'    => __( 'Cloudflare outage failsafe is active', 'kitgenix-captcha-for-cloudflare-turnstile' ),
                'message'  => __( 'Cloudflare Turnstile appears unreachable, so this plugin is currently letting protected forms submit WITHOUT verification instead of blocking every visitor. This will clear automatically once Cloudflare is reachable again. Disable "Cloudflare outage failsafe" in Settings if you would rather block submissions during an outage.', 'kitgenix-captcha-for-cloudflare-turnstile' ),
            ];
        }

        $window_seconds = self::get_alert_window_seconds();
        $cutoff         = time() - $window_seconds;
        $window_events  = [];

        foreach ( self::get_recent_event_log() as $event ) {
            $time        = isset( $event['time'] ) ? (int) $event['time'] : 0;
            $integration = (string) ( $event['integration'] ?? 'general' );

            if ( $time < $cutoff || 'setup-check' === $integration ) {
                continue;
            }

            $window_events[] = $event;
        }

        if ( empty( $window_events ) ) {
            return $alerts;
        }

        $http_error_alert = self::build_http_error_alert( $window_events, $window_seconds );
        if ( null !== $http_error_alert ) {
            $alerts[] = $http_error_alert;
        }

        $failure_spike_alert = self::build_failure_spike_alert( $window_events, $window_seconds );
        if ( null !== $failure_spike_alert ) {
            $alerts[] = $failure_spike_alert;
        }

        return $alerts;
    }

    /**
     * Canonical map of integration key => human-readable label. Shared by
     * get_integration_label() (display) and get_all_integration_keys() (settings
     * validation for e.g. per-integration Test Mode) so the two can never drift apart.
     *
     * @return array<string,string>
     */
    private static function get_integration_labels_map(): array {
        return [
            'general'                       => __('General', 'kitgenix-captcha-for-cloudflare-turnstile'),
            'setup-check'                   => __('Setup verification', 'kitgenix-captcha-for-cloudflare-turnstile'),
            'bbpress'                       => __('bbPress', 'kitgenix-captcha-for-cloudflare-turnstile'),
            'buddypress'                    => __('BuddyPress', 'kitgenix-captcha-for-cloudflare-turnstile'),
            'cf7'                           => __('Contact Form 7', 'kitgenix-captcha-for-cloudflare-turnstile'),
            'edd'                           => __('Easy Digital Downloads', 'kitgenix-captcha-for-cloudflare-turnstile'),
            'edd-account'                   => __('EDD account', 'kitgenix-captcha-for-cloudflare-turnstile'),
            'edd-checkout'                  => __('EDD checkout', 'kitgenix-captcha-for-cloudflare-turnstile'),
            'elementor'                     => __('Elementor Forms', 'kitgenix-captcha-for-cloudflare-turnstile'),
            'fluentforms'                   => __('Fluent Forms', 'kitgenix-captcha-for-cloudflare-turnstile'),
            'formidableforms'               => __('Formidable Forms', 'kitgenix-captcha-for-cloudflare-turnstile'),
            'forminator'                    => __('Forminator', 'kitgenix-captcha-for-cloudflare-turnstile'),
            'gravityforms'                  => __('Gravity Forms', 'kitgenix-captcha-for-cloudflare-turnstile'),
            'jetformbuilder'                => __('JetFormBuilder', 'kitgenix-captcha-for-cloudflare-turnstile'),
            'jetpackforms'                  => __('Jetpack Forms', 'kitgenix-captcha-for-cloudflare-turnstile'),
            'kadenceforms'                  => __('Kadence Forms', 'kitgenix-captcha-for-cloudflare-turnstile'),
            'mailpoet'                      => __('MailPoet', 'kitgenix-captcha-for-cloudflare-turnstile'),
            'ninjaforms'                    => __('Ninja Forms', 'kitgenix-captcha-for-cloudflare-turnstile'),
            'memberpress'                   => __('MemberPress', 'kitgenix-captcha-for-cloudflare-turnstile'),
            'memberpress-signup'            => __('MemberPress signup', 'kitgenix-captcha-for-cloudflare-turnstile'),
            'paid-memberships-pro'          => __('Paid Memberships Pro', 'kitgenix-captcha-for-cloudflare-turnstile'),
            'paid-memberships-pro-checkout' => __('Paid Memberships Pro checkout', 'kitgenix-captcha-for-cloudflare-turnstile'),
            'ultimate-member'               => __('Ultimate Member', 'kitgenix-captcha-for-cloudflare-turnstile'),
            'woocommerce'                   => __('WooCommerce', 'kitgenix-captcha-for-cloudflare-turnstile'),
            'woocommerce-account'           => __('WooCommerce My Account', 'kitgenix-captcha-for-cloudflare-turnstile'),
            'woocommerce-checkout'          => __('WooCommerce checkout', 'kitgenix-captcha-for-cloudflare-turnstile'),
            'woocommerce-login'             => __('WooCommerce login', 'kitgenix-captcha-for-cloudflare-turnstile'),
            'woocommerce-pay-order'         => __('WooCommerce pay for order', 'kitgenix-captcha-for-cloudflare-turnstile'),
            'woocommerce-review'            => __('WooCommerce reviews', 'kitgenix-captcha-for-cloudflare-turnstile'),
            'woocommerce-blocks-checkout'   => __('WooCommerce Blocks checkout', 'kitgenix-captcha-for-cloudflare-turnstile'),
            'wordpress-comment'             => __('WordPress comments', 'kitgenix-captcha-for-cloudflare-turnstile'),
            'wordpress-login'               => __('WordPress login', 'kitgenix-captcha-for-cloudflare-turnstile'),
            'wordpress-lostpassword'        => __('WordPress lost password', 'kitgenix-captcha-for-cloudflare-turnstile'),
            'wordpress-register'            => __('WordPress registration', 'kitgenix-captcha-for-cloudflare-turnstile'),
            'wordpress-resetpassword'       => __('WordPress reset password', 'kitgenix-captcha-for-cloudflare-turnstile'),
            'wp-core'                       => __('WordPress core', 'kitgenix-captcha-for-cloudflare-turnstile'),
            'wpdiscuz'                      => __('wpDiscuz', 'kitgenix-captcha-for-cloudflare-turnstile'),
            'wpforms'                       => __('WPForms', 'kitgenix-captcha-for-cloudflare-turnstile'),
        ];
    }

    /**
     * Return every canonical integration key eligible for per-integration Test Mode
     * (excludes 'general' and 'setup-check', which are not real protected actions).
     *
     * @return string[]
     */
    public static function get_all_integration_keys(): array {
        return array_values( array_diff( array_keys( self::get_integration_labels_map() ), [ 'general', 'setup-check' ] ) );
    }

    /**
     * Return a human-friendly integration label for analytics and exports.
     */
    public static function get_integration_label(string $integration): string {
        $integration = self::normalize_integration($integration);
        $labels      = self::get_integration_labels_map();

        if (isset($labels[ $integration ])) {
            return (string) $labels[ $integration ];
        }

        return ucwords(str_replace('-', ' ', $integration));
    }

    /**
     * @param array<int,array<string,mixed>> $events
     * @return array<string,mixed>|null
     */
    private static function build_failure_spike_alert(array $events, int $window_seconds): ?array {
        $sample_size = count( $events );
        if ( $sample_size <= 0 ) {
            return null;
        }

        // Retry-classified events (stale nonce, widget not loaded yet, replayed
        // token) are excluded here too – a site with heavy page caching can
        // rack up dozens of routine nonce hiccups with zero actual attack
        // activity, and that shouldn't page an admin with a false "failure
        // spike" warning. See is_retry_codes() / record_metrics().
        $failed_events = array_values(
            array_filter(
                $events,
                static function ( array $event ): bool {
                    if ( (string) ( $event['outcome'] ?? 'failure' ) !== 'failure' ) {
                        return false;
                    }
                    $codes = isset( $event['codes'] ) && is_array( $event['codes'] ) ? $event['codes'] : [];
                    return ! self::is_retry_codes( $codes );
                }
            )
        );

        $failed_count = count( $failed_events );
        $min_failures = self::get_alert_failure_spike_min_failures();
        if ( $failed_count < $min_failures || $sample_size < max( 5, $min_failures ) ) {
            return null;
        }

        $failure_rate     = self::calculate_rate( $failed_count, $sample_size );
        $min_failure_rate = self::get_alert_failure_spike_failure_rate();
        if ( $failure_rate < $min_failure_rate ) {
            return null;
        }

        $affected_flow = self::summarize_alert_integration( $failed_events );
        $window_label  = self::get_alert_window_label( $window_seconds );

        return [
            'key'               => 'verification-failure-spike',
            'severity'          => 'warning',
            'title'             => __( 'Verification failure spike detected', 'kitgenix-captcha-for-cloudflare-turnstile' ),
            'message'           => sprintf(
                /* translators: 1: failed checks count, 2: sample size, 3: failure rate percentage, 4: rolling time window, 5: integration label */
                __( '%1$s of the last %2$s Turnstile checks failed in the last %4$s (%3$s%% failure rate). %5$s is currently the most affected flow.', 'kitgenix-captcha-for-cloudflare-turnstile' ),
                \number_format_i18n( $failed_count ),
                \number_format_i18n( $sample_size ),
                \number_format_i18n( $failure_rate, 1 ),
                $window_label,
                $affected_flow['label']
            ),
            'event_count'       => $failed_count,
            'sample_size'       => $sample_size,
            'failure_rate'      => $failure_rate,
            'window_seconds'    => $window_seconds,
            'integration'       => $affected_flow['integration'],
            'integration_label' => $affected_flow['label'],
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $events
     * @return array<string,mixed>|null
     */
    private static function build_http_error_alert(array $events, int $window_seconds): ?array {
        $http_error_events = array_values(
            array_filter(
                $events,
                static function ( array $event ): bool {
                    $codes = isset( $event['codes'] ) && is_array( $event['codes'] )
                        ? array_values( array_map( 'strval', $event['codes'] ) )
                        : [];

                    return in_array( 'http_error', $codes, true );
                }
            )
        );

        $http_error_count = count( $http_error_events );
        if ( $http_error_count < self::get_alert_http_error_min_failures() ) {
            return null;
        }

        $affected_flow = self::summarize_alert_integration( $http_error_events );
        $window_label  = self::get_alert_window_label( $window_seconds );

        return [
            'key'               => 'blocked-siteverify-requests',
            'severity'          => 'error',
            'title'             => __( 'Blocked verification requests detected', 'kitgenix-captcha-for-cloudflare-turnstile' ),
            'message'           => sprintf(
                /* translators: 1: failed siteverify request count, 2: rolling time window, 3: integration label */
                __( 'Cloudflare siteverify requests failed %1$s times in the last %2$s. %3$s is the most affected flow, which usually means outbound HTTPS requests are being blocked, filtered, or timing out.', 'kitgenix-captcha-for-cloudflare-turnstile' ),
                \number_format_i18n( $http_error_count ),
                $window_label,
                $affected_flow['label']
            ),
            'event_count'       => $http_error_count,
            'window_seconds'    => $window_seconds,
            'integration'       => $affected_flow['integration'],
            'integration_label' => $affected_flow['label'],
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $events
     * @return array{integration:string,label:string,count:int}
     */
    private static function summarize_alert_integration(array $events): array {
        $counts = [];

        foreach ( $events as $event ) {
            $integration = self::normalize_integration( (string) ( $event['integration'] ?? 'general' ) );
            if ( ! isset( $counts[ $integration ] ) ) {
                $counts[ $integration ] = 0;
            }

            $counts[ $integration ]++;
        }

        if ( empty( $counts ) ) {
            return [
                'integration' => 'general',
                'label'       => self::get_integration_label( 'general' ),
                'count'       => 0,
            ];
        }

        arsort( $counts );
        $integration = (string) key( $counts );

        return [
            'integration' => $integration,
            'label'       => self::get_integration_label( $integration ),
            'count'       => (int) current( $counts ),
        ];
    }

    private static function get_alert_window_seconds(): int {
        $window = (int) apply_filters( 'kitgenix_turnstile_alert_window_seconds', 15 * MINUTE_IN_SECONDS );
        return $window > 0 ? $window : 15 * MINUTE_IN_SECONDS;
    }

    private static function get_alert_failure_spike_min_failures(): int {
        $threshold = (int) apply_filters( 'kitgenix_turnstile_alert_failure_spike_min_failures', 5 );
        return max( 1, $threshold );
    }

    private static function get_alert_failure_spike_failure_rate(): float {
        $threshold = (float) apply_filters( 'kitgenix_turnstile_alert_failure_spike_failure_rate', 60.0 );
        if ( $threshold < 0 ) {
            return 0.0;
        }

        if ( $threshold > 100 ) {
            return 100.0;
        }

        return $threshold;
    }

    private static function get_alert_http_error_min_failures(): int {
        $threshold = (int) apply_filters( 'kitgenix_turnstile_alert_http_error_min_failures', 3 );
        return max( 1, $threshold );
    }

    private static function get_alert_window_label(int $window_seconds): string {
        $window_seconds = $window_seconds > 0 ? $window_seconds : self::get_alert_window_seconds();

        if ( \function_exists( 'human_time_diff' ) ) {
            return (string) \human_time_diff( time() - $window_seconds, time() );
        }

        return (string) \number_format_i18n( max( 1, (int) ceil( $window_seconds / MINUTE_IN_SECONDS ) ) ) . ' minutes';
    }

    /**
     * Determine whether a set of error codes represents a retry-causing event.
     */
    public static function is_retry_codes(array $codes): bool {
        $codes = array_values(array_unique(array_map('strval', $codes)));
        if (empty($codes)) {
            return false;
        }

        $retry_codes = [
            'missing-input-response',
            'invalid-input-response',
            'timeout-or-duplicate',
            'token_missing',
            'replay_detected',
            // Stale WP nonce – common on cached pages or after a session/login
            // change, not a security signal. Grouped with the other recoverable
            // "friction" codes so it stops inflating checks_failed / the
            // failure-spike alert with routine cache hiccups.
            'nonce_invalid',
        ];

        foreach ($codes as $code) {
            if (in_array($code, $retry_codes, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * How many days recent-event-log entries are kept before being auto-deleted.
     * Falls back to the default when the stored value isn't one of the allowed choices.
     */
    public static function get_log_retention_days(): int {
        $days = (int) get_option(self::LOG_RETENTION_OPTION, self::LOG_RETENTION_DEFAULT);
        return in_array($days, self::LOG_RETENTION_ALLOWED, true) ? $days : self::LOG_RETENTION_DEFAULT;
    }

    /**
     * Persist the log auto-delete window, clamped to an allowed value.
     *
     * @param int $days Requested retention window in days.
     * @return int The value actually saved.
     */
    public static function save_log_retention_days(int $days): int {
        $days = in_array($days, self::LOG_RETENTION_ALLOWED, true) ? $days : self::LOG_RETENTION_DEFAULT;
        \update_option(self::LOG_RETENTION_OPTION, $days, false);
        return $days;
    }

    /** Delete every stored recent-event-log entry (manual "Clear log" action). */
    public static function clear_event_log(): void {
        \delete_option(self::RECENT_EVENT_LOG_OPTION);
    }

    /**
     * Drop event-log entries older than the configured retention window.
     *
     * @param array<int,array<string,mixed>> $log
     * @return array<int,array<string,mixed>>
     */
    private static function filter_log_by_retention(array $log): array {
        $cutoff = time() - (self::get_log_retention_days() * DAY_IN_SECONDS);

        return array_values(array_filter($log, static function ($entry) use ($cutoff): bool {
            return is_array($entry) && (int) ($entry['time'] ?? 0) >= $cutoff;
        }));
    }

    /**
     * Prune event-log entries older than the configured retention window. Runs on a
     * daily WP-Cron schedule (see Settings_UI::maybe_schedule_log_pruning()) so the
     * log still decays on a quiet site, in addition to the age check already applied
     * on every write in record_recent_event().
     */
    public static function prune_event_log(): void {
        $log = get_option(self::RECENT_EVENT_LOG_OPTION, []);
        if ( ! is_array($log) || empty($log) ) {
            return;
        }

        $pruned = self::filter_log_by_retention($log);
        if ( count($pruned) !== count($log) ) {
            \update_option(self::RECENT_EVENT_LOG_OPTION, $pruned, false);
        }
    }

    public static function get_recent_event_log(): array {
        $log = get_option(self::RECENT_EVENT_LOG_OPTION, []);
        if ( ! is_array($log) ) {
            return [];
        }

        $normalized = [];
        foreach ($log as $entry) {
            if ( ! is_array($entry) ) {
                continue;
            }

            $integration = self::normalize_integration((string) ($entry['integration'] ?? 'general'));
            $codes       = isset($entry['codes']) && is_array($entry['codes']) ? array_values(array_map('strval', $entry['codes'])) : [];

            $normalized[] = [
                'time'        => isset($entry['time']) ? (int) $entry['time'] : 0,
                'integration' => $integration,
                'label'       => self::get_integration_label($integration),
                'outcome'     => ! empty($entry['outcome']) && $entry['outcome'] === 'success' ? 'success' : 'failure',
                'codes'       => $codes,
                'retry'       => self::is_retry_codes($codes),
                'latency_ms'  => max(0, (int) ($entry['latency_ms'] ?? 0)),
            ];
        }

        return $normalized;
    }

    /**
     * Map error codes (and outcome) to a short category slug and a plain-English hint note.
     *
     * Used by both the text log and the CSV export so that admins can distinguish between
     * likely false positives (e.g. stale nonce on a cached page) and genuine security blocks.
     *
     * @param  array<int,string> $codes   Error codes for this event.
     * @param  string            $outcome 'success' or 'failure'.
     * @return array{category: string, note: string}
     */
    public static function get_event_category_and_note( array $codes, string $outcome ): array {
        if ( $outcome === 'success' ) {
            // Non-blocking T1/T3 notices still ride along on a successful event (see
            // record_outcome()); surface them instead of the blanket "passed" note so
            // admins can see, e.g., a token whose action/hostname didn't match what was
            // expected even though it still passed Cloudflare's own check.
            if ( in_array( 'action_mismatch', $codes, true ) || in_array( 'hostname_mismatch', $codes, true ) ) {
                return [
                    'category' => 'action-hostname-mismatch',
                    'note'     => __( 'Passed Cloudflare verification, but the action or hostname Cloudflare echoed back did not match what this form should have sent. Currently logged only (not blocked) – see "Harden verification" in the roadmap.', 'kitgenix-captcha-for-cloudflare-turnstile' ),
                ];
            }
            if ( in_array( 'nonce_invalid_soft_bypass', $codes, true ) ) {
                return [
                    'category' => 'nonce-soft-bypass',
                    'note'     => __( 'Passed on the Turnstile token alone; the WP security nonce was stale or missing, which is expected on a page-cached form and was not treated as a failure.', 'kitgenix-captcha-for-cloudflare-turnstile' ),
                ];
            }

            return [
                'category' => 'passed',
                'note'     => __( 'Challenge verified by Cloudflare – legitimate submission.', 'kitgenix-captcha-for-cloudflare-turnstile' ),
            ];
        }

        // Pick the most significant code from the set.
        $map = [
            // Configuration issues – fix in plugin settings
            'missing-input-secret'    => [
                'category' => 'config-error',
                'note'     => __( 'Plugin secret key is not configured. Go to Settings → Cloudflare Turnstile to fix.', 'kitgenix-captcha-for-cloudflare-turnstile' ),
            ],
            'invalid-input-secret'    => [
                'category' => 'config-error',
                'note'     => __( 'Secret key is invalid. Check it in Settings → Cloudflare Turnstile.', 'kitgenix-captcha-for-cloudflare-turnstile' ),
            ],
            'sitekey-secret-mismatch' => [
                'category' => 'config-error',
                'note'     => __( 'Site key and secret key belong to different Turnstile widgets. Verify both in your Cloudflare dashboard.', 'kitgenix-captcha-for-cloudflare-turnstile' ),
            ],

            // Infrastructure issues – usually transient
            'http_error' => [
                'category' => 'network-error',
                'note'     => __( 'Plugin could not reach Cloudflare to verify the token (timeout or connectivity issue). No user action blocked by this alone.', 'kitgenix-captcha-for-cloudflare-turnstile' ),
            ],
            'internal-error' => [
                'category' => 'cloudflare-error',
                'note'     => __( 'Temporary error from Cloudflare\'s verification service. Should resolve on its own.', 'kitgenix-captcha-for-cloudflare-turnstile' ),
            ],
            'bad-request' => [
                'category' => 'bad-request',
                'note'     => __( 'Cloudflare rejected the verification request as malformed. May indicate a plugin conflict or proxy stripping POST fields.', 'kitgenix-captcha-for-cloudflare-turnstile' ),
            ],

            // Zero-JS bot trap (checked before Cloudflare is even contacted)
            'honeypot_tripped' => [
                'category' => 'honeypot-blocked',
                'note'     => __( 'A hidden trap field was filled in. Real visitors never see or fill this field, so this is almost always an automated bot submitting the form blindly.', 'kitgenix-captcha-for-cloudflare-turnstile' ),
            ],

            // Likely false positives (common on cached/shared-session sites)
            'nonce_invalid' => [
                'category' => 'cached-or-expired-page',
                'note'     => __( 'Stale WP security token. Most common on cached pages or after a session/login change. Usually a false positive – NOT necessarily a bot or attack.', 'kitgenix-captcha-for-cloudflare-turnstile' ),
            ],

            // Widget load issues (may be real users on slow connections or bots)
            'token_missing' => [
                'category' => 'widget-not-loaded',
                'note'     => __( 'No Turnstile token was in the form. Widget may not have loaded before submit (slow JS), JS may be blocked, or the form was posted directly (bot).', 'kitgenix-captcha-for-cloudflare-turnstile' ),
            ],
            'missing-input-response' => [
                'category' => 'widget-not-loaded',
                'note'     => __( 'Cloudflare received no response token. Widget may not have rendered before submit, or JS was disabled/blocked.', 'kitgenix-captcha-for-cloudflare-turnstile' ),
            ],

            // Genuine security blocks
            'invalid-input-response' => [
                'category' => 'token-rejected',
                'note'     => __( 'Token present but rejected as invalid or malformed by Cloudflare. Likely a bot-crafted or tampered submission.', 'kitgenix-captcha-for-cloudflare-turnstile' ),
            ],
            'timeout-or-duplicate' => [
                'category' => 'token-expired',
                'note'     => __( 'Token expired before server verification, or was already used in a previous request.', 'kitgenix-captcha-for-cloudflare-turnstile' ),
            ],
            'replay_detected' => [
                'category' => 'replay-blocked',
                'note'     => __( 'Identical Turnstile token submitted more than once. Usually an ordinary double-click or back-button resubmit – a genuine replay attack looks the same, so treat this as informational unless it repeats from one visitor.', 'kitgenix-captcha-for-cloudflare-turnstile' ),
            ],
        ];

        foreach ( $codes as $code ) {
            if ( isset( $map[ $code ] ) ) {
                return $map[ $code ];
            }
        }

        return [
            'category' => 'blocked',
            'note'     => __( 'Blocked – reason not mapped to a known category.', 'kitgenix-captcha-for-cloudflare-turnstile' ),
        ];
    }

    public static function get_recent_event_log_text(): string {
        $events = self::get_recent_event_log();
        if ( empty($events) ) {
            return __('No recent Turnstile diagnostic events recorded yet.', 'kitgenix-captcha-for-cloudflare-turnstile');
        }

        $format = (string) get_option('date_format') . ' ' . (string) get_option('time_format');
        $lines  = [
            // Header so the format is self-documenting
            '# Columns: timestamp | integration (key) | outcome | latency | category | error codes | note',
        ];

        foreach (array_reverse($events) as $event) {
            $time = isset($event['time']) ? (int) $event['time'] : 0;
            if ( function_exists('wp_date') ) {
                $when = $time ? \wp_date($format, $time) : '';
            } else {
                $when = $time ? \date_i18n($format, $time) : '';
            }

            $codes_arr  = isset($event['codes']) && is_array($event['codes']) ? array_map('strval', $event['codes']) : [];
            $codes_str  = $codes_arr !== [] ? implode(', ', $codes_arr) : __('none', 'kitgenix-captcha-for-cloudflare-turnstile');
            $outcome    = (string) ($event['outcome'] ?? 'failure');
            $context    = self::get_event_category_and_note($codes_arr, $outcome);
            $latency_ms = max(0, (int) ($event['latency_ms'] ?? 0));
            $latency_str = $latency_ms > 0
                /* translators: %d: siteverify round-trip time in milliseconds. */
                ? sprintf(__('%dms', 'kitgenix-captcha-for-cloudflare-turnstile'), $latency_ms)
                : __('n/a', 'kitgenix-captcha-for-cloudflare-turnstile');

            $integration_key   = (string) ($event['integration'] ?? 'general');
            $integration_label = (string) ($event['label'] ?? self::get_integration_label($integration_key));

            $lines[] = sprintf(
                '%1$s | %2$s (%3$s) | %4$s | %5$s | %6$s | %7$s | %8$s',
                $when ?: __('Unknown time', 'kitgenix-captcha-for-cloudflare-turnstile'),
                $integration_label,
                $integration_key,
                $outcome,
                $latency_str,
                $context['category'],
                $codes_str,
                $context['note']
            );
        }

        return implode("\n", $lines);
    }

    /* =========================
     * Internals
     * ========================= */

    /**
     * Pull token from POST or a custom header (helps fetch/Blocks flows).
     */
    private static function get_token_from_request(): string {
        // Prefer an explicit header token (used by fetch/Blocks flows).
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- header token is allowed for fetch/Blocks flows; nonce verification occurs in caller when required.
        if ( isset( $_SERVER['HTTP_X_TURNSTILE_TOKEN'] ) ) {
            return sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_TURNSTILE_TOKEN'] ) );
        }

        // Read from POST. If a nonce is present, verify it here; otherwise
        // return the token and let callers perform any required checks.
        // This optional verification satisfies static analysis without
        // changing behavior for flows that don't submit a nonce.
        if ( isset( $_POST['cf-turnstile-response'] ) ) {
            $token = sanitize_text_field( wp_unslash( $_POST['cf-turnstile-response'] ) );

            if ( isset( $_POST['kitgenix_captcha_for_cloudflare_turnstile_nonce'] ) ) {
                $nonce = sanitize_text_field( wp_unslash( $_POST['kitgenix_captcha_for_cloudflare_turnstile_nonce'] ) );
                if ( '' === $nonce ) {
                    self::$last_error_codes[] = 'nonce_invalid';
                    self::$last_error_msg     = __('Security check failed. Please refresh and try again.', 'kitgenix-captcha-for-cloudflare-turnstile');
                    return '';
                }
                if ( ! wp_verify_nonce( $nonce, 'kitgenix_captcha_for_cloudflare_turnstile_action' ) ) {
                    self::$last_error_codes[] = 'nonce_invalid';
                    self::$last_error_msg     = __('Security check failed. Please refresh and try again.', 'kitgenix-captcha-for-cloudflare-turnstile');
                    return '';
                }
            }

            return $token;
        }
        
        $token = apply_filters('kitgenix_turnstile_token_from_request', '');
        return sanitize_text_field( (string) $token );
    }

    /**
     * Perform the siteverify call with memoization and robust HTTP args.
     *
     * @param string $token
     * @param string $integration Integration context, used for the T1 action/hostname check.
     */
    private static function verify_with_site(string $token, string $integration = ''): bool {
        if (array_key_exists($token, self::$verified_tokens)) {
            return self::$verified_tokens[$token];
        }

        self::$last_notice_codes = [];

        $settings = get_option('kitgenix_captcha_for_cloudflare_turnstile_settings', []);
        $secret   = $settings['secret_key'] ?? '';

        if ($secret === '') {
            self::$last_error_codes = ['missing-input-secret'];
            self::$last_error_msg   = __('Secret key missing.', 'kitgenix-captcha-for-cloudflare-turnstile');
            self::record_last_verify(false, self::$last_error_codes);
            return self::$verified_tokens[$token] = false;
        }

        $remoteip     = Client_IP::get();
        $remoteip     = apply_filters('kitgenix_turnstile_remote_ip', $remoteip);
        $send_remoteip = apply_filters('kitgenix_turnstile_send_remoteip', true);

        // Idempotency key: identical for every attempt of THIS verification call, so a
        // network-level retry after Cloudflare already recorded success is recognized as
        // the same request instead of failing as timeout-or-duplicate. See T4 in ROADMAP.md.
        $idempotency_key = function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : self::token_hash($token . microtime());

        $body = [
            'secret'         => $secret,
            'response'       => $token,
            'idempotency_key' => $idempotency_key,
        ];
        if ($send_remoteip && $remoteip) {
            $body['remoteip'] = $remoteip;
        }

        $url  = apply_filters('kitgenix_turnstile_siteverify_url', 'https://challenges.cloudflare.com/turnstile/v0/siteverify'); // phpcs:ignore PluginCheck.CodeAnalysis.Offloading.OffloadedContent -- Cloudflare Turnstile's own official verification endpoint, the plugin's core third-party CAPTCHA service; there is no self-hosted alternative.
        $args = [
            'timeout'   => (int) apply_filters('kitgenix_turnstile_siteverify_timeout', 10),
            'headers'   => [
                'User-Agent' => 'kitgenix-captcha-for-cloudflare-turnstile/' . ( defined( 'KitgenixCaptchaForCloudflareTurnstile_Version' ) ? (string) constant( 'KitgenixCaptchaForCloudflareTurnstile_Version' ) : '2.2.0' ),
                'Accept'     => 'application/json',
            ],
            'body'      => $body,
            'sslverify' => apply_filters('kitgenix_turnstile_siteverify_sslverify', true),
        ];
        $args = apply_filters('kitgenix_turnstile_siteverify_http_args', $args, $token);

        // One retry on a transport-level failure only (timeout, DNS, connection reset –
        // never on a well-formed Cloudflare response, even an unsuccessful one). Reusing
        // the same idempotency_key means a retry that lands after the first attempt
        // actually succeeded server-side is recognized as a duplicate of that success,
        // not rejected. See T4 in ROADMAP.md.
        $attempts = 0;
        do {
            $attempts++;
            $request_start = microtime(true);
            $resp = wp_remote_post($url, $args);
            self::$last_latency_ms = (int) round((microtime(true) - $request_start) * 1000);
            $transport_failed = ! $resp || is_wp_error($resp);
        } while ( $transport_failed && $attempts < 2 );

        if ( $transport_failed ) {
            $msg = $resp && is_wp_error($resp) ? $resp->get_error_message() : 'no-response';
            self::$last_response    = ['http_error' => $msg];
            self::$last_error_codes = ['http_error'];
            /* translators: %s: HTTP error detail returned when contacting Cloudflare's Turnstile verify endpoint */
            self::$last_error_msg   = sprintf(__('Verification request failed: %s', 'kitgenix-captcha-for-cloudflare-turnstile'), $msg);
            self::record_last_verify(false, self::$last_error_codes);
            return self::$verified_tokens[$token] = false;
        }

        $json = json_decode( (string) wp_remote_retrieve_body($resp), true );
        self::$last_response = is_array($json) ? $json : [];

        $codes = isset($json['error-codes']) && is_array($json['error-codes']) ? $json['error-codes'] : [];
        self::$last_error_codes = $codes;

        $ok = !empty($json['success']);
        if ( ! $ok ) {
            // Map codes → friendly message immediately
            self::$last_error_msg = self::message_from_error_codes(self::$last_error_codes, '');
            self::record_last_verify(false, self::$last_error_codes);
        } else {
            // success recorded after replay check in callers
            self::$last_notice_codes = self::evaluate_action_hostname($integration, self::$last_response);

            if ( ! empty(self::$last_notice_codes) && apply_filters('kitgenix_turnstile_enforce_action_hostname', false, $integration, self::$last_notice_codes) ) {
                self::$last_error_codes = self::$last_notice_codes;
                self::$last_error_msg   = __('Verification failed. Please try again.', 'kitgenix-captcha-for-cloudflare-turnstile');
                self::record_last_verify(false, self::$last_error_codes);
                $ok = false;
            }
        }

        return self::$verified_tokens[$token] = (bool) $ok;
    }

    /**
     * T1 hardening: check the `action` and `hostname` fields Cloudflare's siteverify
     * echoes back against what this integration's widget should have sent (see
     * Script_Handler::get_widget_data_attributes()) and against a filterable hostname
     * allowlist (default: this site's own host). A mismatch means the token may have
     * been solved on a different form, or on another site/staging copy sharing the same
     * site key, and is replayed here.
     *
     * Ships in LOG-ONLY mode: mismatches are recorded as non-blocking notice codes (and
     * fired via the `kitgenix_turnstile_action_hostname_mismatch` action) but never fail
     * verification, unless a site/integrator opts in via the
     * `kitgenix_turnstile_enforce_action_hostname` filter – at which point this becomes
     * authoritative and the caller MUST block (see verify_with_site()). Planned to
     * default to enforced in a later release once sites have had a chance to review the
     * log-only data (see ROADMAP.md).
     *
     * @return string[] Notice codes ('action_mismatch' / 'hostname_mismatch'), empty when clean.
     */
    private static function evaluate_action_hostname(string $integration, array $response): array {
        $integration = self::normalize_integration($integration);
        $group_map   = Script_Handler::get_integration_analytics_key_map();
        $expected    = $group_map[ $integration ] ?? '';

        // No widget-attribute group for this context (e.g. 'general', 'setup-check',
        // or a REST/API integration that never rendered our widget markup) – nothing to compare.
        if ( '' === $expected ) {
            return [];
        }

        $notices = [];

        $actual_action = isset($response['action']) ? (string) $response['action'] : '';
        if ( '' !== $actual_action && $actual_action !== $expected ) {
            $notices[] = 'action_mismatch';
        }

        $actual_host = isset($response['hostname']) ? strtolower((string) $response['hostname']) : '';
        if ( '' !== $actual_host ) {
            $home_host      = (string) \wp_parse_url( \home_url(), PHP_URL_HOST );
            $allowed_hosts  = (array) apply_filters( 'kitgenix_turnstile_allowed_hostnames', array_filter( [ $home_host ] ), $integration );
            $allowed_hosts  = array_map( 'strtolower', array_map( 'strval', $allowed_hosts ) );

            if ( ! empty($allowed_hosts) && ! in_array($actual_host, $allowed_hosts, true) ) {
                $notices[] = 'hostname_mismatch';
            }
        }

        if ( ! empty($notices) ) {
            self::log_dev('action_hostname_mismatch', [
                'integration'    => $integration,
                'expected_action'=> $expected,
                'actual_action'  => $actual_action,
                'actual_host'    => $actual_host,
                'notices'        => $notices,
            ]);
            \do_action('kitgenix_turnstile_action_hostname_mismatch', $integration, $notices, $response);
        }

        return $notices;
    }

    /* =========================
     * Error code → message mapping
     * ========================= */

    /**
     * Central mapping for Cloudflare siteverify error codes.
     * Allows integrators to alter $codes before mapping via `kitgenix_turnstile_error_codes`.
     *
     * @param array  $codes
     * @param string $context
     * @return string Friendly, localized message (or empty if none)
     */
    private static function message_from_error_codes(array $codes, string $context = ''): string {
        if (empty($codes)) {
            return '';
        }

        // Normalize strings and allow custom filters to adjust
        $codes = array_values(array_unique(array_map('strval', $codes)));
        $codes = (array) apply_filters('kitgenix_turnstile_error_codes', $codes, $context, self::$last_response);

        // Known mappings (Cloudflare Turnstile)
        $map = [
            // Config / key issues
            'missing-input-secret'    => __('Configuration error: secret key missing.', 'kitgenix-captcha-for-cloudflare-turnstile'),
            'invalid-input-secret'    => __('Configuration error: invalid secret key.', 'kitgenix-captcha-for-cloudflare-turnstile'),
            'sitekey-secret-mismatch' => __('Configuration error: site key and secret mismatch.', 'kitgenix-captcha-for-cloudflare-turnstile'),

            // Token / response issues
            'missing-input-response'  => __('Please complete the Turnstile challenge.', 'kitgenix-captcha-for-cloudflare-turnstile'),
            'invalid-input-response'  => __('Verification failed. Please try again.', 'kitgenix-captcha-for-cloudflare-turnstile'),
            'timeout-or-duplicate'    => __('Verification expired. Please try again.', 'kitgenix-captcha-for-cloudflare-turnstile'),

            // Request issues
            'bad-request'             => __('Verification request invalid. Please try again.', 'kitgenix-captcha-for-cloudflare-turnstile'),
            'internal-error'          => __('Verification temporarily unavailable. Please try again.', 'kitgenix-captcha-for-cloudflare-turnstile'),

            // Our own internal markers
            'http_error'              => __('Verification request failed. Please try again.', 'kitgenix-captcha-for-cloudflare-turnstile'),
            'nonce_invalid'           => __('Security check failed. Please try again.', 'kitgenix-captcha-for-cloudflare-turnstile'),
            'token_missing'           => __('Please complete the Turnstile challenge.', 'kitgenix-captcha-for-cloudflare-turnstile'),
            'replay_detected'         => __('Verification expired. Please try again.', 'kitgenix-captcha-for-cloudflare-turnstile'),
        ];

        // First known code wins
        foreach ($codes as $c) {
            if (isset($map[$c])) {
                $msg = $map[$c];
                // Let integrators post-process the friendly message if needed
                $msg = apply_filters('kitgenix_turnstile_error_message', $msg, $codes, $context, self::$last_response);
                return (string) $msg;
            }
        }

        // Unknown code → generic
        $generic = __('Turnstile verification failed. Please try again.', 'kitgenix-captcha-for-cloudflare-turnstile');
        return (string) apply_filters('kitgenix_turnstile_error_message', $generic, $codes, $context, self::$last_response);
    }

    private static function record_recent_event(string $integration, bool $ok, array $codes = [], string $token = ''): void {
        $integration = self::normalize_integration($integration);
        $codes       = array_values(array_unique(array_map('strval', $codes)));
        $cache_key   = $integration . '|' . ($ok ? '1' : '0') . '|' . implode(',', $codes) . '|' . ($token !== '' ? self::token_hash($token) : 'no-token');

        if ( isset(self::$logged_events[$cache_key]) ) {
            return;
        }
        self::$logged_events[$cache_key] = true;

        $log = self::get_recent_event_log();
        $log[] = [
            'time'        => time(),
            'integration' => $integration,
            'outcome'     => $ok ? 'success' : 'failure',
            'codes'       => $codes,
            'latency_ms'  => self::$last_latency_ms,
        ];

        // Age-based retention, in addition to the existing count cap below.
        $log = self::filter_log_by_retention($log);

        if ( count($log) > self::RECENT_EVENT_LOG_LIMIT ) {
            $log = array_slice($log, -self::RECENT_EVENT_LOG_LIMIT);
        }

        \update_option(self::RECENT_EVENT_LOG_OPTION, $log, false);
    }

    private static function normalize_integration(string $integration): string {
        $integration = strtolower(trim(str_replace('_', '-', $integration)));
        return $integration !== '' ? $integration : 'general';
    }

    private static function calculate_rate(int $numerator, int $denominator): float {
        if ($denominator <= 0) {
            return 0.0;
        }

        return round(($numerator / $denominator) * 100, 1);
    }
}
