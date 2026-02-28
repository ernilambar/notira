# Wordish

WordPress plugin that turns rough notes or bullet points into professional, email-ready HTML using AI. Output can be copied and pasted into HelpScout or similar tools. Uses the [WordPress AI Client](https://github.com/WordPress/wp-ai-client); configure API keys in **Settings → AI Credentials**.

## Requirements

- PHP 7.4+
- WordPress 5.9+
- API key from a supported provider (e.g. OpenAI), set in **Settings → AI Credentials**

## Installation

1. Install dependencies: `composer install`
2. Activate the plugin in **Plugins**.
3. Set your API key in **Settings → AI Credentials** (e.g. OpenAI or another supported provider).

## Usage

1. Go to **Dashboard → Wordish**.
2. Paste or type rough notes, bullets, or paragraphs in the textarea.
3. Choose a **tone** (default: Professional and courteous).
4. Click **Improve & generate HTML**.
5. Use **Copy to clipboard** to paste the result (HTML) into your email or help-desk app.

Output is formatted with a "Hi," opening and "Regards," closing. Input is limited to 4,000 characters; identical requests are cached for 5 minutes.
