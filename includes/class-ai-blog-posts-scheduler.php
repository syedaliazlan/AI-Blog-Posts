<?php

/**
 * Scheduler for automated post generation
 *
 * @link       https://devonicweb.co.uk/
 * @since      1.0.0
 *
 * @package    Ai_Blog_Posts
 * @subpackage Ai_Blog_Posts/includes
 */

/**
 * Handles scheduled/automated post generation via WP Cron.
 *
 * @since      1.0.0
 * @package    Ai_Blog_Posts
 * @subpackage Ai_Blog_Posts/includes
 * @author     Ali Azlan <contact@devonicweb.co.uk>
 */
class Ai_Blog_Posts_Scheduler {

	/**
	 * Generator instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      Ai_Blog_Posts_Generator
	 */
	private $generator;

	/**
	 * Cost tracker instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      Ai_Blog_Posts_Cost_Tracker
	 */
	private $cost_tracker;

	/**
	 * Initialize the class.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		$this->generator = new Ai_Blog_Posts_Generator();
		$this->cost_tracker = new Ai_Blog_Posts_Cost_Tracker();
	}

	/**
	 * Fallback check: Trigger generation if scheduled time has passed.
	 * 
	 * This runs on every page load to catch missed cron events.
	 * WordPress cron only runs when someone visits the site, so this ensures
	 * generation happens even if cron is delayed.
	 *
	 * @since    1.0.0
	 */
	public function maybe_trigger_scheduled_generation() {
		// Only check if scheduling is enabled
		if ( ! Ai_Blog_Posts_Settings::get( 'schedule_enabled' ) ) {
			return;
		}

		// Check if there's a scheduled event
		$next_scheduled = wp_next_scheduled( 'ai_blog_posts_scheduled_generation' );
		
		// If no event scheduled, reschedule
		if ( ! $next_scheduled ) {
			$this->reschedule();
			return;
		}

		// Check if the scheduled time has passed (with a 5-minute grace period)
		// This catches cases where cron didn't run at the exact time
		$time_passed = time() - $next_scheduled;
		
		if ( $time_passed > 0 && $time_passed <= 5 * MINUTE_IN_SECONDS ) {
			// Use a transient to prevent multiple triggers for the same scheduled time
			$trigger_key = 'ai_blog_posts_triggered_' . $next_scheduled;
			if ( get_transient( $trigger_key ) ) {
				// Already triggered for this scheduled time
				return;
			}
			
			// Mark as triggered (expires in 10 minutes)
			set_transient( $trigger_key, time(), 10 * MINUTE_IN_SECONDS );
			
			// Scheduled time has passed but cron didn't run - trigger it now
			$this->log_event( sprintf( 
				'Fallback trigger: Scheduled time (%s) passed %d seconds ago, triggering generation.', 
				date_i18n( 'Y-m-d H:i:s', $next_scheduled ),
				$time_passed
			) );
			
			// Trigger the generation (but don't reschedule yet - let the cron handler do that)
			$this->run_scheduled_generation();
		}
	}

	/**
	 * Run the scheduled generation.
	 *
	 * This is called by WP Cron at the scheduled interval.
	 *
	 * @since    1.0.0
	 */
	public function run_scheduled_generation() {
		// Prevent concurrent executions using a lock
		$lock_key = 'ai_blog_posts_generation_lock';
		$lock = get_transient( $lock_key );
		
		if ( $lock ) {
			$this->log_event( 'Scheduled generation skipped: Another generation is in progress.' );
			return;
		}

		// Set lock for 10 minutes max (in case of crash)
		set_transient( $lock_key, time(), 10 * MINUTE_IN_SECONDS );

		try {
			$this->do_scheduled_generation();
			
			// After generation, reschedule for the next occurrence
			// This ensures the next run is scheduled at the exact time
			$this->reschedule();
		} finally {
			// Always release lock when done
			delete_transient( $lock_key );
		}
	}

