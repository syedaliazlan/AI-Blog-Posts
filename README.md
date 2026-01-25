# AI Blog Posts - WordPress Plugin

<p align="center">
  <img src="https://img.shields.io/badge/WordPress-5.8+-blue.svg" alt="WordPress 5.8+">
  <img src="https://img.shields.io/badge/PHP-7.4+-purple.svg" alt="PHP 7.4+">
  <img src="https://img.shields.io/badge/License-GPL--2.0-green.svg" alt="GPL-2.0">
  <img src="https://img.shields.io/badge/OpenAI-GPT--5.2-orange.svg" alt="GPT-5.2">
  <img src="https://img.shields.io/badge/Version-1.2.0-brightgreen.svg" alt="Version 1.2.0">
</p>

Automatically generate and publish high-quality, SEO-optimized blog posts using OpenAI's latest GPT models with AI image generation. Perfect for content marketers, bloggers, and businesses looking to scale their content production.

## ✨ Features

### 🤖 AI Content Generation
- **Multi-step generation pipeline**: Outline → Content → Humanization → SEO optimization
- **Gutenberg-compatible output** with proper block structure
- **Customizable word count** (300-10,000 words)
- **Adjustable humanization levels** (1-5) to reduce AI-detectable patterns
- **Website context awareness** - matches your site's tone and style
- **Duplicate prevention** - intelligent topic hashing and title similarity detection

### 🧠 Latest AI Models (January 2026)
| Model | Best For | Cost Efficiency |
|-------|----------|-----------------|
| GPT-5.2 | Flagship coding & agentic tasks | ⭐⭐⭐ |
| GPT-5.2 Pro | Smarter responses, complex content | ⭐⭐ |
| GPT-5.1 | Complex content, agentic tasks | ⭐⭐⭐ |
| GPT-5 | General excellence | ⭐⭐⭐⭐ |
| GPT-5 Mini | Blog writing, general content | ⭐⭐⭐⭐⭐ |
| GPT-5 Nano | Simple tasks, high volume | ⭐⭐⭐⭐⭐ |
| GPT-4.1 / 4.1-mini / 4.1-nano | Non-reasoning tasks | ⭐⭐⭐⭐ |

### 🖼️ AI Image Generation
| Model | Description | Sizes |
|-------|-------------|-------|
| GPT Image 1.5 | State-of-the-art (Recommended) | 1024×1024, 1536×1024, 1024×1536, auto |
| GPT Image 1 | Current standard model | 1024×1024, 1536×1024, 1024×1536, auto |
| GPT Image 1 Mini | Cost-efficient option | 1024×1024, 1536×1024, 1024×1536, auto |
| DALL-E 3 (Legacy) | Previous generation | 1024×1024, 1792×1024, 1024×1792 |

**Features:**
- **Quality settings**: Auto, Low, Medium, High (GPT Image) / Standard, HD (DALL-E 3)
- **Smart NO-TEXT prompts** - Images without text/watermarks
- **Visual concept mapping** - Automatically matches images to content topics
- **Automatic retry mechanism** - Failed images are automatically retried
- **Shared hosting optimized** - Reliable image upload without hangs

### 📅 Scheduling & Automation
- **Automated posting** on customizable schedules (hourly, daily, weekly)
- **WordPress timezone support** - scheduling uses your WordPress timezone settings
- **cron-job.org integration** - One-click setup for 100% reliable scheduling
- **Duplicate prevention** - intelligent locking prevents duplicate post generation
- **Topic queue management** with priority ordering
- **Watchdog mechanism** - automatically detects and recovers stuck processes
- **Smart retry system** - only retries failed steps (image/tags) when post already exists
- **Progress tracking** - checkpoints at each generation step for better recovery
- **CSV import** for bulk topic upload
- **Google Trends integration** for trending topic suggestions

### ☁️ cron-job.org Integration (NEW in 1.2.0)
WordPress cron only runs when someone visits your site. For reliable scheduled posting without traffic dependency:

- **One-click setup** - Just enter your API key and click "Create Cron Job"
- **Live status panel** - Shows last run, next run, and execution status
- **Enable/Disable toggle** - Control without deleting
- **Automatic URL sync** - Updates if your site URL changes
- **Secure endpoint** - Secret key authentication
- **JSON status response** - For monitoring success/failure

