# AI Blog Posts - WordPress Plugin

<p align="center">
  <img src="https://img.shields.io/badge/WordPress-5.8+-blue.svg" alt="WordPress 5.8+">
  <img src="https://img.shields.io/badge/PHP-7.4+-purple.svg" alt="PHP 7.4+">
  <img src="https://img.shields.io/badge/License-GPL--2.0-green.svg" alt="GPL-2.0">
  <img src="https://img.shields.io/badge/OpenAI-GPT--5.1-orange.svg" alt="GPT-5.1">
</p>

Automatically generate and publish high-quality, SEO-optimized blog posts using OpenAI's latest GPT models with AI image generation. Perfect for content marketers, bloggers, and businesses looking to scale their content production.

## ✨ Features

### 🤖 AI Content Generation
- **Multi-step generation pipeline**: Outline → Content → Humanization → SEO optimization
- **Gutenberg-compatible output** with proper block structure
- **Customizable word count** (300-10,000 words)
- **Adjustable humanization levels** (1-5) to reduce AI-detectable patterns
- **Website context awareness** - matches your site's tone and style

### 🧠 Latest AI Models (December 2025)
| Model | Best For | Cost Efficiency |
|-------|----------|-----------------|
| GPT-5.1 | Complex content, agentic tasks | ⭐⭐⭐ |
| GPT-5 Mini | Blog writing, general content | ⭐⭐⭐⭐⭐ |
| GPT-5 Nano | Simple tasks, high volume | ⭐⭐⭐⭐⭐ |
| GPT-5 Pro | Premium quality content | ⭐⭐ |
| GPT-4.1 | Non-reasoning tasks | ⭐⭐⭐⭐ |
| GPT-4o / 4o-mini | Legacy support | ⭐⭐⭐⭐ |

### 🖼️ AI Image Generation
- **GPT Image 1** - State-of-the-art image generation (replaces DALL-E)
- **GPT Image 1 Mini** - Cost-efficient alternative
- **Smart NO-TEXT prompts** - Images without text/watermarks
- **Visual concept mapping** - Automatically matches images to content topics
- **Professional quality** - HD, natural style, rule-of-thirds composition

### 📅 Scheduling & Automation
- **Automated posting** on customizable schedules (hourly, daily, weekly)
- **Topic queue management** with priority ordering
- **CSV import** for bulk topic upload
- **Google Trends integration** for trending topic suggestions
- **Daily post limits** and budget controls

### 🔍 SEO Integration
- **Auto-generate meta descriptions** and focus keywords
- **Automatic SEO title optimization**
- **Seamless integration** with:
  - ✅ Yoast SEO
  - ✅ Rank Math
  - ✅ All In One SEO Pack
- **SEO-optimized content structure** with proper heading hierarchy

### 📊 Analytics & Cost Management
- **Real-time cost tracking** per generation
- **Monthly, weekly, and daily spending reports**
- **Budget limits** with automatic pause
- **Detailed generation logs**
- **CSV export** for accounting
- **Token usage statistics**

### 🔒 Security
- **Encrypted API key storage** using WordPress salts
- **Full WordPress nonce verification**
- **Role-based access control** (Administrator only)
- **Prepared database queries** to prevent SQL injection
- **Secure AJAX handlers**

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
2. Upload to `/wp-content/plugins/ai-blog-posts/`
3. Activate through the WordPress admin
4. Navigate to **AI Blog Posts → Settings**
5. Enter your OpenAI API key and click "Verify Key"
6. Configure your preferences and start generating!

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

### Scheduling

1. Enable scheduled posting in Settings
2. Set frequency (hourly, daily, weekly)
3. Configure preferred posting time
4. Set maximum posts per day
5. Add topics to the queue

### SEO Configuration

1. Ensure your SEO plugin is active (Yoast, Rank Math, or AIOSEO)
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

### CSV Import Format

```csv
Topic,Keywords,Category,Priority
"Your Topic Title","keyword1, keyword2",Category Name,50
```

## 🛣️ Roadmap

### Version 1.1 (Q1 2025)
- [ ] Multi-language content generation
- [ ] Custom post type support
- [ ] Bulk regeneration of existing posts
- [ ] Advanced scheduling (specific days/dates)

### Version 1.2 (Q2 2025)
- [ ] Content templates/blueprints
- [ ] A/B title testing
- [ ] Internal linking suggestions
- [ ] Plagiarism checking integration

### Version 1.3 (Q3 2025)
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

