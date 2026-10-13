# YouTube Script: Kitgenix CAPTCHA for Cloudflare Turnstile

**Suggested video title:** Stop WordPress & WooCommerce Spam with Cloudflare Turnstile (Full Setup Guide: Kitgenix CAPTCHA for Cloudflare Turnstile)

**Alternative titles:**
- Replace reCAPTCHA on WordPress with Cloudflare Turnstile: Complete Walkthrough
- Protect Login, Checkout & Every Form with One Turnstile Plugin

**Estimated runtime:** 15 to 17 minutes (about 2,750 spoken words at a relaxed tutorial pace). For a cut of about 13 minutes, drop Section 9 (Tips) into the description and shorten the form-builder list in Section 6.

**Plugin version covered:** 2.1.0 (requires WordPress 6.0+ and PHP 8.1+)

**Recording notes:** Record on a staging site with WooCommerce, WPForms and at least one or two other form plugins active so the Integrations tab shows several cards. Log out (or use a private window) for every front-end demo, because logged-in whitelisting would otherwise hide the widget. Seed the Log tab with a few passes and failures before recording section 8.

---

## 1. Hook

[ON SCREEN: Quick cuts. A WordPress comments queue full of spam, a Users list of obviously fake sign-ups, a WooCommerce Orders screen full of failed low-value orders, then an old-style "select all the traffic lights" image puzzle.]

If you run a WordPress site, you know this. Spam in the comments. Fake accounts signing up overnight. Bots hammering your login page. And if you run a WooCommerce store, maybe worse: bots running stolen cards through your checkout one small order at a time.

The usual answer has been an old-school CAPTCHA, where your real customers have to squint at blurry crosswalks before they're allowed to buy anything.

[ON SCREEN: The Cloudflare Turnstile widget on a WordPress login form showing a green tick.]

There's a better way. Today we'll show you how to put Cloudflare Turnstile, a privacy-oriented challenge that most visitors barely notice, across your whole WordPress site. Login, checkout, and every form plugin you use, all from one place.

## 2. Intro

[ON SCREEN: Plugin banner from the WordPress.org listing, then the Kitgenix logo.]

Hi, we're Kitgenix, and this is **Kitgenix CAPTCHA for Cloudflare Turnstile**. It's a free WordPress plugin that adds Cloudflare Turnstile to WordPress core forms, WooCommerce, Easy Digital Downloads, Elementor, and a long list of form, membership, community and newsletter plugins.

The important part is that every protected form is verified on your server. The plugin checks each submission directly with Cloudflare before it lets the login, order or message through.

It's built for bloggers who are tired of comment spam, store owners worried about checkout abuse, and agencies who want one setup they can roll out to every client site.

By the end of this video you'll know how to get your Cloudflare keys, connect them, go through every settings tab, protect a real checkout and a real contact form, and fix things when something goes wrong.

## 3. Why We Built This

[ON SCREEN: A presenter on camera, or a simple slide headed "The problem with the old way".]

So why did we build this? Three reasons.

First, privacy. A lot of site owners are uncomfortable sending their visitors through a heavily tracked CAPTCHA just to post a comment. Turnstile is Cloudflare's privacy-oriented alternative to the traditional image CAPTCHA. It checks signals in the browser and hands back a token, and in most cases there's no puzzle to solve.

[ON SCREEN: A diagram. Browser to Cloudflare (gets a token), then your WordPress server to Cloudflare siteverify (checks the token). No other servers in the path.]

Second, we wanted the data flow to be simple and honest. There's no Kitgenix account, and we don't run a verification proxy in the middle. Your visitor's browser talks to Cloudflare, your server talks to Cloudflare's official siteverify endpoint, and that's it. Your form contents are never sent to us.

[ON SCREEN: A messy grid of logos for separate CAPTCHA add-ons, then it collapses into one Kitgenix card.]

Third, fragmentation. A typical site might run WooCommerce, a form plugin, a newsletter plugin and a membership plugin, which often means four separate CAPTCHA add-ons. We wanted one plugin, one set of keys and one place to see what's going on. And it had to be real protection: a widget does nothing if the server never checks the result, so we audited every integration to confirm a failed check actually blocks the action.

## 4. Getting a Turnstile Site Key & Secret Key

[ON SCREEN: The Cloudflare dashboard, Turnstile section.]

