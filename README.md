# llms.click - AI Discoverability Fixes

WordPress plugin that applies [llms.click](https://llms.click) AI-discoverability fixes with one click, so your site is readable and citable by AI assistants (ChatGPT, Perplexity, Google AI).

- **Version:** 1.0.0
- **Requires WordPress:** 5.8+
- **Requires PHP:** 7.4+
- **License:** GPLv2 or later

## What it does

[llms.click](https://llms.click) analyzes your site across 4 categories (Access, Extractability, Understanding, Citability) and generates ready-to-use fixes written on the real content of your pages. This plugin closes the loop: instead of copy-pasting by hand, you apply the fixes directly from your WordPress.

It can apply:

- **JSON-LD structured data** (Organization, WebSite, WebPage, FAQPage) in the head
- **Open Graph meta** for correct previews on AI assistants and social networks
- **robots.txt** directives for AI bots (PerplexityBot, OAI-SearchBot, ChatGPT-User, GPTBot, ...)
- **llms.txt** served at `/llms.txt`, introducing your site to AI models
- **Answer-ready content**: an answer-first paragraph plus FAQ plus FAQPage JSON-LD, via shortcode or block

Every fix has an on/off switch and is reversible: turning it off removes the injection with no leftovers.

## Installation

### From the zip
1. Download the latest [`llmsclick.zip`](https://llms.click/downloads/llmsclick.zip).
2. In WordPress: Plugins -> Add New -> Upload Plugin -> select the zip -> Install -> Activate.

### From source
1. Clone this repository into `wp-content/plugins/llmsclick`.
2. Activate the plugin from the Plugins menu.

## Setup

1. Create an account on [llms.click](https://llms.click) (Silver plan or higher) and generate an API key from your profile.
2. Go to **Settings -> llms.click** and paste the key.
3. Click **Analyze and load fixes**: the plugin asks llms.click which fixes your site needs.
4. Enable the fixes you want.

## How it works

The plugin is a thin client. All the value (analysis and fix generation on your real content) happens server-side on llms.click and is returned only to active subscribers. The plugin:

- communicates only with the llms.click API, server to server, over HTTPS, authenticated with your API key;
- never executes code received from the API: it only outputs static markup (JSON-LD, meta tags, text);
- never exposes your API key in the front-end;
- removes every injection cleanly when a fix is turned off or the plugin is deactivated.

The API key is tied to your site domain. See the [API docs](https://llms.click/docs/api) and the [plugin guide](https://llms.click/docs/plugin).

## External service

This plugin requires the third-party [llms.click](https://llms.click) service to function. Your site URL and API key are sent (over HTTPS) only when you analyze the site or toggle a fix, never on front-end page loads. See the [Terms](https://llms.click/terms) and [Privacy](https://llms.click/privacy).

## Structure

```
llmsclick/
  llmsclick.php            Bootstrap, hooks, activation/deactivation
  includes/
    class-api.php          API client (wp_remote_get + X-API-Key)
    class-applier.php      Fix state, conflict detection, cache flush, FAQ shortcode/block
    class-head.php         wp_head injection (validated JSON-LD + meta)
    class-files.php        /llms.txt rewrite + robots.txt filter
    class-settings.php     Admin settings page + AJAX (validate/fetch/toggle)
  admin/                   Settings page UI (PHP/JS/CSS)
  uninstall.php            Cleanup on uninstall
```

## License

GPLv2 or later. See [LICENSE](LICENSE).
