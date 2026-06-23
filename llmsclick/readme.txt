=== llms.click - AI Discoverability Fixes ===
Contributors: vogliofatti
Tags: ai, llm, seo, schema, structured-data
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Apply llms.click AI-discoverability fixes with one click, so your site is readable and citable by AI assistants (ChatGPT, Perplexity, Google AI).

== Description ==

[llms.click](https://llms.click) analyzes your site across 4 categories (Access, Extractability, Understanding, Citability) and generates ready-to-use fixes written on the real content of your pages. This plugin closes the loop: instead of copy-pasting by hand, you apply the fixes directly from your WordPress.

What it can apply:

* **JSON-LD structured data** (Organization, WebSite, WebPage, FAQPage) in the head.
* **Open Graph meta** for correct previews on AI assistants and social networks.
* **robots.txt**: directives for AI bots (PerplexityBot, OAI-SearchBot, ChatGPT-User, GPTBot, and more).
* **llms.txt**: the file that introduces your site to AI models, served at /llms.txt.
* **Answer-ready content**: an answer-first paragraph plus FAQ plus FAQPage JSON-LD, via shortcode or block.

Every fix has an on/off switch and is **reversible**: turning it off removes the injection, with no leftovers.

= How it works =

1. Create an account on llms.click (Silver plan or higher) and generate an API key from your profile.
2. Install the plugin and paste the key into Settings -> llms.click.
3. Click "Analyze and load fixes": the plugin asks llms.click which fixes your site needs.
4. Enable the fixes you want. Done.

= External service =

This plugin connects to the llms.click API, a third-party service, to analyze your site and return the fixes to apply. It is required for the plugin to work.

* What is sent: the URL of your site and your API key (over HTTPS, server to server).
* When: only when you click "Analyze and load fixes" or toggle a fix on, never on front-end page loads.
* The plugin never executes code received from the API: it only outputs static markup (JSON-LD, meta tags, text).
* Service: [llms.click](https://llms.click) - [Terms](https://llms.click/terms) - [Privacy](https://llms.click/privacy)

= Privacy and security =

The plugin communicates only with llms.click, server to server, authenticating with your API key. The key is never exposed in the front-end. Premium fixes are generated and returned by the llms.click server only if your API key belongs to an active subscription. The key is tied to your site domain.

== Installation ==

1. Upload the `llmsclick` folder to `/wp-content/plugins/` (or install the zip from Plugins -> Add New -> Upload Plugin).
2. Activate the plugin from the Plugins menu.
3. Go to Settings -> llms.click and enter your API key.

== Frequently Asked Questions ==

= Do I need a paid account? =
Yes. Fixes are available from the Silver plan. Without an active subscription the server returns nothing to apply.

= Does it work with Yoast / Rank Math / SEOPress? =
Yes. If an active SEO plugin is detected, we avoid overwriting title, meta description, Open Graph and schema (to prevent duplicates) and show them to you as a copyable suggestion instead. robots.txt, llms.txt and the FAQ remain always applicable.

= Where does the llms.txt file go? =
At /llms.txt, served by WordPress through a rewrite rule. No write permission on the site root is required.

= How do I insert the answer-ready FAQ? =
Enable the "answer-ready" fix, then add the shortcode `[llmsclick_faq]` or the "llms.click FAQ" block to your page content.

= Do you use a cache (WP Rocket, LiteSpeed...)? =
The plugin tries to clear the cache automatically after each change. If you do not see the changes right away, clear the cache manually.

== Changelog ==

= 1.0.0 =
* First release: API connection, fix list with toggles, head injection (JSON-LD/OG/meta), robots.txt AI-bot directives, llms.txt, FAQ shortcode/block, SEO conflict detection, cache flush.

== Upgrade Notice ==

= 1.0.0 =
First public release.