Before the plugin can do anything, you need a Site Key and a Secret Key from Cloudflare. You'll need a Cloudflare account, but your website does **not** have to use Cloudflare's DNS, CDN or proxy. Turnstile works on any WordPress site.

[ON SCREEN: Clicking "Add widget" in the Turnstile section of the Cloudflare dashboard.]

In your Cloudflare dashboard, open **Turnstile** and add a new widget. Give it a name, add your site's hostname, and choose a widget mode. Cloudflare's own interface explains each option, and you can change it later. Create the widget.

[ON SCREEN: The Site Key and Secret Key revealed in Cloudflare, with the Secret Key blurred.]

Cloudflare will show you two values. The **Site Key** is public and goes into your page. The **Secret Key** is private, and only your server should ever see it. Copy both. And don't put your secret key in a screenshot like we nearly just did.

## 5. Installing & Activating

[ON SCREEN: Plugins, then Add New, then search for "Kitgenix Turnstile".]

Installing works like any other plugin. Go to **Plugins → Add New**, search for "Kitgenix CAPTCHA for Cloudflare Turnstile", then install and activate it. You can also upload the zip file if you prefer.

[ON SCREEN: After activation, the browser redirects to the settings page automatically.]

When you activate it, the plugin takes you straight to its settings page. Later on you'll find it in the **Kitgenix** menu in your admin sidebar, under **Cloudflare Turnstile**. There's also a **Settings** link on the Plugins screen.

[ON SCREEN: Point out the top bar: Site Keys, Display, Integrations, the Advanced dropdown, Log, Support, the search icon and the light/dark toggle.]

Along the top you've got **Site Keys**, **Display**, **Integrations**, an **Advanced** dropdown holding **Security**, **Advanced** and **Portability**, then **Log** and **Support**. Tip: press forward slash, or Cmd or Ctrl plus K, to search the settings.

## 6. Full Settings Walkthrough

### Site Keys

[ON SCREEN: The Site Keys tab. Paste in the Site Key, then the Secret Key.]

Let's start with **Site Keys**. Paste your Site Key and your Secret Key into the matching fields.

The Secret Key field is a password field, and the plugin never writes your saved secret into the page's HTML. If you need to check it, click **Reveal secret key** and it's fetched securely on demand. There's also a **Clear saved secret** checkbox if you want to remove it.

[ON SCREEN: The "Test Cloudflare Turnstile Response" card. The widget solves and a "Setup verification" success message appears.]

Next comes one of the most important features in the plugin: **Test Cloudflare Turnstile Response**. This shows a live Turnstile widget right here in your admin. When it solves, the plugin sends that token to Cloudflare from your server and records whether your key pair really works.

This matters because the plugin keeps your **login-sensitive** protections, like WordPress login and registration, the WooCommerce and EDD account forms and the membership plugins, switched off on the front end until this test passes. So a mistyped key can't lock you out of wp-admin. Change either key later and you'll need to run the test again.

[ON SCREEN: The "Managed Key Overrides" card.]

For agencies and security-minded hosts, the **Managed Key Overrides** card shows how to set your keys outside the database. You can use the `KITGENIX_CAPTCHA_FOR_CLOUDFLARE_TURNSTILE_SITE_KEY` and `..._SECRET_KEY` constants in wp-config.php, or matching environment variables. When those are set, the fields here become read-only and the secret can't be revealed or edited from the screen.

### Display

[ON SCREEN: The Display tab, Display Settings card.]

Now **Display**, which controls how the widget looks and behaves.

**Theme** can be Auto, Light or Dark. Auto is the default and follows the visitor's preference.

**Widget Size** can be Normal, Compact or Flexible. Use Compact for tight sidebars and Flexible when you want the widget to stretch to fit its container.

**Appearance** can be Always or Interaction Only. With Always, the widget is always visible. With Interaction Only, it stays out of the way and only appears if Cloudflare actually needs the visitor to do something.

**Language** takes a language code such as `en`, `fr` or `zh-CN`, or `auto` to detect it.

[ON SCREEN: Toggle "Disable Submit Button" on, then type into Custom Error Message and Extra Failure Message.]

**Disable Submit Button** keeps the submit button inactive until Turnstile is solved. With Interaction Only, the button stays usable unless a visible challenge actually becomes necessary, so visitors never face a greyed-out button with no explanation.

