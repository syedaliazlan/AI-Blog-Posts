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

		// Check if it's the right time
		if ( ! $this->is_scheduled_time() ) {
			return;
		}

		// Get and lock next topic from queue
		$topic = $this->get_and_lock_next_topic();

		if ( ! $topic ) {
			$this->log_event( 'Scheduled generation skipped: No topics in queue.' );
			return;
		}

		$this->log_event( sprintf( 'Starting scheduled generation for topic: "%s"', $topic->topic ) );

		// Generate the post
		$result = $this->generator->generate_post( $topic->topic, array(
			'keywords'      => $topic->keywords,
			'category_id'   => $topic->category_id,
			'publish'       => Ai_Blog_Posts_Settings::get( 'post_status' ) === 'publish',
			'source'        => 'scheduled',
		) );

		// Update topic status
		$this->update_topic_status( $topic->id, $result );

		// Log the result
		if ( is_wp_error( $result ) ) {
			$this->log_event( sprintf( 'Scheduled generation failed for topic "%s": %s', $topic->topic, $result->get_error_message() ) );
		} else {
			$this->log_event( sprintf( 'Scheduled generation successful: "%s" (Post ID: %d)', $result['title'], $result['post_id'] ) );
		}
	}

	/**
	 * Check if current time matches scheduled time.
	 *
	 * @since    1.0.0
	 * @return   bool
	 */
	private function is_scheduled_time() {
		$frequency = Ai_Blog_Posts_Settings::get( 'schedule_frequency' );
		$scheduled_time = Ai_Blog_Posts_Settings::get( 'schedule_time' );

		// For hourly, always run
		if ( 'hourly' === $frequency ) {
			return true;
		}

		// Get current hour
		$current_hour = (int) current_time( 'G' );
		$scheduled_hour = (int) substr( $scheduled_time, 0, 2 );

		// Allow a 1-hour window around the scheduled time
		$hour_match = abs( $current_hour - $scheduled_hour ) <= 1;

		if ( ! $hour_match ) {
			return false;
		}

		// Check day of week for weekly
		if ( 'weekly' === $frequency ) {
			$day_of_week = (int) current_time( 'w' );
			// Run on Monday (1)
			return $day_of_week === 1;
		}

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

		// First, reset any stale 'processing' topics (older than 15 minutes)
		// This handles cases where a previous run crashed
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE $table 
				SET status = 'pending' 
				WHERE status = 'processing' 
				AND locked_at < %s",
				gmdate( 'Y-m-d H:i:s', time() - 15 * MINUTE_IN_SECONDS )
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
				),
				array( 'id' => $topic_id ),
				array( '%d', '%s', '%s' ),
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
				),
				array( 'id' => $topic_id ),
				array( '%s', '%d', '%s' ),
				array( '%d' )
			);
		}
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
	 * @since    1.0.0
	 */
	public function reschedule() {
		// Clear existing schedule
		$timestamp = wp_next_scheduled( 'ai_blog_posts_scheduled_generation' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'ai_blog_posts_scheduled_generation' );
		}

		// Only reschedule if enabled
		if ( ! Ai_Blog_Posts_Settings::get( 'schedule_enabled' ) ) {
			return;
		}

		$frequency = Ai_Blog_Posts_Settings::get( 'schedule_frequency' );
		$scheduled_time = Ai_Blog_Posts_Settings::get( 'schedule_time' );

		// Calculate next run time
		$hour = (int) substr( $scheduled_time, 0, 2 );
		$minute = (int) substr( $scheduled_time, 3, 2 );

		$next_run = strtotime( sprintf( 'today %02d:%02d:00', $hour, $minute ) );
		
		// If time has passed today, schedule for tomorrow
		if ( $next_run < time() ) {
			$next_run = strtotime( sprintf( 'tomorrow %02d:%02d:00', $hour, $minute ) );
		}

		wp_schedule_event( $next_run, $frequency, 'ai_blog_posts_scheduled_generation' );
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

