=== Notira ===
Contributors: nilambar
Tags: ai, email, proofreading, writing, content
Requires at least: 7.0
Tested up to: 7.1.2
Requires PHP: 8.2
Stable tag: 2.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Turn rough notes into clean, email-ready HTML or a polished proofread — powered by the AI provider you connect.

== Description ==

Notira turns rough draft notes into clean, ready-to-send HTML using the AI provider you connect through WordPress. Pick a mode, choose a tone, and generate.

**Email** polishes your draft into a clear message and wraps it with the opening and closing lines you set in the plugin settings, so the result is ready to paste into your mail client.

**Proofread** fixes grammar, spelling, and clarity with minimal rewriting, preserving your original voice and meaning.

= Features =

* Two modes: **Email** and **Proofread**.
* Ten tone presets: Professional, Match original, Friendly, Formal, Concise, Empathetic, Authoritative, Commanding, Assertive, and Neutral.
* Configurable opening and closing lines for email output.
* Default mode and default tone settings.
* Preferred AI provider and model selection.
* One-click **Copy** for the generated result.
* Uses the WordPress AI client, so it works with any AI provider you have connected — no provider lock-in.
* Input between 20 and 2000 characters.
* Translation ready.

= How it works =

1. Open **Notira** from the WordPress admin menu.
2. Paste your draft notes or bullet points.
3. Choose **Email** or **Proofread**, and optionally a **Tone**.
4. Click **Generate**, then **Copy** the result.

Configure your defaults, preferred provider and model, and email opening/closing lines under **Notira → Settings → Output**.

= External services =

This plugin sends the text you submit for generation to the AI provider configured on your site under **Settings → Connectors**. The request is made directly from your WordPress site to that provider using the API key you supply.

* The text you enter is transmitted to the selected AI provider for processing.
* The provider's own terms of service and privacy policy apply to that data.
* No data is sent to the plugin author, and the plugin does not collect or store usage statistics.

Only submit content you are comfortable sending to your chosen AI provider.

== Installation ==

1. Upload the `notira` folder to the `/wp-content/plugins/` directory, or install the plugin through the **Plugins → Add New** screen.
2. Activate the plugin through the **Plugins** screen.
3. Go to **Settings → Connectors** and add an API key for at least one AI provider.
4. Open **Notira** from the admin menu and start writing.

== Frequently Asked Questions ==

= Do I need an API key? =

Yes. Notira uses the WordPress AI client, which requires a connected AI provider. Add your provider credentials under **Settings → Connectors** before generating.

= Which AI providers are supported? =

Any provider available through the WordPress AI client and Connectors. Notira does not bundle its own provider — it uses whichever ones your site has configured.

= What happens to the text I submit? =

It is sent to your configured AI provider to generate the result. See the "External services" section above for details.

= Where is my API key stored? =

API keys are managed by WordPress under **Settings → Connectors**. Notira reads the configured providers but does not store your credentials itself.

= Can I change the opening and closing lines used for emails? =

Yes. Set them under **Notira → Settings → Output**.

== Screenshots ==

1. Admin UI

== Changelog ==

= 2.0.1 =
* Confirmed compatibility with WordPress 7.1.2.

= 2.0.0 =
* Raised the minimum PHP requirement to 8.2.
* Improved AI provider and model selection.
* Refined generation prompts.

= 1.1.1 =
* Added settings for default mode and default tone.
* Added support for default content.
* Updated AI model list.
* Refined translations.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 2.0.0 =
Plugin requires PHP 8.2 or later. Confirm your server runs PHP 8.2+ before updating.