**Custom Error Message** replaces the default failure message, and **Extra Failure Message** adds text to it, such as "Still stuck? Email our support team."

[ON SCREEN: Scroll down to the "Per-Integration Widget Overrides" table.]

Below that, the **Per-Integration Widget Overrides** table has one row per integration, where you can override **Theme**, **Widget Size** and **Language**. Leave a field on "Inherit global setting" to follow the defaults. So you could have a dark widget on a dark checkout and a compact light one in an Elementor popup.

### Integrations

[ON SCREEN: The Integrations tab, scrolling slowly through the cards.]

The **Integrations** tab is where you choose what to protect. You'll only see cards for plugins that are actually active on your site, so the list stays relevant.

[ON SCREEN: The WordPress Integration card.]

**WordPress Core** has a master toggle plus separate switches for the **Login Form**, which also covers custom login screens built with `wp_login_form()`, the **Registration Form**, the **Password Reset Form**, and the **Comments Form**. Like most cards, it has an **Injection Mode**: **Auto-inject**, which is the default, or **Shortcode only**. With Shortcode only, you place the widget yourself with `[kitgenix_turnstile]`. Either way, validation always stays on.

[ON SCREEN: The WooCommerce Integration card, highlighting the Classic and Blocks sections.]

**WooCommerce** is split in two. **WooCommerce Classic** has toggles for **Checkout Form (Classic)**, **Product Reviews**, and the My Account **Login**, **Registration** and **Password Reset** forms. **WooCommerce Blocks** has its own **Injection Mode – Blocks Checkout**, because the block checkout submits through WooCommerce's Store API, and the plugin attaches and verifies the token there. The Classic checkout toggle doesn't affect Blocks checkout.

[ON SCREEN: The Easy Digital Downloads card, then the Elementor card.]

**Easy Digital Downloads** gets the **Checkout Form**, **Login**, **Registration** and **Profile / Account** forms. **Elementor** covers Elementor Pro Forms, with a fallback for Elementor forms that don't have the Pro render hook.

[ON SCREEN: Quick scroll past the form-plugin cards.]

Then there are the form builders, each with its own on/off switch and injection mode: **Contact Form 7**, **WPForms**, **Fluent Forms**, **Gravity Forms**, **Formidable Forms**, **Forminator**, **Jetpack Forms**, **Kadence Forms**, **JetFormBuilder** and **Ninja Forms**. Each one hooks into that plugin's real validation step, so AJAX forms work too. One note on Kadence: it's the "Form (Adv)" block that's protected.

[ON SCREEN: The community, newsletter and membership cards.]

Then there's **bbPress**, **BuddyPress** and **wpDiscuz** for communities, **MailPoet** for newsletter sign-ups, and **MemberPress**, **Paid Memberships Pro** and **Ultimate Member** for memberships. Tip: if your MemberPress login relies on WordPress authentication, keep the WordPress Login integration on as well.

### Security

[ON SCREEN: Open the Advanced dropdown and click Security.]

Open the **Advanced** dropdown and choose **Security**.

**Enable Replay Protection** rejects a Turnstile token if someone tries to reuse it, for a short window that's 10 minutes by default. It's on by default for new installs, and it also stops accidental double submissions. Checkouts are handled carefully here: the token is only marked as used once the order is actually created, so a customer retrying a declined payment isn't blocked.

**Enable Honeypot Fallback Field** adds a hidden trap field next to the widget. Real visitors never see it. Simple bots that fill in every field get blocked before your server even contacts Cloudflare.

[ON SCREEN: The Whitelist Settings card.]

Under **Whitelist Settings**: **Skip for Logged-in Users** is useful for membership sites and intranets. The **IP Address Whitelist** takes exact IPs, `*` wildcards or CIDR ranges, one per line. The **User Agent Whitelist** supports wildcards too, but use it carefully, because user agents are easy to fake. Whitelisted visitors don't even load the Turnstile script.

[ON SCREEN: The Reverse Proxy card, with "Trust Cloudflare/Proxy Headers" and "Trusted Proxy IPs".]

If your site sits behind Cloudflare's proxy, a load balancer or another reverse proxy, turn on **Trust Cloudflare/Proxy Headers** and list your proxy IPs or CIDRs under **Trusted Proxy IPs**. The plugin only trusts headers like `CF-Connecting-IP` or `X-Forwarded-For` when the request really comes from one of those proxies. That stops attackers faking their IP address.