	/**
	 * Perform the actual scheduled generation.
	 *
	 * @since    1.0.0
	 */
	private function do_scheduled_generation() {
		// Check if scheduling is enabled
		if ( ! Ai_Blog_Posts_Settings::get( 'schedule_enabled' ) ) {
			return;
		}

		// Check cooldown period (prevents immediate generation after settings save)
		// BUT: Don't block if we're at or past the scheduled time - that's a legitimate run
		$cooldown_end = get_transient( 'ai_blog_posts_schedule_cooldown' );
		if ( $cooldown_end && time() < $cooldown_end ) {
			// Check if we're at the scheduled time - if so, clear cooldown and proceed
			$frequency = Ai_Blog_Posts_Settings::get( 'schedule_frequency' );
			$scheduled_time = Ai_Blog_Posts_Settings::get( 'schedule_time' );
			
			// For non-hourly frequencies, check if we're at the scheduled time
			if ( 'hourly' !== $frequency && ! empty( $scheduled_time ) && preg_match( '/^(\d{1,2}):(\d{2})$/', $scheduled_time, $matches ) ) {
				$timezone = wp_timezone();
				$now = new DateTime( 'now', $timezone );
				$scheduled_hour = (int) $matches[1];
				$scheduled_minute = (int) $matches[2];
				
				$scheduled_today = clone $now;
				$scheduled_today->setTime( $scheduled_hour, $scheduled_minute, 0 );
				
				// If we're within 5 minutes of the scheduled time, clear cooldown and proceed
				$time_diff = abs( $now->getTimestamp() - $scheduled_today->getTimestamp() );
				if ( $time_diff <= 5 * MINUTE_IN_SECONDS ) {
					// We're at the scheduled time - clear cooldown and proceed
					delete_transient( 'ai_blog_posts_schedule_cooldown' );
					$this->log_event( sprintf( 
						'Cooldown cleared: At scheduled time (%s), proceeding with generation.', 
						$scheduled_today->format( 'H:i:s' )
					) );
				} else {
					// Not at scheduled time yet, respect cooldown
					$remaining = round( ( $cooldown_end - time() ) / 60 );
					$this->log_event( sprintf( 'Scheduled generation skipped: Cooldown period active (%d minutes remaining).', $remaining ) );
					return;
				}
			} else {
				// For hourly or if we can't determine scheduled time, respect cooldown
				$remaining = round( ( $cooldown_end - time() ) / 60 );
				$this->log_event( sprintf( 'Scheduled generation skipped: Cooldown period active (%d minutes remaining).', $remaining ) );
				return;
			}
		}

		// Check if API is configured
		if ( ! Ai_Blog_Posts_Settings::is_verified() ) {
			$this->log_event( 'Scheduled generation skipped: API not configured.' );
			return;
		}

		// Check daily limit
		if ( ! $this->cost_tracker->can_generate_today() ) {
			$this->log_event( 'Scheduled generation skipped: Daily limit reached.' );
			return;
		}

		// Check budget
		if ( ! $this->cost_tracker->within_budget() ) {
			$this->log_event( 'Scheduled generation skipped: Budget limit exceeded.' );
			return;
		}

		// Since we're now scheduling at exact times, we don't need the time check
		// But we'll keep a simple validation to ensure we're close to the scheduled time
		// (within 5 minutes) in case cron runs slightly early/late
		$frequency = Ai_Blog_Posts_Settings::get( 'schedule_frequency' );
		
		if ( 'hourly' !== $frequency ) {
			// For non-hourly frequencies, verify we're close to the scheduled time
			// This handles cases where cron might run a few minutes early/late
			if ( ! $this->is_within_time_window() ) {
				$this->log_event( 'Scheduled generation skipped: Not within time window (cron may have run early/late).' );
				// Reschedule anyway to ensure next run is scheduled
				$this->reschedule();
				return;
			}
		}
		
		$this->log_event( 'Scheduled generation time check passed.' );

		// Get and lock next topic from queue
		$topic = $this->get_and_lock_next_topic();

		if ( ! $topic ) {
			$this->log_event( 'Scheduled generation skipped: No topics in queue.' );
			return;
		}

		$this->log_event( sprintf( 'Starting scheduled generation for topic: "%s" (ID: %d)', $topic->topic, $topic->id ) );

		// Track that we're processing this topic to prevent duplicates
		$processing_key = 'ai_blog_posts_processing_topic_' . $topic->id;
		if ( get_transient( $processing_key ) ) {
			$this->log_event( sprintf( 'Scheduled generation skipped: Topic %d is already being processed.', $topic->id ) );
			// Reset topic status if it's stuck
			$this->reset_topic_status( $topic->id );
			return;
		}

		// Set processing flag for 30 minutes (in case of crash)
		set_transient( $processing_key, time(), 30 * MINUTE_IN_SECONDS );

		try {
			// Generate the post
			$result = $this->generator->generate_post( $topic->topic, array(
				'keywords'       => $topic->keywords,
				'category_id'    => $topic->category_id,
				'publish'        => Ai_Blog_Posts_Settings::get( 'post_status' ) === 'publish',
				'source'         => 'scheduled',
				'generate_image' => Ai_Blog_Posts_Settings::get( 'image_enabled' ),
			) );

		// Update topic status
		$this->update_topic_status( $topic->id, $result );

		// Log the result
		if ( is_wp_error( $result ) ) {
			$this->log_event( sprintf( 'Scheduled generation failed for topic "%s": %s', $topic->topic, $result->get_error_message() ) );
		} else {
			$this->log_event( sprintf( 'Scheduled generation successful: "%s" (Post ID: %d)', $result['title'], $result['post_id'] ) );
			
			// Mark daily run as completed
			$frequency = Ai_Blog_Posts_Settings::get( 'schedule_frequency' );
			if ( 'daily' === $frequency ) {
				$today = current_time( 'Y-m-d' );
				set_transient( 'ai_blog_posts_last_daily_run', $today, DAY_IN_SECONDS );
			}
		}
		} catch ( Exception $e ) {
			// Catch any unexpected errors and update topic status
			$this->log_event( sprintf( 'Scheduled generation exception for topic "%s": %s', $topic->topic, $e->getMessage() ) );
			$this->update_topic_status( $topic->id, new WP_Error( 'exception', $e->getMessage() ) );
		} finally {
			// Always clear processing flag
			delete_transient( $processing_key );
		}
	}

