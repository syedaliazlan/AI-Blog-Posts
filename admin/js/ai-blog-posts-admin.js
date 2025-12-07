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
		 * Bind all event handlers
		 */
		bindEvents: function() {
			// API Key verification
			$('#verify-api-key').on('click', this.verifyApiKey.bind(this));
			$('#toggle-api-key').on('click', this.toggleApiKeyVisibility);

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
			$('.delete-topic').on('click', this.deleteTopic.bind(this));
			$('.generate-topic').on('click', this.generateFromTopic.bind(this));
			$('#fetch-trending').on('click', this.fetchTrending.bind(this));
			$('#select-all-topics').on('change', this.toggleAllTopics);

			// Modals
			$('#bulk-import').on('click', function() { $('#csv-import-modal').show(); });
			$('.modal-close, .modal-cancel').on('click', function() { $(this).closest('.ai-blog-posts-modal').hide(); });

			// Website analysis
			$('#analyze-website').on('click', this.analyzeWebsite.bind(this));

			// Export CSV
			$('#export-csv').on('click', this.exportLogs.bind(this));
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
			const apiKey = $('#api_key').val();

			if (!apiKey || apiKey.indexOf('•') === 0) {
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
						// Mask the API key
						$('#api_key').val('••••••••••••••••••••');
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
					settings[name] = $input.is(':checked');
				} else if ($input.attr('multiple')) {
					settings[name] = $input.val() || [];
				} else {
					// Skip masked API key
					if (name === 'api_key' && $input.val().indexOf('•') === 0) {
						return;
					}
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
		 * Generate from topic
		 */
		generateFromTopic: function(e) {
			e.preventDefault();
			
			const topicId = $(e.target).data('id');
			const topicText = $(e.target).closest('tr').find('.column-topic strong').text();
			
			if (confirm('Generate a post for "' + topicText + '"?')) {
				window.location.href = 'admin.php?page=ai-blog-posts-generate&topic=' + encodeURIComponent(topicText);
			}
		},

		/**
		 * Fetch trending topics
		 */
		fetchTrending: function(e) {
			e.preventDefault();
			
			const $modal = $('#trending-modal');
			const $loading = $('#trending-loading');
			const $list = $('#trending-list');

			$modal.show();
			$loading.show();
			$list.hide().empty();

			$.ajax({
				url: aiBlogPosts.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ai_blog_posts_fetch_trending',
					nonce: aiBlogPosts.nonce
				},
				success: function(response) {
					$loading.hide();

					if (response.success && response.data.topics) {
						let html = '<div class="trending-topics-list">';
						response.data.topics.forEach(function(topic, index) {
							const title = topic.title || topic;
							html += '<label class="trending-item">';
							html += '<input type="checkbox" value="' + title + '" checked>';
							html += '<span>' + title + '</span>';
							if (topic.traffic) {
								html += '<small>' + topic.traffic + ' searches</small>';
							}
							html += '</label>';
						});
						html += '</div>';
						$list.html(html).show();
					} else {
						$list.html('<p>No trending topics found.</p>').show();
					}
				},
				error: function() {
					$loading.hide();
					$list.html('<p>Error fetching topics.</p>').show();
				}
			});
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
			
			const $button = $(e.target);
			const $status = $('#analysis-status');
			const $result = $('#analysis-result');

			$button.prop('disabled', true).text('Analyzing...');
			$status.text('');
			$result.hide();

			$.ajax({
				url: aiBlogPosts.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ai_blog_posts_analyze_website',
					nonce: aiBlogPosts.nonce
				},
				success: function(response) {
					if (response.success) {
						$status.html('<span class="status-success">Analysis complete!</span>');
						
						// Show simplified analysis
						const analysis = response.data.analysis;
						let html = '<strong>Content Stats:</strong><br>';
						html += 'Avg word count: ' + (analysis.content_stats?.avg_word_count || 'N/A') + '<br>';
						html += 'Tone: ' + (analysis.writing_style?.tone || 'N/A') + '<br>';
						html += 'Voice: ' + (analysis.writing_style?.voice || 'N/A') + '<br>';
						html += 'Posts analyzed: ' + (analysis.posts_analyzed || 0);
						
						$result.html(html).show();
					} else {
						$status.html('<span class="status-error">' + response.data.message + '</span>');
					}
				},
				error: function() {
					$status.html('<span class="status-error">Connection error.</span>');
				},
				complete: function() {
					$button.prop('disabled', false).html('<span class="dashicons dashicons-search"></span> Analyze Website');
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