### Advanced

[ON SCREEN: Advanced dropdown, then Advanced. Show the Developer Mode card.]

The **Advanced** panel has two cards.

**Developer Mode** includes **Development Mode (Warn-only)**. When it's on, failures are logged but submissions aren't blocked, which is ideal on staging. You'll see an admin notice while it's active so you don't forget about it. Right below it, **Test Mode (per integration)** does the same thing for specific integrations only. So you could trial a new Gravity Forms setup in warn-only mode while the rest of the site stays fully protected.

[ON SCREEN: The Reliability card, "Cloudflare outage failsafe" toggle.]

Then there's **Reliability** and the **Cloudflare outage failsafe**, which is off by default. When it's on, your server checks whether Cloudflare is reachable, caching the result for two minutes. If Cloudflare is confirmed down, protected forms can still be submitted rather than blocking every visitor. A visitor can't trigger this themselves, and every bypass is logged separately from real passes.

### Portability

[ON SCREEN: Advanced dropdown, then Portability. Export and Import cards.]

**Portability** exports your settings as JSON for another site. Keys are left out unless you tick **Include Site Key and Secret Key**. On import you choose **Replace** or **Merge** and see a preview before anything is applied. Great for agencies with a standard setup.

### Log and Support

[ON SCREEN: Briefly show the Log tab, then the Support tab.]

We'll come back to **Log** shortly. **Support** has docs, review and optional donation links, with no settings to worry about.

## 7. Real-World Example

### Scenario A: Protecting a WooCommerce checkout from card-testing bots

[ON SCREEN: Integrations tab, WooCommerce card.]

Let's put this to work. Say you run a WooCommerce store and you're seeing bursts of failed small orders. That's a classic card-testing pattern.

First, make sure the Site Keys test has passed. Then on the WooCommerce card, turn on **Enable for WooCommerce Forms**. If your store uses the classic checkout, turn on **Checkout Form (Classic)**. If you use the newer block checkout, leave **Blocks Checkout** on **Auto-inject**. While you're here, turn on the My Account **Login** and **Registration** forms as well. Save.

[ON SCREEN: A private browser window. Add a product to the cart and go to checkout. The widget appears just before the Place Order button.]

Now open a private window, add something to the cart and go to checkout. The widget sits right before **Place order**. A real customer gets a tick and checks out as normal.

[ON SCREEN: The Log tab, showing a "Passed" row for the WooCommerce checkout with its latency in milliseconds.]

Back in the admin, the **Log** tab shows the check: which integration it came from, whether it passed, and how long the Cloudflare round trip took. An automated script that posts to checkout without a valid token is rejected before the order is created.

### Scenario B: Stopping spam on a WPForms contact form

[ON SCREEN: The WPForms card, with "Enable for WPForms" toggled on and Auto-inject selected.]

Second example: a contact form built with WPForms. Turn on **Enable for WPForms** and leave it on **Auto-inject**. That's it. You don't need to edit the form.

[ON SCREEN: The front-end contact page, with the widget near the submit button. Submit a message successfully.]

The widget appears near the submit area, and checking happens in WPForms' own processing step, so AJAX submissions are covered too. If you want it somewhere specific, switch to **Shortcode only** and drop `[kitgenix_turnstile]` into your form.

[ON SCREEN: Display tab, Per-Integration Widget Overrides, WPForms row set to Compact.]

If the full-size widget looks too big in your layout, go to Per-Integration Widget Overrides and set WPForms to **Compact**. Everything else on the site keeps the global size.

## 8. Troubleshooting / Health Checks / Log Tab

[ON SCREEN: The Log tab, top section: Protection health and Active protection alerts.]

So what if something isn't working? Start on the **Log** tab.

At the top, **Protection health** gives you a plain-English status: **Healthy**, **No recent traffic**, **High failure rate**, **Cloudflare unavailable**, **Duplicate Turnstile loader**, or **Configuration problem**. Under that, **Active protection alerts** flags two things: a spike in genuine verification failures, and repeated errors where your server couldn't reach Cloudflare's siteverify endpoint.

[ON SCREEN: The five "Your site impact" tiles.]