	/**
	 * Check if current time is within the acceptable window of scheduled time.
	 * 
	 * Since we schedule at exact times, this is just a safety check for cron timing variations.
	 *
	 * @since    1.0.0
	 * @return   bool
	 */
	private function is_within_time_window() {
		$frequency = Ai_Blog_Posts_Settings::get( 'schedule_frequency' );
		$scheduled_time = Ai_Blog_Posts_Settings::get( 'schedule_time' );

		// For hourly, always allow (we schedule exactly 1 hour apart)
		if ( 'hourly' === $frequency ) {
			return true;
		}

		// For frequencies that use a specific time, validate the time format
		if ( empty( $scheduled_time ) || ! preg_match( '/^(\d{1,2}):(\d{2})$/', $scheduled_time, $matches ) ) {
			return false;
		}

		// Get current time in WordPress timezone
		$timezone = wp_timezone();
		$now = new DateTime( 'now', $timezone );
		
		// Parse scheduled time
		$scheduled_hour = (int) $matches[1];
		$scheduled_minute = (int) $matches[2];

		// Create scheduled time for today in WordPress timezone
		$scheduled_datetime = clone $now;
		$scheduled_datetime->setTime( $scheduled_hour, $scheduled_minute, 0 );
		
		// Calculate time difference
		$time_diff = abs( $now->getTimestamp() - $scheduled_datetime->getTimestamp() );
		
		// Allow a 10-minute window (cron might run slightly early/late)
		$window = 10 * MINUTE_IN_SECONDS;
		
		// For twicedaily, also check 12-hour offset
		if ( 'twicedaily' === $frequency ) {
			$scheduled_datetime_12h = clone $scheduled_datetime;
			$scheduled_datetime_12h->modify( '+12 hours' );
			$time_diff_12h = abs( $now->getTimestamp() - $scheduled_datetime_12h->getTimestamp() );
			$time_diff = min( $time_diff, $time_diff_12h );
		}
		
		return $time_diff <= $window;
	}

