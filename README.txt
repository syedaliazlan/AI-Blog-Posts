=== AI Blog Posts ===
Contributors: aliazlan
Donate link: https://devonicweb.co.uk/
Tags: ai, blog, content, openai, gpt, automation, seo
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Automatically generate and publish high-quality, SEO-optimized blog posts using OpenAI's GPT models with DALL-E image generation.

== Description ==

AI Blog Posts is a powerful WordPress plugin that leverages OpenAI's advanced language models to automatically generate high-quality, SEO-optimized blog content. Perfect for content marketers, bloggers, and businesses looking to scale their content production.

= Key Features =

**Content Generation**

* Generate complete blog posts from simple topics or keywords
* Multi-step generation process: Outline → Content → Humanization → SEO
* Gutenberg-compatible output with proper block structure
* Customizable word count (300-10,000 words)
* Adjustable "humanization" levels to reduce AI-detectable patterns

**AI Models**

* Support for latest GPT-5.1, GPT-5 Mini, GPT-5 Nano, GPT-5 Pro, GPT-4.1, GPT-4o models
* GPT Image 1 and GPT Image 1 Mini for state-of-the-art image generation
* Legacy DALL-E 3 support
* Automatic model pricing calculation and cost tracking

**Scheduling & Automation**

* Automated posting on customizable schedules (hourly, daily, weekly)
* Topic queue management with priority ordering
* Trending topics integration via Google Trends
* Daily post limits and budget controls

**SEO Integration**

* Auto-generate meta descriptions, focus keywords, and SEO titles
* Seamless integration with Yoast SEO, RankMath, and All In One SEO Pack
* SEO-optimized content structure and heading hierarchy

**Website Analysis**

* Analyze existing posts to match writing style and tone
* Automatic context learning for consistent content
* Minimal API usage with intelligent caching

**Cost Management**

* Detailed cost tracking per generation
* Monthly, weekly, and daily spending reports
* Budget limits with email alerts
* CSV export for accounting

**Security**

* Encrypted API key storage
* Full WordPress nonce verification
* Role-based access control
* Prepared database queries

= Requirements =

* WordPress 5.8 or higher
* PHP 7.4 or higher
* OpenAI API key (get one at platform.openai.com)

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/ai-blog-posts/` or install directly through the WordPress plugins screen.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Navigate to AI Blog Posts → Settings to configure your OpenAI API key.
4. Click "Verify Key" to test your connection.
5. Configure your content preferences, scheduling, and SEO settings.
6. Start generating content!

== Frequently Asked Questions ==

= Do I need an OpenAI account? =

Yes, you need an OpenAI API key to use this plugin. Sign up at platform.openai.com and create an API key in your account settings.

= How much does it cost to generate a post? =

Costs vary based on the model you choose and post length. Using GPT-5 Mini (recommended), a typical 1000-word post costs approximately $0.01-0.03. GPT Image 1 images add $0.04-0.08 per image.

= Can I schedule automatic posting? =

Yes! Configure the scheduling options in Settings → Scheduling. You can set frequency (hourly, daily, weekly), preferred time, and maximum posts per day.

= Will the content be detected as AI-written? =

The plugin includes a multi-level "humanization" feature that rewrites content to sound more natural. Higher levels significantly reduce AI-detectable patterns.

= Does it work with popular SEO plugins? =

Yes! The plugin automatically detects and integrates with Yoast SEO, RankMath, and All In One SEO Pack. It populates meta descriptions, focus keywords, and SEO titles automatically.

= What happens if I hit my budget limit? =

Auto-posting will automatically pause, and you'll receive an email notification. Manual generation will still work until you increase your budget or a new month begins.

== Screenshots ==

1. Dashboard with statistics and quick actions
2. Generate post interface with live progress
3. Settings page with API configuration
4. Topic queue management
5. Cost tracking and generation logs

== Changelog ==

= 1.0.0 =
* Initial release
* GPT-5.1, GPT-5 Mini, GPT-5 Nano, GPT-5 Pro, GPT-4.1, GPT-4o support
* GPT Image 1 and DALL-E 3 featured image generation
* Yoast SEO, RankMath, and All In One SEO Pack integration
* Scheduled posting with topic queue
* CSV import for bulk topic upload
* Google Trends integration
* Website style analysis
* Step-by-step generation with retry logic
* Comprehensive cost tracking
* Budget limits and alerts

== Upgrade Notice ==

= 1.0.0 =
Initial release of AI Blog Posts.

== Privacy Policy ==

This plugin sends data to OpenAI's API servers to generate content. This includes:
* Topics and keywords you provide
* Excerpts of existing posts (for style analysis, if enabled)
* Generated content for humanization passes

Please review OpenAI's privacy policy at https://openai.com/privacy/

No data is stored on third-party servers by this plugin. All logs and settings are stored in your WordPress database.