**Your site impact** shows five numbers: **Turnstile checks**, **Verified users**, **Blocked attempts**, **Retry events** and **Challenge friction**. Retries are kept separate on purpose. Things like stale page caches or slow widget loads count as friction, not as blocked bots, so your blocked numbers stay honest.

[ON SCREEN: The Integration health matrix, then searching for "woo".]

The **Integration health matrix** breaks that down by integration, with protection status, Auto or Shortcode mode, checks, passes, failures, retries and the latest outcome. It's searchable, too.

[ON SCREEN: The Recent diagnostic log, filtering by Integration, Result, Category and Time period. Then the category legend.]

Then the **Recent diagnostic log**, filterable by integration, result, category and time period, with a legend explaining each category. **Honeypot** blocks are almost always bots. A **stale nonce** usually means a cached page, not an attack. A **missing token** means the widget may not have loaded in time. A **configuration error** means your keys don't match your Cloudflare dashboard.

[ON SCREEN: The Clear log button, the "Auto-delete logs after" dropdown (7 / 14 / 30 days), Copy recent log, and the CSV export links.]

New in 2.1.0: **Clear log**, and **Auto-delete logs after** 7, 14 or 30 days, with 30 as the default. You can also copy the log for a support ticket or export it as CSV. It keeps the latest 50 events and never stores raw IP addresses, page URLs or the token itself.

[ON SCREEN: Tools → Site Health, showing the "Cloudflare Turnstile readiness" test.]

The plugin also adds a **Cloudflare Turnstile readiness** test to WordPress **Site Health**, so problems show up where you'd normally look.

[ON SCREEN: A checklist on screen.]

If the widget isn't showing at all, check these in order:

1. Your Site Key is saved.
2. The integration *and* the specific form toggle are both on.
3. For login or registration forms, the setup verification test has passed.
4. You're not testing while logged in with "Skip for Logged-in Users" turned on, which catches out nearly everyone.
5. No optimisation or security plugin is blocking `challenges.cloudflare.com`.
6. There's no duplicate-loader warning. The plugin warns you if another plugin or theme is also loading Turnstile's script.

"Expired" or "already used" errors usually come from cached pages, the back button, double-clicks or a form left open too long, because tokens are short-lived and single-use. The plugin refreshes idle widgets automatically to help.

## 9. Tips & Best Practices

[ON SCREEN: A presenter on camera, or a "Pro tips" title card.]

A few tips before we wrap up.

**Test in warn-only mode first.** On a busy live site, turn on Test Mode for just the integration you're adding, watch the Log for a day, then enforce it.

**Use Interaction Only on checkout and contact forms** for the least friction, and **Always** where you want visitors to see the protection, such as a login page.

**Match your design with per-integration overrides.** These cover theme, size and language. The whole site still uses one Site Key and Secret Key pair.

**Keep your secret out of the database** on production by using the wp-config constants or environment variables.

**Turn on the honeypot.** It costs real visitors nothing and stops the simplest bots early.

**Behind a proxy or load balancer? Set up trusted proxies** so the plugin sees real visitor IPs.

**Decide on the outage failsafe.** A store might prefer a few unprotected minutes to lost sales. A high-risk login page might choose the opposite.

**And remember, the shortcode only renders the widget.** A completely custom PHP form still needs its own server-side check.

## 10. Recap & Call to Action

[ON SCREEN: A montage of the widget on login, WooCommerce checkout, WPForms and a bbPress reply form.]

So that's **Kitgenix CAPTCHA for Cloudflare Turnstile**. One plugin and one set of keys covering WordPress core, WooCommerce Classic and Blocks, Easy Digital Downloads, Elementor, ten form builders, and the community, newsletter and membership plugins. Every submission is verified on your server, there's no Kitgenix middleman, and you get a Log tab that tells you in plain English what's happening.

[ON SCREEN: The WordPress.org plugin page URL, the documentation link and the Kitgenix website.]

You'll find the plugin on WordPress.org, and our full documentation is linked in the description. If it's useful to you, a review on WordPress.org really helps us.

[ON SCREEN: Subscribe button animation, then the comment prompt.]

Subscribe for more WordPress and WooCommerce tutorials. And let us know in the comments which form plugin you're protecting, or which integration you'd like a deep-dive on next. Thanks for watching, and we'll see you in the next one.

[ON SCREEN: End card with the Kitgenix logo, a link to the next video and the playlist.]