	/**
	 * Check if current time matches scheduled time.
	 * 
	 * @deprecated This method is kept for backward compatibility but is no longer used.
	 *             We now schedule at exact times instead of checking on each cron run.
	 *
	 * @since    1.0.0
	 * @return   bool
	 */
	private function is_scheduled_time() {
		$frequency = Ai_Blog_Posts_Settings::get( 'schedule_frequency' );
		$scheduled_time = Ai_Blog_Posts_Settings::get( 'schedule_time' );

		// For hourly, always run (but check we haven't run in the last 50 minutes)
		if ( 'hourly' === $frequency ) {
			$last_run_key = 'ai_blog_posts_last_hourly_run';
			$last_run = get_transient( $last_run_key );
			if ( $last_run && ( time() - $last_run ) < 50 * MINUTE_IN_SECONDS ) {
				$this->log_event( sprintf( 'Hourly check: Last run was %d minutes ago, skipping.', round( ( time() - $last_run ) / 60 ) ) );
				return false;
			}
			set_transient( $last_run_key, time(), HOUR_IN_SECONDS );
			$this->log_event( 'Hourly check: Time to run.' );
			return true;
		}

		// For frequencies that use a specific time, validate the time format
		if ( empty( $scheduled_time ) || ! preg_match( '/^(\d{1,2}):(\d{2})$/', $scheduled_time, $matches ) ) {
			$this->log_event( 'Scheduled time check failed: Invalid time format.' );
			return false;
		}

		// Get current time in WordPress timezone
		$timezone = wp_timezone();
		$now = new DateTime( 'now', $timezone );
		
		// Parse scheduled time
		$scheduled_hour = (int) $matches[1];
		$scheduled_minute = (int) $matches[2];

		// Validate hour and minute
		if ( $scheduled_hour < 0 || $scheduled_hour > 23 || $scheduled_minute < 0 || $scheduled_minute > 59 ) {
			$this->log_event( 'Scheduled time check failed: Invalid hour or minute.' );
			return false;
		}

		// Create scheduled time for today in WordPress timezone
		$scheduled_datetime = clone $now;
		$scheduled_datetime->setTime( $scheduled_hour, $scheduled_minute, 0 );
		
		// If scheduled time has passed today, check if we should use tomorrow's time instead
		// But only if we're way past the window (more than 2 hours for daily)
		$time_diff = $now->getTimestamp() - $scheduled_datetime->getTimestamp();
		
		// For daily, if we're more than 2 hours past, we've missed today's window
		// For other frequencies, use a smaller window
		$max_past_window = ( 'daily' === $frequency ) ? 2 * HOUR_IN_SECONDS : 30 * MINUTE_IN_SECONDS;
		
		if ( $time_diff > $max_past_window ) {
			// We've missed today's window, wait for tomorrow
			$this->log_event( sprintf( 
				'Time check: Scheduled time (%s) has passed more than %d minutes ago. Waiting for next scheduled time.', 
				$scheduled_datetime->format( 'H:i:s' ),
				round( $max_past_window / 60 )
			) );
			return false;
		}
		
		// Log current state for debugging
		$this->log_event( sprintf( 
			'Time check: Now=%s, Scheduled=%s, Diff=%d minutes', 
			$now->format( 'H:i:s' ),
			$scheduled_datetime->format( 'H:i:s' ),
			round( $time_diff / 60 )
		) );
		
		// For daily frequency, allow a window around the scheduled time
		// Window: 10 minutes before to 2 hours after (to catch late cron runs)
		// For other frequencies, use a tighter window
		$before_window = ( 'daily' === $frequency ) ? 10 * MINUTE_IN_SECONDS : 5 * MINUTE_IN_SECONDS;
		$after_window = ( 'daily' === $frequency ) ? 2 * HOUR_IN_SECONDS : 30 * MINUTE_IN_SECONDS;
		
		// Check if we're too early (more than the before window)
		if ( $time_diff < -$before_window ) {
			$this->log_event( sprintf( 'Too early: %d minutes before scheduled time (window: %d minutes).', round( abs( $time_diff ) / 60 ), round( $before_window / 60 ) ) );
			return false;
		}
		
		// If we're within the window (before or after scheduled time), proceed
		// The check above already handled the case where we're way past

		// Check day of week for weekly
		if ( 'weekly' === $frequency ) {
			$day_of_week = (int) current_time( 'w' );
			// Run on Monday (1)
			if ( $day_of_week !== 1 ) {
				$this->log_event( sprintf( 'Weekly check: Today is day %d, need Monday (1).', $day_of_week ) );
				return false;
			}
			$this->log_event( 'Weekly check: It\'s Monday, proceeding.' );
		}

		// For twicedaily, check we haven't run in the last 10 hours
		if ( 'twicedaily' === $frequency ) {
			$last_run_key = 'ai_blog_posts_last_twicedaily_run';
			$last_run = get_transient( $last_run_key );
			if ( $last_run && ( time() - $last_run ) < 10 * HOUR_IN_SECONDS ) {
				$this->log_event( sprintf( 'Twicedaily check: Last run was %d hours ago, skipping.', round( ( time() - $last_run ) / HOUR_IN_SECONDS, 1 ) ) );
				return false;
			}
			set_transient( $last_run_key, time(), 12 * HOUR_IN_SECONDS );
			$this->log_event( 'Twicedaily check: Time to run.' );
		}

		// For daily, check we haven't run today
		if ( 'daily' === $frequency ) {
			$last_run_key = 'ai_blog_posts_last_daily_run';
			$last_run_date = get_transient( $last_run_key );
			$today = current_time( 'Y-m-d' );
			if ( $last_run_date === $today ) {
				$this->log_event( sprintf( 'Daily check: Already ran today (%s).', $today ) );
				return false;
			}
			$this->log_event( sprintf( 'Daily check: Not run today yet (last: %s, today: %s).', $last_run_date ? $last_run_date : 'never', $today ) );
			// Don't set the transient here - it will be set after successful generation
		}

		$this->log_event( 'Time check passed: Proceeding with generation.' );
		return true;
	}

