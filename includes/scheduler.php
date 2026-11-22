<?php
namespace Ssmptms;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

if ( ! class_exists( __NAMESPACE__ . '\\Email_Scheduler', false ) ) {

    class Email_Scheduler {
        private int $emailsPerUnit;
        private string $unit;   // "minute", "hour", "day"
        private float $rate;    // emails per minute
        private $callback;

        public function __construct( callable $callback ) {
            $this->emailsPerUnit = (int) get_option( Constants::EMAILS_PER_UNIT, 0 );
            $this->unit          = get_option( Constants::EMAILS_UNIT, 'minute' );
            $this->rate          = $this->calculateRate( $this->emailsPerUnit, $this->unit );
            $this->callback      = $callback;
        }

        private function calculateRate( int $emailsPerUnit, string $unit ): float {
            switch ( $unit ) {
                case 'minute':
                    return (float) $emailsPerUnit;
                case 'hour':
                    return $emailsPerUnit > 0 ? $emailsPerUnit / 60.0 : 0.0;
                case 'day':
                    return $emailsPerUnit > 0 ? $emailsPerUnit / 1440.0 : 0.0; // 24 * 60
                default:
                    return 0.0; // invalid config → don’t send
            }
        }

        private function getDefaultLastTick( float $now ): float {
            if ( $this->rate <= 0.0 ) {
                return $now;
            }

            $secondsForOneEmail = 60.0 / max(0.0001, $this->rate);
            $secondsNeeded      = min(3600.0, max(10.0, $secondsForOneEmail * 2.0));

            return $now - $secondsNeeded;
        }

        private function formatLockValue(string $token, float $expires): string {
            return $token . '|' . sprintf('%.6F', $expires);
        }

        private function parseLockValue(string $value): ?array {
            $parts = explode('|', $value, 2);

            if (count($parts) !== 2 || $parts[0] === '' || !is_numeric($parts[1])) {
                return null;
            }

            return [
                'token'   => $parts[0],
                'expires' => (float) $parts[1],
            ];
        }

        private function deleteLockValue(string $value): bool {
            global $wpdb;

            $deleted = $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s",
                    Constants::SCHEDULER_LOCK,
                    $value
                )
            );

            if ($deleted) {
                wp_cache_delete(Constants::SCHEDULER_LOCK, 'options');
                return true;
            }

            return false;
        }

        private function insertLockValue(string $value): bool {
            global $wpdb;

            $inserted = $wpdb->query(
                $wpdb->prepare(
                    "INSERT IGNORE INTO {$wpdb->options} (`option_name`, `option_value`, `autoload`) VALUES (%s, %s, %s)",
                    Constants::SCHEDULER_LOCK,
                    $value,
                    'no'
                )
            );

            if (1 !== $inserted) {
                return false;
            }

            wp_cache_delete(Constants::SCHEDULER_LOCK, 'options');
            wp_cache_delete('notoptions', 'options');

            return true;
        }

        private function acquireLock(): ?string {
            $token  = wp_generate_uuid4();
            $value  = $this->formatLockValue($token, microtime(true) + Constants::SCHEDULER_LOCK_TTL);
            $now    = microtime(true);

            if ($this->insertLockValue($value)) {
                return $token;
            }

            $current = (string) get_option(Constants::SCHEDULER_LOCK, '');
            $parsed  = $this->parseLockValue($current);

            if (($parsed === null || $parsed['expires'] <= $now)
                && $this->deleteLockValue($current)
                && $this->insertLockValue($value)) {
                return $token;
            }

            return null;
        }

        private function releaseLock(string $token): void {
            $current = (string) get_option(Constants::SCHEDULER_LOCK, '');
            $parsed  = $this->parseLockValue($current);

            if ($parsed && hash_equals($parsed['token'], $token)) {
                $this->deleteLockValue($current);
            }
        }

        public function tick(): void {
            $lockToken = $this->acquireLock();

            if ($lockToken === null) {
                error_log('M4W SMTP Scheduler tick: skipped, another tick is already running');
                return;
            }

            try {
                $now = microtime(true);

                $lastTime = (float) get_option(Constants::EMAILS_SCHEDULER_LAST_TICK, $this->getDefaultLastTick($now));
                $carry    = (float) get_option(Constants::EMAILS_SCHEDULER_CARRY, 0.0);

                $elapsed = max(0.1, $now - $lastTime);
                update_option(Constants::EMAILS_SCHEDULER_LAST_TICK, $now, false);

                $emailsPerSecond = $this->rate / 60.0;
                $emailsExact     = ($emailsPerSecond * $elapsed) + $carry;
                $toSend          = (int) floor($emailsExact);
                $carry           = $emailsExact - $toSend;

                if ($toSend > 0 && is_callable($this->callback)) {
                    try {
                        ($this->callback)($toSend);
                    } catch (\Throwable $e) {
                        error_log('M4W SMTP Mail Scheduler tick() error: ' . $e->getMessage());
                    }
                }

                update_option(Constants::EMAILS_SCHEDULER_CARRY, $carry, false);

                if (!Email_Queue::get_instance()->has_email_entries_for_sending()) {
                    unschedule_cron_event();

                    if (Email_Queue::get_instance()->has_email_entries_for_sending()) {
                        schedule_cron_event();
                    } else {
                        error_log('M4W SMTP Scheduler tick: unschedule (queue empty)');
                        delete_option(Constants::CURRENT_QUEUE_COUNT);
                        delete_option(Constants::EMAILS_SCHEDULER_LAST_TICK);
                        delete_option(Constants::EMAILS_SCHEDULER_CARRY);
                    }
                }
            } finally {
                $this->releaseLock($lockToken);
            }
        }
    }
}

function schedule_cron_event() {
    $next = wp_next_scheduled(Constants::SCHEDULER_EVENT_NAME);
    $now  = time();

    if ($next !== false && $next <= $now + Constants::SCHEDULER_INTERVAL) {
        return;
    }

    if ($next !== false) {
        wp_unschedule_event($next, Constants::SCHEDULER_EVENT_NAME);
    }

    wp_schedule_event($now, Constants::SCHEDULER_SCHEDULE, Constants::SCHEDULER_EVENT_NAME);
}

function unschedule_cron_event() {
    $removed = 0;

    while (($timestamp = wp_next_scheduled(Constants::SCHEDULER_EVENT_NAME)) !== false && $removed < 10) {
        wp_unschedule_event($timestamp, Constants::SCHEDULER_EVENT_NAME);
        $removed++;
    }
}

function migrate_cron_event_schedule() {
    $has_legacy_event = false;

    foreach (_get_cron_array() as $hooks) {
        if (!isset($hooks[Constants::SCHEDULER_EVENT_NAME])) {
            continue;
        }

        foreach ($hooks[Constants::SCHEDULER_EVENT_NAME] as $event) {
            if (($event['schedule'] ?? '') !== Constants::SCHEDULER_SCHEDULE) {
                $has_legacy_event = true;
                break 2;
            }
        }
    }

    if (!$has_legacy_event) {
        return;
    }

    unschedule_cron_event();
    schedule_cron_event();
}
