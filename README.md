# AI Blog Posts - WordPress Plugin

<p align="center">
  <img src="https://img.shields.io/badge/WordPress-5.8+-blue.svg" alt="WordPress 5.8+">
  <img src="https://img.shields.io/badge/PHP-7.4+-purple.svg" alt="PHP 7.4+">
  <img src="https://img.shields.io/badge/License-GPL--2.0-green.svg" alt="GPL-2.0">
  <img src="https://img.shields.io/badge/OpenAI-GPT--5.2-orange.svg" alt="GPT-5.2">
  <img src="https://img.shields.io/badge/Pexels-Free%20Images-05A081.svg" alt="Pexels">
  <img src="https://img.shields.io/badge/Version-2.0.0-brightgreen.svg" alt="Version 2.0.0">
</p>

Automatically generate and publish high-quality, SEO-optimized blog posts using OpenAI's GPT-5.2 with free Pexels image integration. Perfect for content marketers, bloggers, and businesses looking to scale their content production.

## What's New in v2.0.0

- **Pexels Integration**: Free, high-quality stock images replace paid DALL-E generation
- **GPT-5.2 Optimized**: Streamlined for OpenAI's latest flagship model
- **Step-by-Step Generation**: Visual progress tracking (Outline → Content → Post → Images → SEO)
- **Simplified Architecture**: Removed complex cron/scheduler for reliable synchronous generation
- **Human-Like Content**: Enhanced prompts for natural, engaging writing without AI patterns

## Features

### AI Content Generation
- **Step-by-step pipeline**: Outline → Content → Post Creation → Images → SEO
- **Real-time progress tracking**: See each generation step as it happens
- **GPT-5.2 powered**: Uses OpenAI's latest flagship model
- **Human-like writing**: Optimized prompts for natural tone, no em dashes, varied structure
- **Customizable word count**: Generate content from 500-5000 words
- **Website context awareness**: Matches your site's tone and style

### Pexels Image Integration (FREE)
- **Featured images**: Automatically selected based on post topic
- **Inline images**: Up to 5 contextual images distributed throughout content
- **Orientation options**: Landscape, portrait, or square
- **Zero cost**: Pexels API is free (200 requests/hour)
- **Auto-download**: Images saved to your WordPress media library
- **Photographer attribution**: Proper credits maintained

### Content Settings
- **Title source**: Use your topic as-is OR let GPT-5.2 generate an optimized title
- **Post status**: Publish immediately, save as draft, or pending review
- **Category assignment**: Auto-assign to selected categories
- **SEO optimization**: Auto-generate meta descriptions and focus keywords

### SEO Integration
- **Automatic meta descriptions** and focus keywords
- **Seamless integration** with:
  - Yoast SEO
  - Rank Math
- **SEO-optimized structure** with proper heading hierarchy

### Topic Queue Management
- **Bulk topic management**: Add multiple topics to generate
- **Priority ordering**: Process important topics first
- **CSV import**: Bulk upload topics from spreadsheet
- **Status tracking**: See pending, processing, completed, and failed topics
- **Retry failed topics**: One-click retry for failed generations

### Cost Tracking & Logs
- **Token usage tracking**: Monitor prompt and completion tokens
- **Cost calculation**: Track spending per post and over time
- **Generation logs**: Detailed history of all generations
- **CSV export**: Export logs for accounting

### Security
- **Encrypted API key storage** using WordPress salts
- **WordPress nonce verification** on all AJAX requests
- **Role-based access**: Administrator only
- **Prepared database queries** to prevent SQL injection

## Requirements

- WordPress 5.8 or higher
- PHP 7.4 or higher
- OpenAI API key ([Get one here](https://platform.openai.com))
- Pexels API key ([Get one here](https://www.pexels.com/api/) - Free)

## Installation

### From GitHub

1. Download or clone this repository
2. Upload to `/wp-content/plugins/AI-Blog-Posts/`
3. Activate through WordPress admin
4. Navigate to **AI Blog Posts → Settings**
5. Enter your OpenAI API key and click "Verify Key"
6. Enter your Pexels API key and click "Verify Key"
7. Configure your preferences and start generating!

### Quick Start

1. **Add API Keys**: Settings → API Keys tab
2. **Enable Images**: Settings → Images tab → Enable Images toggle
3. **Configure Pexels**: Choose orientation and number of inline images
4. **Add Topics**: Topic Queue → Add topics you want to write about
5. **Generate**: Click "Generate" on any topic and watch the progress

## Configuration

### API Keys (Required)

| Key | Purpose | Cost |
|-----|---------|------|
| OpenAI API Key | Content generation with GPT-5.2 | Pay per token |
| Pexels API Key | Free stock images | Free (200 req/hour) |

### Image Settings

| Setting | Options | Default |
|---------|---------|---------|
| Enable Images | On/Off | Off |
| Orientation | Landscape, Portrait, Square | Landscape |
| Inline Images | 0-5 | 3 |

### Content Settings

| Setting | Description |
|---------|-------------|
| Title Source | Use topic text OR AI-generated title |
| Word Count | Target word count (500-5000) |
| Post Status | Draft, Pending, or Publish |
| Default Category | Auto-assign category |

## How It Works

### Generation Steps

1. **Generating Outline** (20%): Creates structured outline with sections
2. **Writing Content** (50%): Generates full blog post with images suggestions
3. **Creating Post** (60%): Saves to WordPress as draft/published
4. **Adding Images** (85%): Searches Pexels and embeds images
5. **Optimizing SEO** (100%): Sets meta descriptions and focus keywords

### Image Placement

GPT-5.2 suggests contextual images during content generation. These are:
- Searched on Pexels using AI-suggested queries
- Downloaded to your media library
- Distributed evenly across content sections (after H2 headings)
- Featured image set from the first result

## CSV Import Format

```csv
Topic,Keywords,Category,Priority
"Your Topic Title","keyword1, keyword2",Category Name,50
```

## API Usage & Costs

### OpenAI (GPT-5.2)
- Input: $2.50 per 1M tokens
- Output: $10.00 per 1M tokens
- Typical post (~1000 words): ~$0.03-0.08

### Pexels
- Completely free
- 200 requests per hour
- No attribution required (but appreciated)

## Troubleshooting

### Common Issues

**"API key not verified"**
- Ensure your OpenAI API key has credits available
- Check for spaces before/after the key

**"No images found"**
- Pexels may not have images for very specific queries
- Try broader topics or different keywords

**"Generation timeout"**
- Large word counts may take 2-3 minutes
- Each step has its own timeout for reliability

**"Empty content generated"**
- GPT-5.2 may use reasoning tokens internally
- The plugin handles this automatically with generous token limits

## Contributing

We welcome contributions! See our [Contributing Guide](CONTRIBUTING.md) for details.

### Development Setup

```bash
git clone https://github.com/syedaliazlan/AI-Blog-Posts.git
cd AI-Blog-Posts
# Link to your WordPress plugins folder
```

## License

GPL-2.0 License - see [LICENSE.txt](LICENSE.txt)

## Support

- **Issues**: [GitHub Issues](https://github.com/syedaliazlan/AI-Blog-Posts/issues)
- **Email**: contact@devonicweb.co.uk

---

<p align="center">
  Made with care by <a href="https://devonicweb.co.uk">Ali Azlan</a>
</p>