	/**
	 * Get and lock the next topic from the queue.
	 *
	 * Atomically selects and marks a topic as 'processing' to prevent
	 * duplicate processing by concurrent cron jobs.
	 *
	 * @since    1.0.0
	 * @return   object|null    Topic object or null.
	 */
	private function get_and_lock_next_topic() {
		global $wpdb;
		$table = $wpdb->prefix . 'ai_blog_posts_topics';

		// First, reset any stale 'processing' topics (older than 30 minutes)
		// This handles cases where a previous run crashed
		$stale_time = gmdate( 'Y-m-d H:i:s', time() - 30 * MINUTE_IN_SECONDS );
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE $table 
				SET status = 'pending', locked_at = NULL 
				WHERE status = 'processing' 
				AND (locked_at IS NULL OR locked_at < %s)",
				$stale_time
			)
		);

		// Get highest priority pending topic
		$topic = $wpdb->get_row(
			"SELECT * FROM $table 
			WHERE status = 'pending' 
			AND (attempts < 3 OR attempts IS NULL)
			ORDER BY priority DESC, created_at ASC 
			LIMIT 1"
		);

		if ( ! $topic ) {
			return null;
		}

		// Immediately lock this topic by setting status to 'processing'
		$locked = $wpdb->update(
			$table,
			array(
				'status'    => 'processing',
				'locked_at' => current_time( 'mysql' ),
			),
			array(
				'id'     => $topic->id,
				'status' => 'pending', // Only update if still pending (race condition protection)
			),
			array( '%s', '%s' ),
			array( '%d', '%s' )
		);

		// If we couldn't lock it (another process got it), return null
		if ( ! $locked ) {
			return null;
		}

		return $topic;
	}

	/**
	 * Update topic status after generation attempt.
	 *
	 * @since    1.0.0
	 * @param    int              $topic_id    Topic ID.
	 * @param    array|WP_Error   $result      Generation result.
	 */
	private function update_topic_status( $topic_id, $result ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ai_blog_posts_topics';

		if ( is_wp_error( $result ) ) {
			// Increment attempts, mark as failed if max attempts reached
			$topic = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $topic_id ) );
			$attempts = ( $topic->attempts ?? 0 ) + 1;

			$wpdb->update(
				$table,
				array(
					'attempts'   => $attempts,
					'last_error' => $result->get_error_message(),
					'status'     => $attempts >= 3 ? 'failed' : 'pending',
					'locked_at'  => null, // Clear lock
				),
				array( 'id' => $topic_id ),
				array( '%d', '%s', '%s', '%s' ),
				array( '%d' )
			);
		} else {
			// Mark as completed
			$wpdb->update(
				$table,
				array(
					'status'       => 'completed',
					'post_id'      => $result['post_id'],
					'processed_at' => current_time( 'mysql' ),
					'locked_at'    => null, // Clear lock
				),
				array( 'id' => $topic_id ),
				array( '%s', '%d', '%s', '%s' ),
				array( '%d' )
			);
		}
	}

	/**
	 * Reset topic status if it's stuck in processing.
	 *
	 * @since    1.0.0
	 * @param    int $topic_id    Topic ID.
	 */
	private function reset_topic_status( $topic_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ai_blog_posts_topics';

		$wpdb->update(
			$table,
			array(
				'status'    => 'pending',
				'locked_at' => null,
			),
			array( 'id' => $topic_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Log scheduler events.
	 *
	 * @since    1.0.0
	 * @param    string $message    Log message.
	 */
	private function log_event( $message ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[AI Blog Posts] ' . $message );
		}
	}

	/**
	 * Register custom cron schedules.
	 *
	 * @since    1.0.0
	 * @param    array $schedules    Existing schedules.
	 * @return   array               Modified schedules.
	 */
	public function add_cron_schedules( $schedules ) {
		$schedules['weekly'] = array(
			'interval' => WEEK_IN_SECONDS,
			'display'  => __( 'Once Weekly', 'ai-blog-posts' ),
		);

		return $schedules;
	}

	/**
	 * Reschedule cron based on settings.
	 *
	 * Calculates the exact next run time and schedules a single event for that time.
	 * After the event runs, it will call reschedule() again to schedule the next occurrence.
	 *
	 * @since    1.0.0
	 */
	public function reschedule() {
		// Prevent concurrent reschedule calls using a lock
		$lock_key = 'ai_blog_posts_reschedule_lock';
		$lock = get_transient( $lock_key );
		
		if ( $lock ) {
			$this->log_event( 'Reschedule skipped: Another reschedule is in progress.' );
			return;
		}

		// Set lock for 30 seconds (should be enough for reschedule to complete)
		set_transient( $lock_key, time(), 30 );

		// Clear existing schedule
		wp_clear_scheduled_hook( 'ai_blog_posts_scheduled_generation' );

		// Only reschedule if enabled
		if ( ! Ai_Blog_Posts_Settings::get( 'schedule_enabled' ) ) {
			delete_transient( $lock_key );
			$this->log_event( 'Reschedule skipped: Scheduling is disabled.' );
			return;
		}

		$frequency = Ai_Blog_Posts_Settings::get( 'schedule_frequency' );
		$scheduled_time = Ai_Blog_Posts_Settings::get( 'schedule_time' );

		// Get WordPress timezone
		$timezone = wp_timezone();
		$now = new DateTime( 'now', $timezone );

		// Log current state for debugging
		$this->log_event( sprintf( 
			'Reschedule: Frequency=%s, Scheduled Time=%s, Now=%s (timezone: %s)', 
			$frequency,
			$scheduled_time,
			$now->format( 'Y-m-d H:i:s' ),
			$timezone->getName()
		) );

		// Calculate next run time based on frequency
		$next_run = $this->calculate_next_run_time( $frequency, $scheduled_time, $now, $timezone );

		if ( ! $next_run ) {
			$this->log_event( 'Reschedule failed: Could not calculate next run time.' );
			return;
		}

		// Check if this is a settings save (cooldown check)
		$cooldown_end = get_transient( 'ai_blog_posts_schedule_cooldown' );
		$is_settings_save = ( $cooldown_end && time() < $cooldown_end );
		
		if ( $is_settings_save ) {
			// Cooldown is meant to prevent immediate execution (within 30 seconds)
			// If the calculated time is more than 30 seconds away, it's fine to use it
			// Only recalculate if the time is very close (less than 30 seconds) or in the past
			$time_until_run = $next_run - time();
			$min_cooldown = 30; // Only require 30 seconds minimum
			
			if ( $time_until_run < $min_cooldown ) {
				// The scheduled time is too close (less than 30 seconds away) or in the past
				// Recalculate for the next occurrence after cooldown
				$min_time_obj = clone $now;
				$min_time_obj->modify( '+' . $min_cooldown . ' seconds' );
				$min_time = $min_time_obj->getTimestamp();
				
				$this->log_event( sprintf( 
					'Cooldown check: Next run (%s) is too close (%d seconds away), recalculating for after cooldown (%s)...', 
					date_i18n( 'Y-m-d H:i:s', $next_run ),
					$time_until_run,
					date_i18n( 'Y-m-d H:i:s', $min_time )
				) );
				$now->setTimestamp( $min_time );
				$next_run = $this->calculate_next_run_time( $frequency, $scheduled_time, $now, $timezone );
			} else {
				$this->log_event( sprintf( 
					'Cooldown check: Next run (%s) is %d seconds away, using calculated time (cooldown allows it).', 
					date_i18n( 'Y-m-d H:i:s', $next_run ),
					$time_until_run
				) );
			}
		}

		// Schedule single event for the exact time
		$scheduled = wp_schedule_single_event( $next_run, 'ai_blog_posts_scheduled_generation' );

		if ( $scheduled === false ) {
			$this->log_event( sprintf( 'Failed to schedule event for %s', date_i18n( 'Y-m-d H:i:s', $next_run ) ) );
		} else {
			$this->log_event( sprintf( 
				'Scheduled next run for %s (%s from now)', 
				date_i18n( 'Y-m-d H:i:s', $next_run ),
				human_time_diff( time(), $next_run )
			) );
		}

		// Release lock
		delete_transient( $lock_key );
	}

	/**
	 * Calculate the next run time based on frequency and scheduled time.
	 *
	 * @since    1.0.0
	 * @param    string    $frequency       Schedule frequency (hourly, daily, weekly, twicedaily).
	 * @param    string    $scheduled_time  Scheduled time in HH:MM format.
	 * @param    DateTime  $now             Current DateTime object in WordPress timezone.
	 * @param    DateTimeZone $timezone    WordPress timezone.
	 * @return   int|false                 Unix timestamp of next run, or false on error.
	 */
	private function calculate_next_run_time( $frequency, $scheduled_time, $now, $timezone ) {
		if ( 'hourly' === $frequency ) {
			// For hourly, run 1 hour from now (or immediately if cooldown allows)
			$next_run = clone $now;
			$next_run->modify( '+1 hour' );
			$next_run->setTime( (int) $next_run->format( 'H' ), 0, 0 ); // Round to top of hour
			return $next_run->getTimestamp();
		}

		// For other frequencies, we need a scheduled time
		if ( empty( $scheduled_time ) || ! preg_match( '/^(\d{1,2}):(\d{2})$/', $scheduled_time, $matches ) ) {
			$this->log_event( 'Cannot calculate next run: Invalid scheduled time format.' );
			return false;
		}

		$scheduled_hour = (int) $matches[1];
		$scheduled_minute = (int) $matches[2];

		// Validate hour and minute
		if ( $scheduled_hour < 0 || $scheduled_hour > 23 || $scheduled_minute < 0 || $scheduled_minute > 59 ) {
			$this->log_event( sprintf( 'Cannot calculate next run: Invalid hour (%d) or minute (%d).', $scheduled_hour, $scheduled_minute ) );
			return false;
		}

		// Log parsed time for debugging
		$this->log_event( sprintf( 
			'Parsed scheduled time: Hour=%d, Minute=%d (from input: %s)', 
			$scheduled_hour, 
			$scheduled_minute,
			$scheduled_time
		) );

		// Create scheduled time for today
		$scheduled_today = clone $now;
		$scheduled_today->setTime( $scheduled_hour, $scheduled_minute, 0 );
		
		// Log what we're comparing
		$this->log_event( sprintf( 
			'Time comparison: Now=%s, Scheduled Today=%s, Diff=%d minutes', 
			$now->format( 'Y-m-d H:i:s' ),
			$scheduled_today->format( 'Y-m-d H:i:s' ),
			round( ( $scheduled_today->getTimestamp() - $now->getTimestamp() ) / 60 )
		) );

		switch ( $frequency ) {
			case 'daily':
				// If scheduled time today has passed, use tomorrow
				if ( $scheduled_today->getTimestamp() <= $now->getTimestamp() ) {
					$scheduled_today->modify( '+1 day' );
				}
				return $scheduled_today->getTimestamp();

			case 'twicedaily':
				// Run twice per day - calculate next occurrence
				// First run: scheduled time today (if not passed) or tomorrow
				// Second run: 12 hours after first run
				
				// Calculate first run time for today
				$first_run_today = clone $scheduled_today;
				
				// Calculate second run time for today (12 hours after first)
				$second_run_today = clone $first_run_today;
				$second_run_today->modify( '+12 hours' );
				
				// Log for debugging
				$this->log_event( sprintf( 
					'Twicedaily: First run today=%s, Second run today=%s, Now=%s', 
					$first_run_today->format( 'Y-m-d H:i:s' ),
					$second_run_today->format( 'Y-m-d H:i:s' ),
					$now->format( 'Y-m-d H:i:s' )
				) );
				
				// Determine which run is next
				if ( $first_run_today->getTimestamp() > $now->getTimestamp() ) {
					// First run today hasn't happened yet
					$this->log_event( sprintf( 'Twicedaily: Using first run today (%s)', $first_run_today->format( 'Y-m-d H:i:s' ) ) );
					return $first_run_today->getTimestamp();
				} elseif ( $second_run_today->getTimestamp() > $now->getTimestamp() ) {
					// Second run today hasn't happened yet
					$this->log_event( sprintf( 'Twicedaily: Using second run today (%s)', $second_run_today->format( 'Y-m-d H:i:s' ) ) );
					return $second_run_today->getTimestamp();
				} else {
					// Both runs today have passed, schedule for tomorrow
					$scheduled_today->modify( '+1 day' );
					$this->log_event( sprintf( 'Twicedaily: Both runs passed, scheduling for tomorrow (%s)', $scheduled_today->format( 'Y-m-d H:i:s' ) ) );
					return $scheduled_today->getTimestamp();
				}

			case 'weekly':
				// Run on Monday at scheduled time
				$scheduled_today->setTime( $scheduled_hour, $scheduled_minute, 0 );
				
				// Get day of week (0=Sunday, 1=Monday, etc.)
				$day_of_week = (int) $now->format( 'w' );
				
				// Calculate days until next Monday
				$days_until_monday = ( 8 - $day_of_week ) % 7;
				if ( $days_until_monday === 0 ) {
					// Today is Monday - check if scheduled time has passed
					if ( $scheduled_today->getTimestamp() <= $now->getTimestamp() ) {
						// Schedule for next Monday
						$days_until_monday = 7;
					}
				}
				
				if ( $days_until_monday > 0 ) {
					$scheduled_today->modify( "+{$days_until_monday} days" );
				}
				
				return $scheduled_today->getTimestamp();

			default:
				$this->log_event( sprintf( 'Unknown frequency: %s', $frequency ) );
				return false;
		}
	}

	/**
	 * Get scheduler status.
	 *
	 * @since    1.0.0
	 * @return   array    Status information.
	 */
	public function get_status() {
		$next_run = wp_next_scheduled( 'ai_blog_posts_scheduled_generation' );

		return array(
			'enabled'        => Ai_Blog_Posts_Settings::get( 'schedule_enabled' ),
			'frequency'      => Ai_Blog_Posts_Settings::get( 'schedule_frequency' ),
			'scheduled_time' => Ai_Blog_Posts_Settings::get( 'schedule_time' ),
			'next_run'       => $next_run ? date_i18n( 'Y-m-d H:i:s', $next_run ) : null,
			'next_run_human' => $next_run ? human_time_diff( time(), $next_run ) : null,
		);
	}

	/**
	 * Get queue statistics.
	 *
	 * @since    1.0.0
	 * @return   array    Queue stats.
	 */
	public function get_queue_stats() {
		global $wpdb;
		$table = $wpdb->prefix . 'ai_blog_posts_topics';

		return array(
			'pending'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE status = 'pending'" ),
			'completed' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE status = 'completed'" ),
			'failed'    => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE status = 'failed'" ),
			'total'     => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" ),
		);
	}
}

