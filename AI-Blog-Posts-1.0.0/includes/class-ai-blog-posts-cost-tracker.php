<?php

/**
 * Cost tracking and logging functionality
 *
 * @link       https://devonicweb.co.uk/
 * @since      1.0.0
 *
 * @package    Ai_Blog_Posts
 * @subpackage Ai_Blog_Posts/includes
 */

/**
 * Handles cost tracking and generation logging.
 *
 * Records all API usage, calculates costs, and provides statistics.
 *
 * @since      1.0.0
 * @package    Ai_Blog_Posts
 * @subpackage Ai_Blog_Posts/includes
 * @author     Ali Azlan <contact@devonicweb.co.uk>
 */
class Ai_Blog_Posts_Cost_Tracker {

	/**
	 * The database table name.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string
	 */
	private $table_name;

	/**
	 * Initialize the class.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'ai_blog_posts_logs';
	}

	/**
	 * Log a generation attempt.
	 *
	 * @since    1.0.0
	 * @param    array $data    Log data.
	 * @return   int|false      Log ID on success, false on failure.
	 */
	public function log( $data ) {
		global $wpdb;

		$defaults = array(
			'post_id'           => null,
			'model_used'        => '',
			'prompt_tokens'     => 0,
			'completion_tokens' => 0,
			'total_tokens'      => 0,
			'cost_usd'          => 0,
			'image_cost_usd'    => 0,
			'generation_time'   => 0,
			'topic_source'      => 'manual',
			'status'            => 'success',
			'error_message'     => null,
			'created_at'        => current_time( 'mysql' ),
		);

		$data = wp_parse_args( $data, $defaults );

		// Calculate total tokens if not provided
		if ( empty( $data['total_tokens'] ) ) {
			$data['total_tokens'] = $data['prompt_tokens'] + $data['completion_tokens'];
		}

		$inserted = $wpdb->insert(
			$this->table_name,
			array(
				'post_id'           => $data['post_id'],
				'model_used'        => $data['model_used'],
				'prompt_tokens'     => $data['prompt_tokens'],
				'completion_tokens' => $data['completion_tokens'],
				'total_tokens'      => $data['total_tokens'],
				'cost_usd'          => $data['cost_usd'],
				'image_cost_usd'    => $data['image_cost_usd'],
				'generation_time'   => $data['generation_time'],
				'topic_source'      => $data['topic_source'],
				'status'            => $data['status'],
				'error_message'     => $data['error_message'],
				'created_at'        => $data['created_at'],
			),
			array( '%d', '%s', '%d', '%d', '%d', '%f', '%f', '%f', '%s', '%s', '%s', '%s' )
		);

		if ( $inserted ) {
			$this->check_budget_limit( $data['cost_usd'] + $data['image_cost_usd'] );
			return $wpdb->insert_id;
		}

		return false;
	}

	/**
	 * Get generation logs.
	 *
	 * @since    1.0.0
	 * @param    int   $page        Page number.
	 * @param    int   $per_page    Items per page.
	 * @param    array $filters     Optional filters.
	 * @return   array              Logs and total count.
	 */
	public function get_logs( $page = 1, $per_page = 20, $filters = array() ) {
		global $wpdb;

		$offset = ( $page - 1 ) * $per_page;
		$where = array( '1=1' );
		$values = array();

		// Apply filters
		if ( ! empty( $filters['status'] ) ) {
			$where[] = 'status = %s';
			$values[] = $filters['status'];
		}

		if ( ! empty( $filters['model'] ) ) {
			$where[] = 'model_used = %s';
			$values[] = $filters['model'];
		}

		if ( ! empty( $filters['date_from'] ) ) {
			$where[] = 'created_at >= %s';
			$values[] = $filters['date_from'];
		}

		if ( ! empty( $filters['date_to'] ) ) {
			$where[] = 'created_at <= %s';
			$values[] = $filters['date_to'] . ' 23:59:59';
		}

		$where_clause = implode( ' AND ', $where );

		// Get total count
		$count_query = "SELECT COUNT(*) FROM {$this->table_name} WHERE $where_clause";
		if ( ! empty( $values ) ) {
			$count_query = $wpdb->prepare( $count_query, $values );
		}
		$total = $wpdb->get_var( $count_query );

		// Get logs
		$query = "SELECT * FROM {$this->table_name} WHERE $where_clause ORDER BY created_at DESC LIMIT %d OFFSET %d";
		$values[] = $per_page;
		$values[] = $offset;

		$logs = $wpdb->get_results( $wpdb->prepare( $query, $values ) );

		return array(
			'logs'  => $logs,
			'total' => (int) $total,
		);
	}