**Setup:**
1. Get your free API key from [cron-job.org Settings](https://console.cron-job.org/settings)
2. Paste in plugin settings and verify
3. Click "Create Cron Job"
4. Done! Your posts will generate reliably even with zero traffic

### 🔍 SEO Integration
- **Auto-generate meta descriptions** and focus keywords
- **Automatic SEO title optimization**
- **Seamless integration** with:
  - ✅ Yoast SEO
  - ✅ Rank Math
- **SEO-optimized content structure** with proper heading hierarchy

### 📊 Analytics & Cost Management
- **Real-time cost tracking** per generation
- **Monthly, weekly, and daily spending reports**
- **Budget limits** with automatic pause
- **Detailed generation logs**
- **CSV export** for accounting
- **Token usage statistics**
- **API call counting** - track total API calls in logs

### 🔒 Security
- **Encrypted API key storage** using WordPress salts
- **Full WordPress nonce verification**
- **Role-based access control** (Administrator only)
- **Prepared database queries** to prevent SQL injection
- **Secure AJAX handlers**
- **Secure external cron endpoint** with secret key authentication

### 🌐 Website Analysis
- **Analyze existing posts** to match writing style and tone
- **Automatic context learning** for consistent content
- **Minimal API usage** with intelligent caching
- **Custom instructions support** per generation

## 📋 Requirements

- WordPress 5.8 or higher
- PHP 7.4 or higher
- OpenAI API key ([Get one here](https://platform.openai.com))

## 🚀 Installation

### From GitHub

1. Download the latest release from the [Releases](https://github.com/syedaliazlan/AI-Blog-Posts/releases) page
2. **Important**: If updating an existing installation:
   - Deactivate the plugin in WordPress admin
   - Delete the old plugin folder (`/wp-content/plugins/ai-blog-posts/` or `/wp-content/plugins/AI-Blog-Posts-main/`)
   - Upload the new zip file
   - Activate the plugin
3. For fresh installation:
   - Upload the zip file via WordPress admin (Plugins → Add New → Upload Plugin)
   - Or extract to `/wp-content/plugins/ai-blog-posts/` (ensure folder name is `ai-blog-posts`)
4. Activate through the WordPress admin
5. Navigate to **AI Blog Posts → Settings**
6. Enter your OpenAI API key and click "Verify Key"
7. Configure your preferences and start generating!

**Note**: If you download directly from GitHub, the zip may extract to `AI-Blog-Posts-main/`. Make sure the final folder name is `ai-blog-posts` to match the plugin's expected directory structure.

### From WordPress Admin

1. Go to **Plugins → Add New**
2. Search for "AI Blog Posts"
3. Click **Install Now** and then **Activate**
4. Follow the setup wizard

## ⚙️ Configuration

### Basic Setup

1. **API Key**: Enter your OpenAI API key in Settings
2. **Model Selection**: Choose your preferred model (GPT-5 Mini recommended for blogs)
3. **Content Settings**: Set word count range and humanization level
4. **Website Context**: Describe your website for consistent tone

### Image Generation

1. Enable featured images in Settings
2. Choose your image model:
   - **GPT Image 1.5** (Recommended) - State-of-the-art quality
   - **GPT Image 1** - Current standard
   - **GPT Image 1 Mini** - Cost-efficient
   - **DALL-E 3** - Legacy option
3. Select size and quality settings
4. Images are automatically generated for both manual and scheduled posts

### Scheduling

1. Enable scheduled posting in Settings
2. Set frequency (hourly, daily, weekly)
3. Configure preferred posting time (uses WordPress timezone)
4. Set maximum posts per day
5. Add topics to the queue
6. **Recommended**: Set up cron-job.org for reliable scheduling

### cron-job.org Setup (Recommended)

For 100% reliable scheduling regardless of site traffic:

1. Create a free account at [cron-job.org](https://cron-job.org)
2. Go to Settings and generate an API key
3. In WordPress: **AI Blog Posts → Settings → Scheduling**
4. Paste your API key and click "Verify & Save"
5. Click "Create Cron Job"
6. The status panel will show your job's live status

**Alternative**: Manual server crontab setup is also available in the settings.

### SEO Configuration

1. Ensure your SEO plugin is active (Yoast or Rank Math)
2. Enable SEO optimization in Settings
3. The plugin will automatically populate meta fields

## 📖 Usage

### Manual Generation

1. Go to **AI Blog Posts → Generate**
2. Enter your topic and optional keywords
3. Select category and options
4. Click "Generate Post"
5. Review and publish

### Queue-Based Generation

1. Go to **AI Blog Posts → Topic Queue**
2. Add topics manually or import via CSV
3. Set priorities (0-100, higher = sooner)
4. Enable scheduled posting
5. Topics will be processed automatically
6. **Automatic recovery**: Topics stuck in "processing" status are automatically recovered
7. **Smart retry**: If a post was created but image/tags failed, only those steps are retried
8. Failed topics can be retried (up to 3 attempts)

### CSV Import Format

```csv
Topic,Keywords,Category,Priority
"Your Topic Title","keyword1, keyword2",Category Name,50
```

## 🆕 Version 1.2.0 Highlights

### cron-job.org Integration
- ✅ **One-click setup** - Automatic cron job creation via API
- ✅ **Live status panel** - Monitor job status in WordPress admin
- ✅ **Enable/Disable toggle** - Control without deleting
- ✅ **Automatic URL sync** - Updates if site URL changes
- ✅ **Secure endpoint** - Secret key authentication for external cron

### Updated AI Models (January 2026)
- ✅ **GPT-5.2** - Latest flagship model
- ✅ **GPT-5.2 Pro** - Enhanced version with smarter responses
- ✅ **GPT-4.1-nano** - Fastest, most cost-efficient option
- ✅ **GPT Image 1.5** - State-of-the-art image generation
- ✅ **GPT Image 1 Mini** - Cost-efficient image option

### Duplicate Prevention
- ✅ **Topic hashing** - Prevents regenerating same topic/keyword combinations
- ✅ **Title similarity detection** - Catches posts with 75%+ similar titles
- ✅ **Smart queue handling** - Duplicates marked as completed, not failed

### Image Retry Mechanism
- ✅ **Automatic retry** - Failed images are retried in background
- ✅ **Retry tracking** - Up to 3 retry attempts per post
- ✅ **Watchdog recovery** - Stuck image jobs are automatically detected

### Reliability & Performance
- ✅ **Atomic locking** - Prevents race conditions in scheduled generation
- ✅ **Wider time windows** - 30-minute tolerance for cron timing variations
- ✅ **Database optimization** - Removed shutdown handlers causing conflicts
- ✅ **API call batching** - Reduced database writes for call counting

### Previous Updates (v1.1.0)
- ✅ **Optimized image flow** - Posts publish immediately, images generate after
- ✅ **Shared hosting optimized** - Reliable image uploads without hangs
- ✅ **Enhanced timeout handling** - 90-second timeout for faster failure detection
- ✅ **Shutdown handlers** - Ensures topic status is updated even if process is killed

## 🛣️ Roadmap

### Version 1.3 (Q1 2026)
- [ ] Multi-language content generation
- [ ] Custom post type support
- [ ] Bulk regeneration of existing posts
- [ ] Advanced scheduling (specific days/dates)

### Version 1.4 (Q2 2026)
- [ ] Content templates/blueprints
- [ ] A/B title testing
- [ ] Internal linking suggestions
- [ ] Plagiarism checking integration

### Version 1.5 (Q3 2026)
- [ ] AI-powered content calendar
- [ ] Competitor content analysis
- [ ] Social media post generation
- [ ] Email newsletter content

### Future Plans
- [ ] Multi-site network support
- [ ] REST API endpoints
- [ ] Zapier/Make integration
- [ ] Content performance analytics
- [ ] AI content editing assistant

## 🤝 Contributing

We welcome contributions from the community! Here's how you can help:

### Ways to Contribute

1. **Report Bugs**: Open an issue with detailed reproduction steps
2. **Suggest Features**: Share your ideas in the Issues section
3. **Submit PRs**: Fork the repo and submit pull requests
4. **Documentation**: Help improve our docs
5. **Testing**: Test on different WordPress configurations

### Development Setup

```bash
# Clone the repository
git clone https://github.com/syedaliazlan/AI-Blog-Posts.git

# Navigate to your WordPress plugins directory
cd /path/to/wordpress/wp-content/plugins/

# Create symlink (optional)
ln -s /path/to/AI-Blog-Posts ai-blog-posts

# Activate in WordPress admin
```

### Code Standards

- Follow [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/)
- Use meaningful commit messages
- Add PHPDoc comments for functions
- Test on WordPress 5.8+ and PHP 7.4+

## 📄 License

This project is licensed under the GPL-2.0 License - see the [LICENSE.txt](LICENSE.txt) file for details.

## 🙏 Acknowledgments

- [OpenAI](https://openai.com) for their powerful API
- [cron-job.org](https://cron-job.org) for reliable external cron service
- [WordPress](https://wordpress.org) community
- All contributors and testers

## 📞 Support

- **Issues**: [GitHub Issues](https://github.com/syedaliazlan/AI-Blog-Posts/issues)
- **Discussions**: [GitHub Discussions](https://github.com/syedaliazlan/AI-Blog-Posts/discussions)
- **Email**: contact@devonicweb.co.uk

## 🌟 Show Your Support

If you find this plugin useful, please:
- ⭐ Star this repository
- 🐛 Report bugs and issues
- 💡 Suggest new features
- 📢 Share with others

---

<p align="center">
  Made with ❤️ by <a href="https://devonicweb.co.uk">Ali Azlan</a>
</p>

<p align="center">
  <strong>Open to collaborations!</strong> If you'd like to contribute or partner on this project, feel free to reach out.
</p>
