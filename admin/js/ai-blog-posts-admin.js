/**
 * AI Blog Posts Admin JavaScript
 *
 * @link       https://devonicweb.co.uk/
 * @since      1.0.0
 * @package    Ai_Blog_Posts
 */

(function($) {
	'use strict';

	/**
	 * AI Blog Posts Admin Module
	 */
	const AIBlogPosts = {
		/**
		 * Initialize
		 */
		init: function() {
			this.bindEvents();
			this.initTabs();
			this.initToggles();
		},

		/**
		 * Escape HTML to prevent XSS
		 */
		escapeHtml: function(text) {
			if (!text) return '';
			const div = document.createElement('div');
			div.textContent = text;
			return div.innerHTML;
		},

		/**
		 * Bind all event handlers
		 */
		bindEvents: function() {
			// API Key verification
			$('#verify-api-key').on('click', this.verifyApiKey.bind(this));
			$('#toggle-api-key').on('click', this.toggleApiKeyVisibility.bind(this));

			// Settings form
			$('#ai-blog-posts-settings-form').on('submit', this.saveSettings.bind(this));

			// Model selector pricing display
			$('#model').on('change', this.updateModelPricing);

			// Generate post
			$('#generate-post-form').on('submit', this.generatePost.bind(this));
			$('#add-to-queue-btn').on('click', this.addToQueue.bind(this));
			$('#generate-another-btn, #retry-btn').on('click', this.resetGenerateForm);
			$('#close-preview').on('click', this.closePreview);

			// Topics
			$('#add-topic-form').on('submit', this.addTopic.bind(this));
			$(document).on('click', '.delete-topic', this.deleteTopic.bind(this));
			$(document).on('click', '.generate-topic, .retry-topic', this.generateFromTopic.bind(this));
			$('#fetch-trending').on('click', this.fetchTrending.bind(this));
			$('#add-selected-trends').on('click', this.addSelectedTrends.bind(this));
			$('#select-all-topics').on('change', this.toggleAllTopics);
			$('#apply-bulk').on('click', this.applyBulkAction.bind(this));
			
			// Select all checkbox for topic table
			$('.topics-table thead .topic-checkbox').on('change', function() {
				$('.topics-table tbody .topic-checkbox').prop('checked', $(this).is(':checked'));
			});

			// Modals
			$('#bulk-import').on('click', function() { $('#csv-import-modal').show(); });
			$('.modal-close, .modal-cancel').on('click', function() { $(this).closest('.ai-blog-posts-modal').hide(); });

			// Website analysis (both quick and AI-powered buttons)
			$('#analyze-website, #analyze-website-ai').on('click', this.analyzeWebsite.bind(this));

			// Export CSV and Clear Logs
			$('#export-csv').on('click', this.exportLogs.bind(this));
			$('#clear-logs').on('click', this.clearLogs.bind(this));
		},

		/**
		 * Initialize tab navigation
		 */
		initTabs: function() {
			$('.nav-tab').on('click', function(e) {
				e.preventDefault();
				const tab = $(this).attr('href').split('tab=')[1];
				if (tab) {
					$('.nav-tab').removeClass('nav-tab-active');
					$(this).addClass('nav-tab-active');
					$('.settings-tab').removeClass('active');
					$('.settings-tab[data-tab="' + tab + '"]').addClass('active');
					
					// Update URL without reload
					const url = new URL(window.location);
					url.searchParams.set('tab', tab);
					window.history.pushState({}, '', url);
				}
			});
		},

		/**
		 * Initialize toggle switches
		 */
		initToggles: function() {
			// Image settings toggle
			$('#image_enabled').on('change', function() {
				$('.image-settings').toggle($(this).is(':checked'));
			});

			// Schedule settings toggle
			$('#schedule_enabled').on('change', function() {
				$('.schedule-settings').toggle($(this).is(':checked'));
			});

			// Trending settings toggle
			$('#trending_enabled').on('change', function() {
				$('.trending-settings').toggle($(this).is(':checked'));
			});
		},

		/**
		 * Verify API Key
		 */
		verifyApiKey: function(e) {
			e.preventDefault();
			
			const $button = $('#verify-api-key');
			const $status = $('#api-key-status');
			const apiKey = $('#api_key').val().trim();

			if (!apiKey) {
				$status.html('<span class="status-warning">Please enter an API key.</span>');
				return;
			}

			$button.prop('disabled', true).text(aiBlogPosts.strings.verifying);

			$.ajax({
				url: aiBlogPosts.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ai_blog_posts_verify_api',
					nonce: aiBlogPosts.nonce,
					api_key: apiKey
				},
				success: function(response) {
					if (response.success) {
						$status.html('<span class="status-success"><span class="dashicons dashicons-yes-alt"></span> ' + response.data.message + '</span>');
						// Key is saved - show success but keep the key visible
					} else {
						$status.html('<span class="status-error"><span class="dashicons dashicons-warning"></span> ' + response.data.message + '</span>');
					}
				},
				error: function() {
					$status.html('<span class="status-error">Connection error. Please try again.</span>');
				},
				complete: function() {
					$button.prop('disabled', false).text('Verify Key');
				}
			});
		},

		/**
		 * Toggle API key visibility
		 */
		toggleApiKeyVisibility: function() {
			const $input = $('#api_key');
			const $icon = $(this).find('.dashicons');
			
			if ($input.attr('type') === 'password') {
				$input.attr('type', 'text');
				$icon.removeClass('dashicons-visibility').addClass('dashicons-hidden');
			} else {
				$input.attr('type', 'password');
				$icon.removeClass('dashicons-hidden').addClass('dashicons-visibility');
			}
		},

		/**
		 * Update model pricing display
		 */
		updateModelPricing: function() {
			const model = $(this).val();
			$('.model-price').hide();
			$('.model-price[data-model="' + model + '"]').show();
		},

		/**
		 * Save settings
		 */
		saveSettings: function(e) {
			e.preventDefault();

			const $button = $('#save-settings');
			const $status = $('.save-status');
			const $form = $(e.target);

			$button.prop('disabled', true);
			$status.removeClass('success').text(aiBlogPosts.strings.saving);

			// Collect settings
			const settings = {};
			$form.find('input, select, textarea').each(function() {
				const $input = $(this);
				const name = $input.attr('name');
				
				if (!name) return;

				if ($input.attr('type') === 'checkbox') {
					// Send "1" or "0" for checkboxes - cleaner for PHP handling
					settings[name] = $input.is(':checked') ? '1' : '0';
				} else if ($input.attr('multiple')) {
					settings[name] = $input.val() || [];
				} else {
					settings[name] = $input.val();
				}
			});

			$.ajax({
				url: aiBlogPosts.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ai_blog_posts_save_settings',
					nonce: aiBlogPosts.nonce,
					settings: settings
				},
				success: function(response) {
					if (response.success) {
						$status.addClass('success').text(aiBlogPosts.strings.success + ' Settings saved.');
					} else {
						$status.text(aiBlogPosts.strings.error + ': ' + response.data.message);
					}
				},
				error: function() {
					$status.text('Connection error. Please try again.');
				},
				complete: function() {
					$button.prop('disabled', false);
					setTimeout(function() {
						$status.text('');
					}, 3000);
				}
			});
		},

		/**
		 * Generate post
		 */
		generatePost: function(e) {
			e.preventDefault();

			const $form = $(e.target);
			const $preview = $('#preview-container');
			const $progress = $('#generation-progress');
			const $content = $('#preview-content');
			const $error = $('#preview-error');

			// Show preview container
			$preview.show();
			$progress.show();
			$content.hide();
			$error.hide();

			// Reset progress
			$('.progress-step').removeClass('active complete');
			$('#progress-fill').css('width', '0%');

			// Start progress animation
			const steps = ['outline', 'content', 'humanize', 'seo', 'image', 'complete'];
			let currentStep = 0;

			const progressInterval = setInterval(function() {
				if (currentStep < steps.length - 1) {
					$('.progress-step[data-step="' + steps[currentStep] + '"]').removeClass('active').addClass('complete');
					currentStep++;
					$('.progress-step[data-step="' + steps[currentStep] + '"]').addClass('active');
					$('#progress-fill').css('width', ((currentStep + 1) / steps.length * 100) + '%');
					$('#progress-status').text('Step ' + (currentStep + 1) + ' of ' + steps.length + '...');
				}
			}, 3000);

			// Collect form data
			const data = {
				action: 'ai_blog_posts_generate_post',
				nonce: aiBlogPosts.nonce,
				topic: $('#topic').val(),
				keywords: $('#keywords').val(),
				additional_instructions: $('#additional_instructions').val(),
				category_id: $('#category_id').val(),
				model: $('#model').val(),
				post_status: $('#post_status').val(),
				generate_image: $('#generate_image').is(':checked')
			};
			
			// Include queue topic ID if generating from queue
			const queueTopicId = $('#queue_topic_id').val();
			if (queueTopicId) {
				data.queue_topic_id = queueTopicId;
			}

			$.ajax({
				url: aiBlogPosts.ajaxUrl,
				type: 'POST',
				data: data,
				timeout: 300000, // 5 minutes
				success: function(response) {
					clearInterval(progressInterval);
					
					if (response.success) {
						// Complete all steps
						$('.progress-step').removeClass('active').addClass('complete');
						$('#progress-fill').css('width', '100%');
						$('#progress-status').text('Complete!');

						setTimeout(function() {
							$progress.hide();
							$content.show();

							// Populate preview
							$('#result-model').text(response.data.model);
							$('#result-tokens').text(response.data.tokens.toLocaleString());
							$('#result-cost').text(response.data.cost_usd.toFixed(4));
							$('#result-time').text(response.data.generation_time);
							$('#preview-title-text').text(response.data.title);
							$('#preview-body').html(response.data.content_preview);
							$('#edit-post-btn').attr('href', response.data.edit_url);
							$('#view-post-btn').attr('href', response.data.view_url);
						}, 1000);
					} else {
						AIBlogPosts.showError(response.data.message);
					}
				},
				error: function(xhr, status, error) {
					clearInterval(progressInterval);
					AIBlogPosts.showError('Request failed: ' + (error || 'Unknown error'));
				}
			});
		},

		/**
		 * Show error in preview
		 */
		showError: function(message) {
			$('#generation-progress').hide();
			$('#preview-content').hide();
			$('#preview-error').show();
			$('#error-message').text(message);
		},

		/**
		 * Reset generate form
		 */
		resetGenerateForm: function() {
			$('#preview-container').hide();
			$('#topic').val('').focus();
		},

		/**
		 * Close preview
		 */
		closePreview: function() {
			$('#preview-container').hide();
		},

		/**
		 * Add topic to queue
		 */
		addToQueue: function(e) {
			e.preventDefault();
			
			const topic = $('#topic').val();
			if (!topic) {
				alert('Please enter a topic.');
				return;
			}

			$.ajax({
				url: aiBlogPosts.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ai_blog_posts_add_topic',
					nonce: aiBlogPosts.nonce,
					topic: topic,
					keywords: $('#keywords').val(),
					category_id: $('#category_id').val(),
					priority: 50
				},
				success: function(response) {
					if (response.success) {
						alert('Topic added to queue!');
						$('#topic').val('');
						$('#keywords').val('');
					} else {
						alert('Error: ' + response.data.message);
					}
				},
				error: function() {
					alert('Connection error.');
				}
			});
		},

		/**
		 * Add topic from topics page
		 */
		addTopic: function(e) {
			e.preventDefault();
			
			const $form = $(e.target);
			const $button = $form.find('button[type="submit"]');

			$button.prop('disabled', true);

			$.ajax({
				url: aiBlogPosts.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ai_blog_posts_add_topic',
					nonce: aiBlogPosts.nonce,
					topic: $('#new-topic').val(),
					keywords: $('#new-keywords').val(),
					category_id: $('#new-category').val(),
					priority: $('#new-priority').val() || 0
				},
				success: function(response) {
					if (response.success) {
						location.reload();
					} else {
						alert('Error: ' + response.data.message);
					}
				},
				error: function() {
					alert('Connection error.');
				},
				complete: function() {
					$button.prop('disabled', false);
				}
			});
		},

		/**
		 * Delete topic
		 */
		deleteTopic: function(e) {
			e.preventDefault();

			if (!confirm(aiBlogPosts.strings.confirmDelete)) {
				return;
			}

			const $link = $(e.target);
			const topicId = $link.data('id');
			const $row = $link.closest('tr');

			$.ajax({
				url: aiBlogPosts.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ai_blog_posts_delete_topic',
					nonce: aiBlogPosts.nonce,
					topic_id: topicId
				},
				success: function(response) {
					if (response.success) {
						$row.fadeOut(function() { $(this).remove(); });
					} else {
						alert('Error: ' + response.data.message);
					}
				},
				error: function() {
					alert('Connection error.');
				}
			});
		},

		/**
		 * Generate from topic (uses AJAX to update queue status)
		 */
		generateFromTopic: function(e) {
			e.preventDefault();
			e.stopPropagation();
			
			// Get the clicked link - handle both direct click and child element click
			let $link = $(e.target);
			if (!$link.hasClass('generate-topic') && !$link.hasClass('retry-topic')) {
				$link = $link.closest('.generate-topic, .retry-topic');
			}
			
			if (!$link.length) return;
			
			const $row = $link.closest('tr');
			const topicId = $link.data('id');
			const topicText = $row.find('.column-topic strong').text().trim();
			const isRetry = $link.hasClass('retry-topic');
			
			// Prevent double-clicks
			if ($row.hasClass('generating')) {
				return;
			}
			
			if (!confirm((isRetry ? 'Retry generating' : 'Generate') + ' a post for "' + topicText + '"?')) {
				return;
			}

			// Show generating state - replace all row actions with spinner
			const $rowActions = $row.find('.row-actions');
			$rowActions.html(
				'<span class="generating-indicator">' +
				'<span class="spinner is-active" style="float:none;margin:0 5px 0 0;visibility:visible;"></span>' +
				'<span style="color:#0073aa;font-weight:500;">Generating...</span>' +
				'</span>'
			);
			$row.addClass('generating');
			
			// Update status badge to "Generating"
			$row.find('.column-status').html('<span class="status-badge generating">Generating</span>');

			// Use AJAX to generate and update queue status
			$.ajax({
				url: aiBlogPosts.ajaxUrl,
				type: 'POST',
				timeout: 300000, // 5 minutes
				data: {
					action: 'ai_blog_posts_generate_from_queue',
					nonce: aiBlogPosts.nonce,
					topic_id: topicId
				},
				success: function(response) {
					$row.removeClass('generating');
					
					if (response.success) {
						$row.addClass('generated');
						// Update status to Completed
						$row.find('.column-status').html('<span class="status-badge completed">Completed</span>');
						
						// Update row actions with View Post and Edit links
						let newActions = '';
						if (response.data.post_url) {
							newActions += '<span class="view"><a href="' + response.data.post_url + '" target="_blank">View Post</a> | </span>';
						}
						if (response.data.edit_url) {
							newActions += '<span class="edit"><a href="' + response.data.edit_url + '">Edit</a> | </span>';
						}
						newActions += '<span class="delete"><a href="#" class="delete-topic" data-id="' + topicId + '">Delete</a></span>';
						$rowActions.html(newActions);
						
					} else {
						$row.addClass('generation-failed');
						const errorMsg = response.data.message || 'Unknown error';
						// Update status to Failed with error tooltip
						$row.find('.column-status').html(
							'<span class="status-badge failed">Failed</span> ' +
							'<span class="error-tooltip" title="' + AIBlogPosts.escapeHtml(errorMsg) + '">' +
							'<span class="dashicons dashicons-info"></span></span>'
						);
						// Show Retry and Delete buttons
						$rowActions.html(
							'<span class="retry"><a href="#" class="retry-topic" data-id="' + topicId + '">Retry</a> | </span>' +
							'<span class="delete"><a href="#" class="delete-topic" data-id="' + topicId + '">Delete</a></span>'
						);
					}
				},
				error: function(xhr, status) {
					$row.removeClass('generating').addClass('generation-failed');
					
					let errorMsg = 'Connection error';
					if (status === 'timeout') {
						errorMsg = 'Generation timed out. Please try again.';
					}
					
					// Update status to Failed
					$row.find('.column-status').html(
						'<span class="status-badge failed">Failed</span> ' +
						'<span class="error-tooltip" title="' + AIBlogPosts.escapeHtml(errorMsg) + '">' +
						'<span class="dashicons dashicons-info"></span></span>'
					);
					// Show Retry and Delete buttons
					$rowActions.html(
						'<span class="retry"><a href="#" class="retry-topic" data-id="' + topicId + '">Retry</a> | </span>' +
						'<span class="delete"><a href="#" class="delete-topic" data-id="' + topicId + '">Delete</a></span>'
					);
				}
			});
		},

		/**
		 * Apply bulk action to selected topics
		 */
		applyBulkAction: function(e) {
			e.preventDefault();

			const action = $('#bulk-action').val();
			if (!action) {
				alert('Please select a bulk action.');
				return;
			}

			// Get selected topic IDs
			const selectedIds = [];
			$('.topics-table tbody .topic-checkbox:checked').each(function() {
				selectedIds.push($(this).val());
			});

			if (selectedIds.length === 0) {
				alert('Please select at least one topic.');
				return;
			}

			if (action === 'delete') {
				this.bulkDeleteTopics(selectedIds);
			} else if (action === 'generate') {
				this.bulkGenerateTopics(selectedIds);
			}
		},

		/**
		 * Bulk delete topics
		 */
		bulkDeleteTopics: function(ids) {
			if (!confirm('Are you sure you want to delete ' + ids.length + ' topic(s)?')) {
				return;
			}

			const $button = $('#apply-bulk');
			$button.prop('disabled', true).text('Deleting...');

			$.ajax({
				url: aiBlogPosts.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ai_blog_posts_bulk_delete_topics',
					nonce: aiBlogPosts.nonce,
					topic_ids: ids
				},
				success: function(response) {
					if (response.success) {
						alert(response.data.message);
						location.reload();
					} else {
						alert('Error: ' + response.data.message);
					}
				},
				error: function() {
					alert('Connection error.');
				},
				complete: function() {
					$button.prop('disabled', false).text('Apply');
				}
			});
		},

		/**
		 * Bulk generate topics (one at a time)
		 */
		bulkGenerateTopics: function(ids) {
			if (!confirm('Generate posts for ' + ids.length + ' topic(s)? This may take several minutes.')) {
				return;
			}

			const $button = $('#apply-bulk');
			$button.prop('disabled', true);

			let completed = 0;
			let failed = 0;
			let index = 0;

			const generateNext = function() {
				if (index >= ids.length) {
					$button.prop('disabled', false).text('Apply');
					alert('Generation complete! ' + completed + ' succeeded, ' + failed + ' failed.');
					location.reload();
					return;
				}

				const topicId = ids[index];
				const $row = $('.topic-checkbox[value="' + topicId + '"]').closest('tr');
				const topicText = $row.find('.column-topic strong').text();

				$button.text('Generating ' + (index + 1) + '/' + ids.length + '...');
				$row.addClass('generating');

				$.ajax({
					url: aiBlogPosts.ajaxUrl,
					type: 'POST',
					timeout: 300000, // 5 minutes timeout
					data: {
						action: 'ai_blog_posts_generate_from_queue',
						nonce: aiBlogPosts.nonce,
						topic_id: topicId
					},
					success: function(response) {
						if (response.success) {
							completed++;
							$row.removeClass('generating').addClass('generated');
						} else {
							failed++;
							$row.removeClass('generating').addClass('generation-failed');
							console.error('Failed to generate:', topicText, response.data.message);
						}
					},
					error: function() {
						failed++;
						$row.removeClass('generating').addClass('generation-failed');
					},
					complete: function() {
						index++;
						generateNext();
					}
				});
			};

			generateNext();
		},

		/**
		 * Fetch trending topics
		 */
		fetchTrending: function(e, forceRefresh) {
			e.preventDefault();
			
			const $modal = $('#trending-modal');
			const $loading = $('#trending-loading');
			const $list = $('#trending-list');
			const self = this;

			// Determine if force refresh
			const isForceRefresh = forceRefresh === true || $(e.target).attr('id') === 'refresh-trending';

			$modal.show();
			$loading.show();
			$list.hide().empty();

			$.ajax({
				url: aiBlogPosts.ajaxUrl,
				type: 'POST',
				timeout: 30000,
				data: {
					action: 'ai_blog_posts_fetch_trending',
					nonce: aiBlogPosts.nonce,
					force_refresh: isForceRefresh ? '1' : '0'
				},
				success: function(response) {
					$loading.hide();

					if (response.success && response.data.topics && response.data.topics.length > 0) {
						// Check source type for header message
						const source = response.data.topics[0].source || 'trending';
						let headerMsg = '';
						let sourceLabel = '';
						if (source === 'curated') {
							headerMsg = '<p class="topics-source-note"><span class="dashicons dashicons-lightbulb"></span> Curated evergreen topics based on your categories.</p>';
							sourceLabel = 'curated';
						} else if (source === 'ai_generated') {
							headerMsg = '<p class="topics-source-note"><span class="dashicons dashicons-superhero-alt"></span> AI-generated trending topics for your region.</p>';
							sourceLabel = 'AI';
						} else {
							headerMsg = '<p class="topics-source-note"><span class="dashicons dashicons-chart-line"></span> Live trending topics from Google Trends.</p>';
							sourceLabel = 'Google';
						}
						
						// Add refresh button
						headerMsg += '<p class="topics-refresh"><button type="button" id="refresh-trending" class="button button-small"><span class="dashicons dashicons-update"></span> Get Fresh Topics</button></p>';
						
						let html = headerMsg + '<div class="trending-topics-list">';
						response.data.topics.forEach(function(topic, index) {
							const title = topic.title || topic;
							const traffic = topic.traffic || '';
							
							html += '<label class="trending-item">';
							html += '<input type="checkbox" value="' + AIBlogPosts.escapeHtml(title) + '" checked>';
							html += '<span class="topic-title">' + AIBlogPosts.escapeHtml(title) + '</span>';
							if (traffic) {
								html += '<span class="topic-traffic">' + AIBlogPosts.escapeHtml(traffic) + '</span>';
							}
							html += '</label>';
						});
						html += '</div>';
						html += '<p class="select-hint"><small>Uncheck topics you don\'t want to add to your queue.</small></p>';
						$list.html(html).show();
						
						// Bind refresh button
						$('#refresh-trending').on('click', function(e) {
							self.fetchTrending(e, true);
						});
					} else {
						$list.html('<div class="no-topics-message"><span class="dashicons dashicons-warning"></span><p>No topics available. Please try again later.</p></div>').show();
					}
				},
				error: function(xhr, status, error) {
					$loading.hide();
					let errorMsg = 'Error fetching topics.';
					if (status === 'timeout') {
						errorMsg = 'Request timed out. Please try again.';
					}
					$list.html('<div class="no-topics-message"><span class="dashicons dashicons-warning"></span><p>' + errorMsg + '</p></div>').show();
				}
			});
		},

		/**
		 * Add selected trending topics to queue
		 */
		addSelectedTrends: function(e) {
			e.preventDefault();

			const $button = $(e.target);
			const $modal = $('#trending-modal');
			const selectedTopics = [];

			// Collect all checked topics
			$('#trending-list input[type="checkbox"]:checked').each(function() {
				selectedTopics.push($(this).val());
			});

			if (selectedTopics.length === 0) {
				alert('Please select at least one topic to add.');
				return;
			}

			$button.prop('disabled', true).text('Adding...');

			// Add topics one by one
			let addedCount = 0;
			let failedCount = 0;
			let processed = 0;

			selectedTopics.forEach(function(topic) {
				// Auto-generate keywords from topic title
				const keywords = AIBlogPosts.extractKeywords(topic);
				
				$.ajax({
					url: aiBlogPosts.ajaxUrl,
					type: 'POST',
					data: {
						action: 'ai_blog_posts_add_topic',
						nonce: aiBlogPosts.nonce,
						topic: topic,
						keywords: keywords,
						category_id: 0,
						priority: 0
					},
					success: function(response) {
						if (response.success) {
							addedCount++;
						} else {
							failedCount++;
						}
					},
					error: function() {
						failedCount++;
					},
					complete: function() {
						processed++;
						
						// All done?
						if (processed === selectedTopics.length) {
							$button.prop('disabled', false).text('Add Selected');
							$modal.hide();
							
							// Show result message
							let msg = addedCount + ' topic(s) added to queue.';
							if (failedCount > 0) {
								msg += ' ' + failedCount + ' failed.';
							}
							alert(msg);
							
							// Reload page to show new topics
							location.reload();
						}
					}
				});
			});
		},

		/**
		 * Extract keywords from a topic title
		 */
		extractKeywords: function(topic) {
			// Common words to filter out
			const stopWords = [
				'a', 'an', 'the', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for',
				'of', 'with', 'by', 'from', 'as', 'is', 'was', 'are', 'were', 'been',
				'be', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could',
				'should', 'may', 'might', 'must', 'can', 'this', 'that', 'these', 'those',
				'it', 'its', 'you', 'your', 'we', 'our', 'they', 'their', 'what', 'which',
				'who', 'whom', 'how', 'when', 'where', 'why', 'all', 'each', 'every',
				'both', 'few', 'more', 'most', 'other', 'some', 'such', 'no', 'not',
				'only', 'same', 'so', 'than', 'too', 'very', 'just', 'also', 'now',
				'here', 'there', 'about', 'into', 'through', 'during', 'before', 'after',
				'above', 'below', 'between', 'under', 'again', 'further', 'then', 'once'
			];
			
			// Clean and split the topic
			const words = topic
				.toLowerCase()
				.replace(/[^\w\s]/g, ' ')  // Remove punctuation
				.split(/\s+/)               // Split by whitespace
				.filter(word => {
					return word.length > 2 && !stopWords.includes(word);
				});
			
			// Get unique keywords (max 5)
			const uniqueKeywords = [...new Set(words)].slice(0, 5);
			
			return uniqueKeywords.join(', ');
		},

		/**
		 * Toggle all topics checkboxes
		 */
		toggleAllTopics: function() {
			const checked = $(this).is(':checked');
			$('.topic-checkbox').prop('checked', checked);
		},

		/**
		 * Analyze website
		 */
		analyzeWebsite: function(e) {
			e.preventDefault();
			
			const $button = $(e.currentTarget);
			const $status = $('#analysis-status');
			const $result = $('#analysis-result');
			const useAi = $button.data('use-ai') === true || $button.data('use-ai') === 'true';
			const originalHtml = $button.html();

			// Disable both buttons during analysis
			$('#analyze-website, #analyze-website-ai').prop('disabled', true);
			
			if (useAi) {
				$button.html('<span class="dashicons dashicons-update spin"></span> AI Analyzing...');
			} else {
				$button.html('<span class="dashicons dashicons-update spin"></span> Analyzing...');
			}
			
			$status.html('<span class="status-warning">Analyzing your content...</span>');

			$.ajax({
				url: aiBlogPosts.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ai_blog_posts_analyze_website',
					nonce: aiBlogPosts.nonce,
					use_ai: useAi
				},
				timeout: 120000, // 2 minutes for AI analysis
				success: function(response) {
					if (response.success) {
						$status.html('<span class="status-success"><span class="dashicons dashicons-yes-alt"></span> ' + response.data.message + '</span>');
						
						// Reload page to show full analysis with PHP rendering
						setTimeout(function() {
							location.reload();
						}, 500);
					} else {
						$status.html('<span class="status-error"><span class="dashicons dashicons-warning"></span> ' + response.data.message + '</span>');
						$('#analyze-website, #analyze-website-ai').prop('disabled', false);
						$button.html(originalHtml);
					}
				},
				error: function(xhr, status, error) {
					let errorMsg = 'Connection error.';
					if (status === 'timeout') {
						errorMsg = 'Request timed out. Please try again.';
					}
					$status.html('<span class="status-error"><span class="dashicons dashicons-warning"></span> ' + errorMsg + '</span>');
					$('#analyze-website, #analyze-website-ai').prop('disabled', false);
					$button.html(originalHtml);
				}
			});
		},

		/**
		 * Export logs to CSV
		 */
		exportLogs: function(e) {
			e.preventDefault();
			
			// Create a temporary form to trigger download
			const form = document.createElement('form');
			form.method = 'POST';
			form.action = aiBlogPosts.ajaxUrl;
			
			const actionInput = document.createElement('input');
			actionInput.type = 'hidden';
			actionInput.name = 'action';
			actionInput.value = 'ai_blog_posts_export_logs';
			form.appendChild(actionInput);
			
			const nonceInput = document.createElement('input');
			nonceInput.type = 'hidden';
			nonceInput.name = 'nonce';
			nonceInput.value = aiBlogPosts.nonce;
			form.appendChild(nonceInput);
			
			document.body.appendChild(form);
			form.submit();
			document.body.removeChild(form);
		},

		/**
		 * Clear all logs
		 */
		clearLogs: function(e) {
			e.preventDefault();
			
			if (!confirm('Are you sure you want to delete ALL generation logs? This action cannot be undone.')) {
				return;
			}

			const $button = $('#clear-logs');
			$button.prop('disabled', true).text('Clearing...');

			$.ajax({
				url: aiBlogPosts.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ai_blog_posts_clear_logs',
					nonce: aiBlogPosts.nonce
				},
				success: function(response) {
					if (response.success) {
						alert(response.data.message);
						location.reload();
					} else {
						alert('Error: ' + response.data.message);
					}
				},
				error: function() {
					alert('Connection error.');
				},
				complete: function() {
					$button.prop('disabled', false).html('<span class="dashicons dashicons-trash"></span> Clear All Logs');
				}
			});
		}
	};

	// Initialize on document ready
	$(document).ready(function() {
		AIBlogPosts.init();

		// Pre-fill topic if passed in URL
		const urlParams = new URLSearchParams(window.location.search);
		const topic = urlParams.get('topic');
		if (topic) {
			$('#topic').val(decodeURIComponent(topic));
		}
	});

})(jQuery);