	/**
	 * Get usage statistics.
	 *
	 * @since    1.0.0
	 * @return   array    Statistics array.
	 */
	public function get_stats() {
		global $wpdb;

		$stats = array();

		// All time stats
		$all_time = $wpdb->get_row(
			"SELECT 
				COUNT(*) as total_posts,
				SUM(total_tokens) as total_tokens,
				SUM(cost_usd) as total_text_cost,
				SUM(image_cost_usd) as total_image_cost,
				SUM(cost_usd + image_cost_usd) as total_cost,
				AVG(cost_usd + image_cost_usd) as avg_cost
			FROM {$this->table_name}
			WHERE status = 'success'"
		);

		$stats['total_posts'] = (int) ( $all_time->total_posts ?? 0 );
		$stats['total_tokens'] = (int) ( $all_time->total_tokens ?? 0 );
		$stats['total_cost'] = (float) ( $all_time->total_cost ?? 0 );
		$stats['avg_cost'] = (float) ( $all_time->avg_cost ?? 0 );

		// This month
		$month_start = date( 'Y-m-01 00:00:00' );
		$this_month = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT 
					COUNT(*) as posts,
					SUM(cost_usd + image_cost_usd) as cost
				FROM {$this->table_name}
				WHERE status = 'success' AND created_at >= %s",
				$month_start
			)
		);

		$stats['posts_this_month'] = (int) ( $this_month->posts ?? 0 );
		$stats['cost_this_month'] = (float) ( $this_month->cost ?? 0 );

		// This week
		$week_start = date( 'Y-m-d 00:00:00', strtotime( 'monday this week' ) );
		$this_week = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT 
					COUNT(*) as posts,
					SUM(cost_usd + image_cost_usd) as cost
				FROM {$this->table_name}
				WHERE status = 'success' AND created_at >= %s",
				$week_start
			)
		);

		$stats['posts_this_week'] = (int) ( $this_week->posts ?? 0 );
		$stats['cost_this_week'] = (float) ( $this_week->cost ?? 0 );

		// Today
		$today = date( 'Y-m-d 00:00:00' );
		$today_stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT 
					COUNT(*) as posts,
					SUM(cost_usd + image_cost_usd) as cost
				FROM {$this->table_name}
				WHERE status = 'success' AND created_at >= %s",
				$today
			)
		);

		$stats['posts_today'] = (int) ( $today_stats->posts ?? 0 );
		$stats['cost_today'] = (float) ( $today_stats->cost ?? 0 );

		// Model breakdown
		$by_model = $wpdb->get_results(
			"SELECT 
				model_used,
				COUNT(*) as count,
				SUM(total_tokens) as tokens,
				SUM(cost_usd + image_cost_usd) as cost
			FROM {$this->table_name}
			WHERE status = 'success'
			GROUP BY model_used
			ORDER BY count DESC"
		);

		$stats['by_model'] = $by_model;

		return $stats;
	}

	/**
	 * Get the cost for the current month.
	 *
	 * @since    1.0.0
	 * @return   float    Monthly cost.
	 */
	public function get_monthly_cost() {
		global $wpdb;

		$month_start = date( 'Y-m-01 00:00:00' );

		return (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(cost_usd + image_cost_usd) 
				FROM {$this->table_name} 
				WHERE status = 'success' AND created_at >= %s",
				$month_start
			)
		);
	}

	/**
	 * Check if budget limit is approaching or exceeded.
	 *
	 * @since    1.0.0
	 * @param    float $new_cost    Cost of the latest generation.
	 */
	private function check_budget_limit( $new_cost ) {
		$budget_limit = Ai_Blog_Posts_Settings::get( 'budget_limit' );

		if ( $budget_limit <= 0 ) {
			return; // No limit set
		}

		$monthly_cost = $this->get_monthly_cost();
		$percentage = ( $monthly_cost / $budget_limit ) * 100;

		// Send alert at 80%
		if ( $percentage >= 80 && $percentage < 100 ) {
			$this->send_budget_alert( 'approaching', $monthly_cost, $budget_limit );
		}

		// Send alert and pause at 100%
		if ( $percentage >= 100 ) {
			$this->send_budget_alert( 'exceeded', $monthly_cost, $budget_limit );
			// Disable auto-posting
			Ai_Blog_Posts_Settings::set( 'schedule_enabled', false );
		}
	}

	/**
	 * Send budget alert email.
	 *
	 * @since    1.0.0
	 * @param    string $type      Alert type ('approaching' or 'exceeded').
	 * @param    float  $current   Current monthly cost.
	 * @param    float  $limit     Budget limit.
	 */
	private function send_budget_alert( $type, $current, $limit ) {
		$email = Ai_Blog_Posts_Settings::get( 'budget_alert_email' );
		if ( empty( $email ) ) {
			$email = get_option( 'admin_email' );
		}

		$site_name = get_bloginfo( 'name' );

		if ( 'approaching' === $type ) {
			$subject = sprintf(
				/* translators: %s: site name */
				__( '[%s] AI Blog Posts Budget Alert - 80%% Reached', 'ai-blog-posts' ),
				$site_name
			);
			$message = sprintf(
				/* translators: 1: current cost, 2: budget limit */
				__( 'Your AI Blog Posts monthly spending has reached 80%% of your budget limit.

Current Spending: $%1$s
Budget Limit: $%2$s

You may want to review your settings or increase your budget limit.', 'ai-blog-posts' ),
				number_format( $current, 2 ),
				number_format( $limit, 2 )
			);
		} else {
			$subject = sprintf(
				/* translators: %s: site name */
				__( '[%s] AI Blog Posts Budget Exceeded - Auto-posting Paused', 'ai-blog-posts' ),
				$site_name
			);
			$message = sprintf(
				/* translators: 1: current cost, 2: budget limit */
				__( 'Your AI Blog Posts monthly spending has exceeded your budget limit.

Current Spending: $%1$s
Budget Limit: $%2$s

Auto-posting has been automatically paused. You can resume it by increasing your budget limit or waiting until next month.', 'ai-blog-posts' ),
				number_format( $current, 2 ),
				number_format( $limit, 2 )
			);
		}

		// Only send once per type per day
		$transient_key = 'ai_blog_posts_budget_alert_' . $type;
		if ( ! get_transient( $transient_key ) ) {
			wp_mail( $email, $subject, $message );
			set_transient( $transient_key, true, DAY_IN_SECONDS );
		}
	}

	/**
	 * Export logs to CSV.
	 *
	 * @since    1.0.0
	 * @param    array $filters    Optional filters.
	 * @return   string            CSV content.
	 */
	public function export_csv( $filters = array() ) {
		global $wpdb;

		$where = array( '1=1' );
		$values = array();

		if ( ! empty( $filters['date_from'] ) ) {
			$where[] = 'created_at >= %s';
			$values[] = $filters['date_from'];
		}

		if ( ! empty( $filters['date_to'] ) ) {
			$where[] = 'created_at <= %s';
			$values[] = $filters['date_to'] . ' 23:59:59';
		}

		$where_clause = implode( ' AND ', $where );

		$query = "SELECT * FROM {$this->table_name} WHERE $where_clause ORDER BY created_at DESC";
		if ( ! empty( $values ) ) {
			$query = $wpdb->prepare( $query, $values );
		}

		$logs = $wpdb->get_results( $query, ARRAY_A );

		if ( empty( $logs ) ) {
			return '';
		}

		// Build CSV
		$output = fopen( 'php://temp', 'r+' );

		// Headers
		fputcsv( $output, array(
			'Date',
			'Post ID',
			'Post Title',
			'Model',
			'Prompt Tokens',
			'Completion Tokens',
			'Total Tokens',
			'Text Cost ($)',
			'Image Cost ($)',
			'Total Cost ($)',
			'Generation Time (s)',
			'Source',
			'Status',
			'Error',
		) );

		// Data
		foreach ( $logs as $log ) {
			$post_title = $log['post_id'] ? get_the_title( $log['post_id'] ) : '';
			fputcsv( $output, array(
				$log['created_at'],
				$log['post_id'],
				$post_title,
				$log['model_used'],
				$log['prompt_tokens'],
				$log['completion_tokens'],
				$log['total_tokens'],
				$log['cost_usd'],
				$log['image_cost_usd'],
				$log['cost_usd'] + $log['image_cost_usd'],
				$log['generation_time'],
				$log['topic_source'],
				$log['status'],
				$log['error_message'],
			) );
		}

		rewind( $output );
		$csv = stream_get_contents( $output );
		fclose( $output );

		return $csv;
	}

	/**
	 * Get posts generated today.
	 *
	 * @since    1.0.0
	 * @return   int    Count of posts.
	 */
	public function get_posts_generated_today() {
		global $wpdb;

		$today = date( 'Y-m-d 00:00:00' );

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table_name} 
				WHERE status = 'success' AND created_at >= %s",
				$today
			)
		);
	}

	/**
	 * Check if we can generate more posts today.
	 *
	 * @since    1.0.0
	 * @return   bool    True if limit not reached.
	 */
	public function can_generate_today() {
		$max_per_day = Ai_Blog_Posts_Settings::get( 'max_posts_per_day' );
		$generated_today = $this->get_posts_generated_today();

		return $generated_today < $max_per_day;
	}

	/**
	 * Check if budget allows generation.
	 *
	 * @since    1.0.0
	 * @return   bool    True if within budget.
	 */
	public function within_budget() {
		$budget_limit = Ai_Blog_Posts_Settings::get( 'budget_limit' );

		if ( $budget_limit <= 0 ) {
			return true; // No limit
		}

		return $this->get_monthly_cost() < $budget_limit;
	}
}

